<?php
session_start();
include("db_connect.php");

// Protect admin access
if(!isset($_SESSION['admin_id'])){
    header("Location: admin_login.php");
    exit();
}

$conn->begin_transaction();
try {
    // Fetch total fees
    $stmt = $conn->prepare("SELECT SUM(fee_amount) AS total_fees FROM daily_fees");
    $stmt->execute();
    $stmt->bind_result($total_fees);
    $stmt->fetch();
    $stmt->close();

    $total_fees = $total_fees ?? 0;

    if($total_fees > 0){
        // Update admin_wallet safely
        $upd = $conn->prepare("UPDATE admin_wallet SET total_fees = total_fees + ? WHERE id=1");
        $upd->bind_param("d", $total_fees);
        $upd->execute();
        $upd->close();

        // Clear daily_fees
        $conn->query("DELETE FROM daily_fees");
    }

    $conn->commit();
    header("Location: user_fees.php");
    exit();

} catch(Exception $e){
    $conn->rollback();
    echo "Error processing fees: " . $e->getMessage();
}
?>
