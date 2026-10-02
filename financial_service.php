<?php
declare(strict_types=1);

/** All money movement goes through these helpers and users.account_balance. */
function credit_deposit_once(mysqli $conn, int $depositId, float $amount, string $providerReference): bool
{
    $conn->begin_transaction();
    try {
        $statement = $conn->prepare('SELECT id, user_id, amount_ksh, credited FROM btc_deposits WHERE id = ? FOR UPDATE');
        $statement->bind_param('i', $depositId); $statement->execute();
        $deposit = $statement->get_result()->fetch_assoc(); $statement->close();
        if (!$deposit || (int) $deposit['credited'] === 1) { $conn->rollback(); return false; }
        if (round((float) $deposit['amount_ksh'], 2) !== round($amount, 2)) { throw new RuntimeException('Provider amount does not match recorded deposit.'); }
        $statement = $conn->prepare("UPDATE btc_deposits SET status='credited', credited=1, provider_reference=?, processed_at=NOW() WHERE id=? AND credited=0");
        $statement->bind_param('si', $providerReference, $depositId); $statement->execute();
        if ($statement->affected_rows !== 1) { throw new RuntimeException('Deposit was already handled.'); }
        $statement->close();
        $userId = (int) $deposit['user_id'];
        $statement = $conn->prepare('UPDATE users SET account_balance = account_balance + ? WHERE id = ?');
        $statement->bind_param('di', $amount, $userId); $statement->execute();
        if ($statement->affected_rows !== 1) { throw new RuntimeException('Deposit user was not found.'); }
        $statement->close();
        $event = 'deposit_credit'; $referenceType = 'btc_deposits'; $referenceId = (string) $depositId;
        $statement = $conn->prepare('INSERT INTO financial_ledger (user_id,event_type,amount,reference_type,reference_id) VALUES (?,?,?,?,?)');
        $statement->bind_param('isdss', $userId, $event, $amount, $referenceType, $referenceId); $statement->execute(); $statement->close();
        $conn->commit(); return true;
    } catch (Throwable $exception) { $conn->rollback(); throw $exception; }
}

function mature_investment_once(mysqli $conn, int $investmentId): bool
{
    $conn->begin_transaction();
    try {
        $statement = $conn->prepare("SELECT id,user_id,net_amount,expected_interest FROM investments WHERE id=? AND status='active' FOR UPDATE");
        $statement->bind_param('i', $investmentId); $statement->execute(); $investment = $statement->get_result()->fetch_assoc(); $statement->close();
        if (!$investment) { $conn->rollback(); return false; }
        $total = round((float) $investment['net_amount'] + (float) $investment['expected_interest'], 2); $userId = (int) $investment['user_id'];
        $statement = $conn->prepare("UPDATE investments SET status='matured', matured_at=NOW() WHERE id=? AND status='active'");
        $statement->bind_param('i', $investmentId); $statement->execute();
        if ($statement->affected_rows !== 1) { throw new RuntimeException('Investment was already processed.'); }
        $statement->close(); $principal = (float) $investment['net_amount'];
        $statement = $conn->prepare('UPDATE users SET account_balance=account_balance+?, invested_balance=invested_balance-? WHERE id=?');
        $statement->bind_param('ddi', $total, $principal, $userId); $statement->execute(); $statement->close();
        $event = 'investment_maturity'; $referenceType = 'investments'; $referenceId = (string) $investmentId;
        $statement = $conn->prepare('INSERT INTO financial_ledger (user_id,event_type,amount,reference_type,reference_id) VALUES (?,?,?,?,?)');
        $statement->bind_param('isdss', $userId, $event, $total, $referenceType, $referenceId); $statement->execute(); $statement->close();
        $conn->commit(); return true;
    } catch (Throwable $exception) { $conn->rollback(); throw $exception; }
}
