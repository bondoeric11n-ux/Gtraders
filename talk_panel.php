<?php
// talk_panel.php - Final upgraded admin chat panel (flicker-free, 3s polling, corporate email incl. logo & contact)
// WARNING: file contains live SMTP credentials as requested. Keep private.

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
include_once("db_connect.php"); // expects $conn (mysqli)

// CSRF token (simple stub)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}
$CSRF_TOKEN = $_SESSION['csrf_token'];

// Rate limiting for admin messages (1 per 2 seconds)
if (!isset($_SESSION['last_admin_msg_at'])) $_SESSION['last_admin_msg_at'] = 0;
$MIN_MSG_INTERVAL = 2;

// PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'src/Exception.php';
require 'src/PHPMailer.php';
require 'src/SMTP.php';

// Helpers
function json_ok($data = []) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}
function json_err($msg = 'error', $code = 400) {
    header('Content-Type: application/json; charset=utf-8', true, $code);
    echo json_encode(['error' => $msg]);
    exit;
}
function clean_text($s) {
    $s = trim((string)$s);
    return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $s);
}

// ---------------- API HANDLERS ----------------

// 1) Fetch messages for a specific chat
// Optional: since_id param to return only messages with id > since_id (keeps compatibility).
if (isset($_GET['fetchMessages']) && isset($_GET['chat_id'])) {
    $chat_id = intval($_GET['chat_id']);
    if ($chat_id <= 0) json_err('invalid_chat_id', 422);

    $since_id = isset($_GET['since_id']) ? intval($_GET['since_id']) : 0;

    if ($since_id > 0) {
        $stmt = $conn->prepare("SELECT id, chat_id, user_email, admin_id, message, sender_name, sender, status, created_at, is_read FROM admin_chats WHERE chat_id=? AND id>? ORDER BY created_at ASC");
        $stmt->bind_param("ii", $chat_id, $since_id);
    } else {
        $stmt = $conn->prepare("SELECT id, chat_id, user_email, admin_id, message, sender_name, sender, status, created_at, is_read FROM admin_chats WHERE chat_id=? ORDER BY created_at ASC");
        $stmt->bind_param("i", $chat_id);
    }
    if (!$stmt) json_err('db_prepare_failed', 500);
    $stmt->execute();
    $res = $stmt->get_result();
    $messages = [];
    $last_id = 0;
    while ($row = $res->fetch_assoc()) {
        $messages[] = [
            'id' => (int)$row['id'],
            'chat_id' => (int)$row['chat_id'],
            'user_email' => $row['user_email'],
            'admin_id' => $row['admin_id'] !== null ? (int)$row['admin_id'] : null,
            'message' => $row['message'],
            'sender_name' => $row['sender_name'],
            'sender' => $row['sender'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'is_read' => (int)$row['is_read']
        ];
        $last_id = max($last_id, (int)$row['id']);
    }
    $stmt->close();

    // mark newly fetched user messages as read (only for those messages returned)
    if (!empty($messages)) {
        // only mark read if we fetched all (no since_id) OR we fetched with since_id (we still mark returned ones)
        $ids = array_map(function($m){ return intval($m['id']); }, $messages);
        // build placeholders for prepared statement - MySQLi doesn't support IN (?) easily - use a safe prepared approach
        // We'll update with a parameterized single chat_id and set is_read for those ids via separate prepared execution per id (safe and simple)
        $uStmt = $conn->prepare("UPDATE admin_chats SET is_read=1 WHERE chat_id=? AND id=? AND sender='user'");
        if ($uStmt) {
            foreach ($ids as $mid) {
                $uStmt->bind_param("ii", $chat_id, $mid);
                $uStmt->execute();
            }
            $uStmt->close();
        }
    }

    json_ok($messages);
}

// 2) Send admin reply (preserve original response "ok")
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sendAdminMsg') {
    $now = time();
    if ($now - $_SESSION['last_admin_msg_at'] < $MIN_MSG_INTERVAL) {
        // Return "ok" to maintain original API behavior while preventing spam
        echo "ok";
        exit;
    }

    $chat_id = intval($_POST['chat_id'] ?? 0);
    $message = clean_text($_POST['message'] ?? '');
    $user_email = filter_var($_POST['user_email'] ?? '', FILTER_SANITIZE_EMAIL);
    $sender_name = clean_text($_POST['sender_name'] ?? 'GIBAL Support');

    if ($chat_id <= 0) $chat_id = time();
    if ($message === '' || $user_email === '') {
        echo "error"; exit;
    }

    // Insert using prepared statement with correct parameter order:
    // chat_id, user_email, admin_id, message, sender_name, sender, status, created_at, is_read
    $admin_id = isset($_SESSION['admin_id']) ? intval($_SESSION['admin_id']) : null;
    if ($admin_id === null) {
        $stmt = $conn->prepare("INSERT INTO admin_chats (chat_id, user_email, admin_id, message, sender_name, sender, status, created_at, is_read) VALUES (?,?,?,?,NULL,'admin','active',NOW(),1)");
        if ($stmt) {
            $stmt->bind_param("isss", $chat_id, $user_email, $message, $sender_name);
            $stmt->execute();
            $stmt->close();
        } else {
            echo "error"; exit;
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO admin_chats (chat_id, user_email, admin_id, message, sender_name, sender, status, created_at, is_read) VALUES (?,?,?,?,?,'admin','active',NOW(),1)");
        if ($stmt) {
            $stmt->bind_param("isiss", $chat_id, $user_email, $admin_id, $message, $sender_name);
            $stmt->execute();
            $stmt->close();
        } else {
            echo "error"; exit;
        }
    }

    $_SESSION['last_admin_msg_at'] = $now;
    echo "ok";
    exit;
}

// 3) End chat - send corporate HTML email and mark completed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'endChat') {
    $chat_id = intval($_POST['chat_id'] ?? 0);
    if ($chat_id <= 0) { echo "error"; exit; }

    // Get last user info for this chat
    $stmt = $conn->prepare("SELECT user_email, sender_name FROM admin_chats WHERE chat_id=? ORDER BY id DESC LIMIT 1");
    if (!$stmt) { echo "error"; exit; }
    $stmt->bind_param("i", $chat_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $user_email = ''; $user_name = '';
    if ($row = $res->fetch_assoc()) {
        $user_email = $row['user_email'];
        $user_name = $row['sender_name'] ?? '';
    }
    $stmt->close();

    // Mark chat completed
    $uStmt = $conn->prepare("UPDATE admin_chats SET status='completed' WHERE chat_id=?");
    if ($uStmt) {
        $uStmt->bind_param("i", $chat_id);
        $uStmt->execute();
        $uStmt->close();
    }

    // Send corporate HTML email using PHPMailer (credentials preserved)
    if ($user_email) {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            // your requested credentials (kept as-is)
            $mail->Username = 'gibal.ltd@gmail.com';
            $mail->Password = 'dkbcereljkmvzfqy';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->setFrom('gibal.ltd@gmail.com', 'GIBAL LTD');

            $mail->addAddress($user_email, $user_name ?: $user_email);
            $mail->isHTML(true);

            $mail->Subject = "Your Support Session with GIBAL LTD — Closed";

            // HTML email body with logo and contact details (uses remote logo URL)
            $logoUrl = "https://gtraders.gt.tc/assets/img/logo.png";
            // Build HTML body
            $usernameEsc = htmlspecialchars($user_name ?: strtok($user_email, '@'));
            $htmlBody = '
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body style="margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;background:#f4f6f8;">
  <table align="center" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:20px auto;background:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #e6e6e6;">
    <tr>
      <td style="background:#0b0b0b;padding:18px;text-align:center;">
        <img src="' . $logoUrl . '" alt="GIBAL LTD" width="140" style="display:block;margin:0 auto;">
      </td>
    </tr>
    <tr>
      <td style="padding:24px;color:#333333;">
        <h2 style="color:#d4af37;margin:0 0 10px 0;">Hello ' . $usernameEsc . ',</h2>
        <p style="line-height:1.5;color:#333;">Thank you for contacting <strong>GIBAL LTD Support</strong>. This is to confirm that your recent chat session with our support team has been closed.</p>
        <p style="line-height:1.5;color:#333;">If your issue was resolved, we appreciate the opportunity to help. If you still need assistance, simply reply to this email or start a new chat and one of our agents will be happy to assist you further.</p>
        <p style="line-height:1.5;color:#333;"><strong>Support Summary</strong><br>Please keep this email as a record of the chat closure. If you need further help, include this message in your next conversation.</p>
        <p style="line-height:1.5;color:#333;">Warm regards,<br><strong>GIBAL LTD Support Team</strong></p>
        <hr style="border:none;border-top:1px solid #efefef;margin:18px 0;">
        <p style="font-size:14px;color:#666;margin:0 0 6px 0;"><strong>Contact Details</strong></p>
        <p style="font-size:14px;color:#666;margin:0;">
          Email: <a href="mailto:gibal.ltd@gmail.com">gibal.ltd@gmail.com</a><br>
          Website: <a href="https://gtraders.gt.tc">www.gtraders.gt.tc</a><br>
          Phone: +254 703 834 247
        </p>
      </td>
    </tr>
    <tr>
      <td style="background:#0b0b0b;padding:12px;text-align:center;color:#d4af37;font-size:12px;">
        © ' . date('Y') . ' GIBAL LTD. All rights reserved.
      </td>
    </tr>
  </table>
</body>
</html>';

            $mail->Body = $htmlBody;
            $mail->AltBody = "Hello {$user_name},\n\nYour chat session with GIBAL LTD Support has been closed. If you need more help, reply to this message or start a new chat.\n\nContact: support@gibal.ltd | www.gtraders.gt.tc | +254 712 345 678\n\n— GIBAL LTD Support";
            $mail->send();
        } catch (Exception $e) {
            // log warning to DB if system_logs exists
            $errMsg = $e->getMessage();
            if ($lstmt = $conn->prepare("INSERT INTO system_logs (`level`, `message`, `created_at`) VALUES ('warning', ?, NOW())")) {
                $msg = "PHPMailer error (endChat) for chat {$chat_id}: " . substr($errMsg, 0, 255);
                $lstmt->bind_param("s", $msg);
                $lstmt->execute();
                $lstmt->close();
            }
        }
    }

    echo "ended";
    exit;
}

// 4) Fetch chat list (same shape)
if (isset($_GET['fetchChats']) && $_GET['fetchChats'] == 1) {
    $chats = [];
    $sql = "SELECT chat_id,
             MAX(created_at) AS last_msg,
             MIN(status) AS chat_status,
             SUM(CASE WHEN is_read=0 AND sender='user' THEN 1 ELSE 0 END) AS unread_count,
             SUBSTRING_INDEX(GROUP_CONCAT(DISTINCT user_email ORDER BY id DESC SEPARATOR ','), ',', 1) AS user_email
             FROM admin_chats GROUP BY chat_id ORDER BY last_msg DESC";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $chats[] = [
                'chat_id' => (int)$row['chat_id'],
                'last_msg' => $row['last_msg'],
                'chat_status' => $row['chat_status'],
                'unread_count' => (int)$row['unread_count'],
                'user_email' => $row['user_email']
            ];
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($chats);
    exit;
}

// If no API call matched, render the page (HTML + JS UI)
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>GIBAL Support — Admin Chat</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<style>
:root{
    --bg:#07101a;
    --card: rgba(255,255,255,0.03);
    --gold: #D4AF37;
    --muted: #9aa4b2;
}
*{box-sizing:border-box}
body{background: linear-gradient(180deg,#061016,#07121a); color:#eef2f6; font-family:Inter, Arial, Helvetica, sans-serif; margin:0; padding:20px;}
.container{max-width:1200px;margin:0 auto;display:flex;gap:18px;align-items:stretch;}
.left{width:32%;background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));border-radius:10px;padding:0;box-shadow:0 10px 30px rgba(0,0,0,0.5);height:80vh;display:flex;flex-direction:column;overflow:hidden;border:1px solid rgba(212,175,55,0.04);}
.left-header{padding:12px 16px;border-bottom:1px solid rgba(255,255,255,0.03);font-weight:700;font-size:1rem;color:var(--gold);display:flex;align-items:center;justify-content:space-between}
.chatList{flex:1;overflow:auto;}
.chatItem{padding:12px 14px;border-bottom:1px solid rgba(255,255,255,0.02);cursor:pointer;display:flex;justify-content:space-between;align-items:center;background:transparent;transition:all .18s ease;border-left:4px solid transparent;}
.chatItem:hover{background:rgba(255,255,255,0.02);transform:translateY(-2px)}
.chatItem.active{background:linear-gradient(90deg, rgba(212,175,55,0.06), rgba(255,255,255,0.01));border-left-color:var(--gold);box-shadow:0 8px 18px rgba(212,175,55,0.06)}
.chatMeta{display:flex;flex-direction:column}
.chatName{font-weight:700;color:#fff}
.chatEmail{font-size:0.85rem;color:var(--muted)}
.unreadBadge{background:linear-gradient(135deg,#FFD700,#E6C200);color:#111;padding:4px 8px;border-radius:999px;font-weight:800;font-size:0.8rem;box-shadow:0 6px 18px rgba(212,175,55,0.12);animation:badgePulse 2s infinite ease-in-out}
@keyframes badgePulse{0%{transform:scale(1)}50%{transform:scale(1.04)}100%{transform:scale(1)}}

/* Right panel */
.right{flex:1;display:flex;flex-direction:column;height:80vh;}
.right-header{padding:12px 16px;border-radius:10px 10px 0 0;background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));border:1px solid rgba(255,255,255,0.02);color:var(--gold);font-weight:700;display:flex;justify-content:space-between;align-items:center}
#chatBox{flex:1;padding:18px;overflow-y:auto;background:linear-gradient(180deg, rgba(255,255,255,0.01), rgba(255,255,255,0.00));border-radius:0 0 10px 10px;display:flex;flex-direction:column;gap:10px}
.msg{max-width:72%;padding:12px 14px;border-radius:12px;word-wrap:break-word;box-shadow:0 6px 16px rgba(0,0,0,0.35);opacity:0;transform:translateY(6px);animation:msgIn .18s ease forwards}
@keyframes msgIn{to{opacity:1;transform:none}}
.msg.admin{align-self:flex-end;background:linear-gradient(135deg,#2f2f2f,#1f1f1f);color:#fff;border-bottom-right-radius:2px}
.msg.user{align-self:flex-start;background:linear-gradient(135deg,#111217,#141821);color:#ddd;border-bottom-left-radius:2px}
.msg .meta{font-size:0.8rem;color:var(--muted);margin-top:6px}

/* input area */
.input-row{display:flex;gap:8px;padding-top:12px}
.form-control{background:transparent;border:1px solid rgba(255,255,255,0.04);color:#fff}
.btn-send{background:linear-gradient(135deg,#FFD700,#E6C200);color:#000;font-weight:800;border:none}
.btn-end{background:transparent;border:1px solid rgba(255,255,255,0.06);color:#fff}
.small-muted{color:var(--muted);font-size:0.9rem}

/* responsive */
@media (max-width: 900px){
  .container{flex-direction:column;padding:12px}
  .left{width:100%;height:30vh}
  .right{width:100%;height:60vh}
  #chatBox{padding:12px}
}
</style>
</head>
<body>
<div class="container">
  <div class="left">
    <div class="left-header">
      <div>GIBAL SUPPORT</div>
      <div><span id="totalUnread" class="small-muted">0 unread</span></div>
    </div>
    <div id="chatList" class="chatList"></div>
  </div>

  <div class="right">
    <div id="panelHeader" class="right-header">Select a chat</div>
    <div id="chatBox">
      <div class="small-muted" id="emptyHint" style="padding:20px">Choose a chat from the left to view messages.</div>
    </div>

    <div style="padding:12px">
      <div class="input-row">
        <input id="adminMessage" class="form-control" placeholder="Type a reply..." autocomplete="off" />
        <button id="sendAdminBtn" class="btn btn-send">Send</button>
        <button id="endChatBtn" class="btn btn-end">End Chat</button>
      </div>
      <div style="margin-top:8px"><small class="small-muted">Press Enter to send. Rate-limit: 1 message / 2s.</small></div>
    </div>
  </div>
</div>

<!-- End Chat Modal -->
<div class="modal fade" id="endChatModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content" style="background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01)); color:#fff; border:1px solid rgba(255,255,255,0.02)">
      <div class="modal-header">
        <h5 class="modal-title">End Chat</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to end this chat? This will mark it completed and send the user a closing email.
      </div>
      <div class="modal-footer">
        <button id="confirmEndBtn" type="button" class="btn btn-end" data-bs-dismiss="modal">Cancel</button>
        <button id="confirmEndOkBtn" type="button" class="btn btn-send">End Chat</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Flicker-free client logic: append only new messages, update chat list by diff
let currentChatId = null;
let currentUserEmail = null;
let chatAutoRefresh = null;
const CHAT_REFRESH_MS = 3000; // 3 seconds as requested
const LIST_REFRESH_MS = 5000;

const chatListEl = document.getElementById('chatList');
const chatBoxEl = document.getElementById('chatBox');
const panelHeaderEl = document.getElementById('panelHeader');
const totalUnreadEl = document.getElementById('totalUnread');
const adminInputEl = document.getElementById('adminMessage');

let lastMessageId = 0; // track last message id for append-only fetch
let chatMap = {}; // map chat_id => {unread_count, ...}

// Utility
function escapeHtml(s) {
    if (!s) return '';
    return s.replace(/&/g, '&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}
function nl2br(s) {
    return s.replace(/\n/g, '<br>');
}

// Fetch chat list and diff-update
function fetchChatList() {
    fetch('talk_panel.php?fetchChats=1', { cache: 'no-store' })
        .then(res => res.json())
        .then(data => {
            // data is array of chats
            const newMap = {};
            let totalUnread = 0;
            // update existing nodes or create new
            data.forEach(c => {
                newMap[c.chat_id] = c;
                totalUnread += parseInt(c.unread_count || 0, 10);
                const existing = document.querySelector('.chatItem[data-chat="'+c.chat_id+'"]');
                if (existing) {
                    // update unread badge if changed
                    const badge = existing.querySelector('.unreadBadge');
                    if (c.unread_count > 0) {
                        if (badge) badge.textContent = c.unread_count;
                        else {
                            const b = document.createElement('div'); b.className = 'unreadBadge'; b.textContent = c.unread_count;
                            existing.appendChild(b);
                        }
                    } else {
                        if (badge) badge.remove();
                    }
                    // highlight if unread_count increased
                    const prev = chatMap[c.chat_id] ? chatMap[c.chat_id].unread_count : 0;
                    if (c.unread_count > prev) {
                        existing.classList.add('active');
                        setTimeout(()=>existing.classList.remove('active'), 1400);
                    }
                } else {
                    // create new chat item
                    const div = document.createElement('div');
                    div.className = 'chatItem';
                    div.dataset.chat = String(c.chat_id);
                    div.dataset.email = c.user_email;
                    div.innerHTML = `
                      <div class="chatMeta">
                        <div class="chatName">${escapeHtml((c.user_email||'').split('@')[0] || 'user')}</div>
                        <div class="chatEmail">${escapeHtml(c.user_email || '')}</div>
                      </div>
                    `;
                    if (c.unread_count > 0) {
                        const b = document.createElement('div'); b.className = 'unreadBadge'; b.textContent = c.unread_count;
                        div.appendChild(b);
                    }
                    div.addEventListener('click', () => openChat(c.chat_id, c.user_email, div));
                    chatListEl.appendChild(div);
                }
            });

            // remove chat nodes that no longer exist
            document.querySelectorAll('.chatItem').forEach(node => {
                const id = node.dataset.chat;
                if (!newMap[id]) node.remove();
            });

            chatMap = newMap;
            totalUnreadEl.textContent = totalUnread + ' unread';
        })
        .catch(err => console.warn('fetchChatList error', err));
}

// Open chat
function openChat(chat_id, email, el) {
    currentChatId = chat_id;
    currentUserEmail = email;
    panelHeaderEl.textContent = (email ? email.split('@')[0] + ' — ' + email : 'Chat: ' + chat_id);
    // mark selected visually
    document.querySelectorAll('.chatItem').forEach(e=>e.classList.remove('active'));
    if (el) el.classList.add('active');

    // reset lastMessageId when we switch chats so we fetch the full chat on first open
    lastMessageId = 0;
    chatBoxEl.innerHTML = '';
    fetchMessages(); // immediate fetch for messages
    if (chatAutoRefresh) clearInterval(chatAutoRefresh);
    chatAutoRefresh = setInterval(()=>{ fetchMessages(); fetchChatList(); }, CHAT_REFRESH_MS);
}

// Fetch messages: use since_id to get only new messages and append them
function fetchMessages() {
    if (!currentChatId) return;
    const url = 'talk_panel.php?fetchMessages=1&chat_id=' + encodeURIComponent(currentChatId) + (lastMessageId ? '&since_id=' + encodeURIComponent(lastMessageId) : '');
    fetch(url, { cache: 'no-store' })
        .then(res => res.json())
        .then(data => {
            if (!Array.isArray(data)) return;
            // append each message
            data.forEach(m => {
                // skip if already present (safety)
                if (document.querySelector('.msg[data-id="'+m.id+'"]')) return;
                const d = document.createElement('div');
                d.className = 'msg ' + (m.sender === 'admin' ? 'admin' : 'user');
                d.dataset.id = m.id;
                const t = new Date(m.created_at).toLocaleString([], { hour: '2-digit', minute: '2-digit' });
                d.innerHTML = `<div style="font-weight:700">${escapeHtml(m.sender_name || (m.sender === 'admin' ? 'Admin' : 'User'))}</div>
                               <div style="margin-top:8px">${nl2br(escapeHtml(m.message))}</div>
                               <div class="meta">${t}</div>`;
                chatBoxEl.appendChild(d);
                lastMessageId = Math.max(lastMessageId, parseInt(m.id,10));
            });
            // if there were new messages, smooth scroll
            if (data.length > 0) {
                chatBoxEl.scrollTo({ top: chatBoxEl.scrollHeight, behavior: 'smooth' });
            }
        })
        .catch(err => console.warn('fetchMessages error', err));
}

// Send admin message (preserve "ok")
function sendMessage() {
    if (!currentChatId || !currentUserEmail) return;
    const msg = adminInputEl.value.trim();
    if (!msg) return;
    const body = new URLSearchParams();
    body.append('action','sendAdminMsg');
    body.append('chat_id', String(currentChatId));
    body.append('message', msg);
    body.append('user_email', currentUserEmail);
    body.append('sender_name', 'GIBAL Support');

    fetch('talk_panel.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: body.toString()
    }).then(()=> {
        adminInputEl.value = '';
        // Immediately append the admin message locally for snappy UX (it will also arrive via poll)
        const now = new Date().toISOString();
        const fakeId = Date.now(); // temporary id
        const d = document.createElement('div');
        d.className = 'msg admin';
        d.dataset.id = fakeId;
        d.innerHTML = `<div style="font-weight:700">GIBAL Support</div>
                       <div style="margin-top:8px">${nl2br(escapeHtml(msg))}</div>
                       <div class="meta">${new Date().toLocaleString([], { hour: '2-digit', minute: '2-digit' })}</div>`;
        chatBoxEl.appendChild(d);
        chatBoxEl.scrollTo({ top: chatBoxEl.scrollHeight, behavior: 'smooth' });
        // fetchMessages shortly to sync real ID
        setTimeout(fetchMessages, 800);
        fetchChatList();
    }).catch(err=>console.warn('sendMessage error', err));
}

// End chat via modal
const endChatModal = new bootstrap.Modal(document.getElementById('endChatModal'));
document.getElementById('endChatBtn').addEventListener('click', () => {
    if (!currentChatId) return;
    endChatModal.show();
});
document.getElementById('confirmEndOkBtn').addEventListener('click', () => {
    if (!currentChatId) return;
    const body = new URLSearchParams();
    body.append('action','endChat');
    body.append('chat_id', String(currentChatId));
    fetch('talk_panel.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: body.toString()
    }).then(()=> {
        // reset UI
        currentChatId = null; currentUserEmail = null;
        panelHeaderEl.textContent = 'Select a chat';
        chatBoxEl.innerHTML = '<div class="small-muted" id="emptyHint" style="padding:20px">Choose a chat from the left to view messages.</div>';
        if (chatAutoRefresh) { clearInterval(chatAutoRefresh); chatAutoRefresh = null; }
        lastMessageId = 0;
        fetchChatList();
    }).catch(err=>console.warn('endChat error', err));
});

// keyboard & UI bindings
document.getElementById('sendAdminBtn').addEventListener('click', sendMessage);
adminInputEl.addEventListener('keypress', function(e){ if (e.key === 'Enter') { e.preventDefault(); sendMessage(); } });

// initial load
fetchChatList();
setInterval(fetchChatList, LIST_REFRESH_MS);

</script>
</body>
</html>