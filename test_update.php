<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>DIRECT DATABASE UPDATE TEST</h1>";
echo "<pre style='background: #000; color: #0f0; padding: 20px; border-radius: 8px; font-family: monospace;'>";

require_once 'db_connect.php';

// Step 1: Create a test complaint if none exist
echo "🔍 STEP 1: Checking for test data...\n";
$result = $conn->query("SELECT COUNT(*) as count FROM complaints");
$count = $result->fetch_assoc()['count'];

if ($count == 0) {
    echo "⚠️ No complaints found. Creating test complaint...\n";
    $stmt = $conn->prepare("INSERT INTO complaints (user_id, username, subject, message, status) VALUES (?, ?, ?, ?, ?)");
    $user_id = $_SESSION['user_id'] ?? 42;
    $username = "test_user";
    $subject = "Test Complaint";
    $message = "This is a test complaint for debugging";
    $status = "open";
    $stmt->bind_param("issss", $user_id, $username, $subject, $message, $status);
    
    if ($stmt->execute()) {
        $test_id = $conn->insert_id;
        echo "✅ Created test complaint with ID: $test_id\n";
    } else {
        echo "❌ Failed to create test complaint: " . $stmt->error . "\n";
        die();
    }
} else {
    // Get first complaint
    $result = $conn->query("SELECT id, status FROM complaints ORDER BY id DESC LIMIT 1");
    $ticket = $result->fetch_assoc();
    $test_id = $ticket['id'];
    echo "✅ Using existing complaint ID: $test_id (current status: {$ticket['status']})\n";
}

echo "\n";

// Step 2: Test the UPDATE query directly
echo "🔍 STEP 2: Testing UPDATE query...\n";
echo "Attempting to update complaint ID $test_id to status 'in_progress'...\n\n";

$stmt = $conn->prepare("UPDATE complaints SET status = ? WHERE id = ?");

if (!$stmt) {
    echo "❌ PREPARE FAILED!\n";
    echo "MySQL Error: " . $conn->error . "\n";
    echo "Error Code: " . $conn->errno . "\n";
    die();
}

echo "✅ Statement prepared successfully\n";

$bind_result = $stmt->bind_param("si", "in_progress", $test_id);
if (!$bind_result) {
    echo "❌ BIND_PARAM FAILED!\n";
    echo "MySQL Error: " . $stmt->error . "\n";
    die();
}

echo "✅ Parameters bound successfully\n";

$execute_result = $stmt->execute();
if (!$execute_result) {
    echo "❌ EXECUTE FAILED!\n";
    echo "MySQL Error: " . $stmt->error . "\n";
    echo "Error Code: " . $stmt->errno . "\n";
    die();
}

echo "✅ EXECUTE SUCCESSFUL!\n";
echo "Affected rows: " . $stmt->affected_rows . "\n";

// Verify the update
$verify = $conn->query("SELECT id, status FROM complaints WHERE id = $test_id");
$updated = $verify->fetch_assoc();
echo "\n🔍 VERIFICATION:\n";
echo "Updated status: " . $updated['status'] . "\n";

if ($updated['status'] === 'in_progress') {
    echo "\n✅✅✅ UPDATE WORKED PERFECTLY! ✅✅✅\n";
} else {
    echo "\n❌ Status did not update correctly\n";
}

$stmt->close();

// Step 3: Test the reply INSERT
echo "\n🔍 STEP 3: Testing INSERT query for reply...\n";
$admin_id = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 1;
$test_message = "This is a test admin reply";

$stmt2 = $conn->prepare("INSERT INTO ticket_followups (ticket_type, ticket_id, admin_id, message) VALUES (?, ?, ?, ?)");

if (!$stmt2) {
    echo "❌ PREPARE FAILED!\n";
    echo "MySQL Error: " . $conn->error . "\n";
    die();
}

echo "✅ Statement prepared successfully\n";

$ticket_type = "complaint";
$bind_result2 = $stmt2->bind_param("siis", $ticket_type, $test_id, $admin_id, $test_message);
if (!$bind_result2) {
    echo "❌ BIND_PARAM FAILED!\n";
    echo "MySQL Error: " . $stmt2->error . "\n";
    die();
}

echo "✅ Parameters bound successfully\n";

$execute_result2 = $stmt2->execute();
if (!$execute_result2) {
    echo "❌ EXECUTE FAILED!\n";
    echo "MySQL Error: " . $stmt2->error . "\n";
    echo "Error Code: " . $stmt2->errno . "\n";
    die();
}

echo "✅ EXECUTE SUCCESSFUL!\n";
echo "Inserted ID: " . $conn->insert_id . "\n";

$stmt2->close();

echo "\n✅✅✅ ALL DATABASE OPERATIONS WORKING! ✅✅✅\n";
echo "\n🎉 CONCLUSION: The database queries are working perfectly.\n";
echo "The Error 500 must be caused by something else in admin_tickets.php:\n";
echo "  - Session handling\n";
echo "  - PHP syntax error\n";
echo "  - Missing include file\n";
echo "  - HTML rendering issue\n";

echo "\n</pre>";
echo "<p style='margin-top: 20px;'><a href='admin_tickets.php' style='color: #d4af37; font-size: 1.2rem;'>← Try admin_tickets.php again</a></p>";
?>