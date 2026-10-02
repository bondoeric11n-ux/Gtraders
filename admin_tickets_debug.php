<?php
session_start();

// ✅ Show ALL errors (this is critical for debugging)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>DEBUG MODE - Admin Tickets</h1>";
echo "<pre style='background: #000; color: #0f0; padding: 20px; border-radius: 8px;'>";

// Check session
echo "🔍 SESSION CHECK:\n";
echo "Session ID: " . session_id() . "\n";
echo "Role: " . ($_SESSION['role'] ?? 'NOT SET') . "\n";
echo "Is Admin: " . (isset($_SESSION['is_admin']) ? 'YES' : 'NO') . "\n";
echo "Admin ID: " . ($_SESSION['admin_id'] ?? 'NOT SET') . "\n";
echo "User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n\n";

// Check database connection
echo "🔍 DATABASE CONNECTION:\n";
require_once 'db_connect.php';

if ($conn->connect_error) {
    die("❌ Database connection failed: " . $conn->connect_error);
}
echo "✅ Database connected successfully\n";
echo "Database name: " . $conn->select_db($conn->query("SELECT DATABASE()")->fetch_row()[0]) . "\n\n";

// Check if tables exist
echo "🔍 TABLE CHECK:\n";
$tables = ['complaints', 'disputes', 'ticket_followups'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "✅ Table '$table' exists\n";
    } else {
        echo "❌ Table '$table' DOES NOT EXIST\n";
    }
}
echo "\n";

// Check table structure
echo "🔍 TABLE STRUCTURE:\n";
$result = $conn->query("DESCRIBE complaints");
if ($result) {
    echo "complaints table columns:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - {$row['Field']} ({$row['Type']})\n";
    }
}
echo "\n";

$result = $conn->query("DESCRIBE disputes");
if ($result) {
    echo "disputes table columns:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - {$row['Field']} ({$row['Type']})\n";
    }
}
echo "\n";

$result = $conn->query("DESCRIBE ticket_followups");
if ($result) {
    echo "ticket_followups table columns:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - {$row['Field']} ({$row['Type']})\n";
    }
}
echo "\n";

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "🔍 POST REQUEST RECEIVED:\n";
    echo "POST data:\n";
    print_r($_POST);
    echo "\n";

    // Test status update
    if (isset($_POST['update_status'])) {
        echo "🔍 ATTEMPTING STATUS UPDATE:\n";
        
        $ticket_type = $_POST['ticket_type'] ?? null;
        $ticket_id = (int)($_POST['ticket_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? null;

        echo "Ticket Type: $ticket_type\n";
        echo "Ticket ID: $ticket_id\n";
        echo "New Status: $new_status\n\n";

        if (!$ticket_type || !$ticket_id || !$new_status) {
            echo "❌ ERROR: Missing required fields\n";
            echo "ticket_type: " . ($ticket_type ? 'YES' : 'NO') . "\n";
            echo "ticket_id: " . ($ticket_id ? 'YES' : 'NO') . "\n";
            echo "new_status: " . ($new_status ? 'YES' : 'NO') . "\n";
        } else {
            echo "Attempting to update...\n";
            
            if ($ticket_type === 'complaint') {
                $stmt = $conn->prepare("UPDATE complaints SET status = ? WHERE id = ?");
                if (!$stmt) {
                    echo "❌ PREPARE FAILED: " . $conn->error . "\n";
                } else {
                    echo "✅ Statement prepared\n";
                    $stmt->bind_param("si", $new_status, $ticket_id);
                    echo "✅ Parameters bound\n";
                    $result = $stmt->execute();
                    if ($result) {
                        echo "✅ UPDATE SUCCESSFUL! Affected rows: " . $stmt->affected_rows . "\n";
                    } else {
                        echo "❌ EXECUTE FAILED: " . $stmt->error . "\n";
                    }
                    $stmt->close();
                }
            } elseif ($ticket_type === 'dispute') {
                $stmt = $conn->prepare("UPDATE disputes SET status = ? WHERE id = ?");
                if (!$stmt) {
                    echo "❌ PREPARE FAILED: " . $conn->error . "\n";
                } else {
                    echo "✅ Statement prepared\n";
                    $stmt->bind_param("si", $new_status, $ticket_id);
                    echo "✅ Parameters bound\n";
                    $result = $stmt->execute();
                    if ($result) {
                        echo "✅ UPDATE SUCCESSFUL! Affected rows: " . $stmt->affected_rows . "\n";
                    } else {
                        echo "❌ EXECUTE FAILED: " . $stmt->error . "\n";
                    }
                    $stmt->close();
                }
            }
        }
        echo "\n";
    }

    // Test reply
    if (isset($_POST['submit_reply'])) {
        echo "🔍 ATTEMPTING REPLY:\n";
        
        $ticket_type = $_POST['ticket_type'] ?? null;
        $ticket_id = (int)($_POST['ticket_id'] ?? 0);
        $reply_message = trim($_POST['reply_message'] ?? '');
        $admin_id = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 1;

        echo "Ticket Type: $ticket_type\n";
        echo "Ticket ID: $ticket_id\n";
        echo "Reply Message: " . substr($reply_message, 0, 50) . "...\n";
        echo "Admin ID: $admin_id\n\n";

        if (empty($reply_message)) {
            echo "❌ ERROR: Reply message is empty\n";
        } else {
            echo "Attempting to insert followup...\n";
            
            $stmt = $conn->prepare("INSERT INTO ticket_followups (ticket_type, ticket_id, admin_id, message) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                echo "❌ PREPARE FAILED: " . $conn->error . "\n";
            } else {
                echo "✅ Statement prepared\n";
                $stmt->bind_param("siis", $ticket_type, $ticket_id, $admin_id, $reply_message);
                echo "✅ Parameters bound\n";
                $result = $stmt->execute();
                if ($result) {
                    echo "✅ INSERT SUCCESSFUL! Insert ID: " . $conn->insert_id . "\n";
                } else {
                    echo "❌ EXECUTE FAILED: " . $stmt->error . "\n";
                }
                $stmt->close();
            }
        }
        echo "\n";
    }
}

// Fetch tickets
echo "🔍 FETCHING TICKETS:\n";

$complaints = [];
$result = $conn->query("SELECT * FROM complaints ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $complaints[] = $row;
    }
    echo "✅ Found " . count($complaints) . " complaints\n";
} else {
    echo "❌ Failed to fetch complaints: " . $conn->error . "\n";
}

$disputes = [];
$result = $conn->query("SELECT * FROM disputes ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $disputes[] = $row;
    }
    echo "✅ Found " . count($disputes) . " disputes\n";
} else {
    echo "❌ Failed to fetch disputes: " . $conn->error . "\n";
}

echo "\n";
echo "</pre>";

// Show simple form for testing
echo "<h2>Test Form</h2>";
if (!empty($complaints)) {
    $ticket = $complaints[0];
    echo "<form method='POST' style='background: #f0f0f0; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3>Test Complaint #{$ticket['id']}</h3>";
    echo "<input type='hidden' name='ticket_type' value='complaint'>";
    echo "<input type='hidden' name='ticket_id' value='{$ticket['id']}'>";
    echo "<p><strong>Current Status:</strong> {$ticket['status']}</p>";
    echo "<p><strong>Update Status:</strong> ";
    echo "<select name='new_status'>";
    echo "<option value='open'>Open</option>";
    echo "<option value='in_progress'>In Progress</option>";
    echo "<option value='resolved'>Resolved</option>";
    echo "<option value='rejected'>Rejected</option>";
    echo "</select></p>";
    echo "<button type='submit' name='update_status' style='padding: 10px 20px; background: #d4af37; border: none; border-radius: 5px; cursor: pointer;'>Update Status</button>";
    echo "</form>";
    
    echo "<form method='POST' style='background: #f0f0f0; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<input type='hidden' name='ticket_type' value='complaint'>";
    echo "<input type='hidden' name='ticket_id' value='{$ticket['id']}'>";
    echo "<p><strong>Reply Message:</strong></p>";
    echo "<textarea name='reply_message' rows='4' style='width: 100%; padding: 10px;'></textarea>";
    echo "<button type='submit' name='submit_reply' style='padding: 10px 20px; background: #d4af37; border: none; border-radius: 5px; cursor: pointer;'>Send Reply</button>";
    echo "</form>";
}

echo "<p><a href='admin_dashboard.php' style='color: #d4af37;'>← Back to Dashboard</a></p>";
?>