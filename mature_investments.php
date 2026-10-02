<?php
require_once "db_connect.php";
require_once "InvestmentService.php";

$service = new InvestmentService($conn);

$active = $service->getActiveInvestments();
$now = new DateTime();

while ($inv = $active->fetch_assoc()) {

    $end = new DateTime($inv['end_date']);

    if ($now >= $end) {

        // Calculate returns
        $calc = $service->calculateReturns($inv['amount'], $inv['interest_rate']);

        // Credit user: principal + net interest
        $totalCredit = $inv['amount'] + $calc['net'];
        $service->creditUser($inv['user_id'], $totalCredit);

        // Mark investment matured
        $service->markAsMatured($inv['id']);

        echo "Matured investment ID: {$inv['id']}\n";
    }
}
?>