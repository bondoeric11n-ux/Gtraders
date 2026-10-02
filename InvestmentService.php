<?php
class InvestmentService {

    private $conn;
    private $feeRate = 0.035; // 3.5% fee

    public function __construct($db) {
        $this->conn = $db;
    }

    // Fetch all active investments
    public function getActiveInvestments() {
        $sql = "SELECT * FROM investments WHERE status='active'";
        return $this->conn->query($sql);
    }

    // Mark an investment as matured
    public function markAsMatured($id) {
        $stmt = $this->conn->prepare("UPDATE investments SET status='matured' WHERE id=?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    // Credit the user's balance
    public function creditUser($user_id, $amount) {
        $stmt = $this->conn->prepare("UPDATE users SET balance = balance + ? WHERE id=?");
        $stmt->bind_param("di", $amount, $user_id);
        return $stmt->execute();
    }

    // Calculate end date
    public function calculateEndDate($start, $hours) {
        $seconds = $hours * 3600;
        $end = new DateTime($start);
        $interval = new DateInterval('PT' . intval($seconds) . 'S');
        return $end->add($interval)->format("Y-m-d H:i:s");
    }

    // Calculate interest + fee + net
    public function calculateReturns($amount, $interest_rate) {
        $rawInterest = $amount * ($interest_rate / 100);
        $fee = $rawInterest * $this->feeRate;
        $net = $rawInterest - $fee;

        return [
            "interest" => $rawInterest,
            "fee"      => $fee,
            "net"      => $net
        ];
    }
}
?>