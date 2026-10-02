<?php
$status_class = 'status-' . strtolower($w['status']);
?>
<tr class="<?= $status_class ?>">
  <td><a href="edit_user.php?id=<?= $w['user_id'] ?>" style="color:#D4AF37;text-decoration:none;"><?= htmlspecialchars($w['username']) ?></a></td>
  <td><?= htmlspecialchars($w['email']) ?></td>
  <td><?= htmlspecialchars($w['phone']) ?></td>
  <td><?= number_format($w['account_balance'],2) ?></td>
  <td><?= number_format($w['amount'],2) ?></td>
  <td><?= ucfirst($w['status']) ?></td>
  <td><?= $w['created_at'] ?></td>
  <td>
    <?php if($w['status']=='pending'): ?>
      <form method="post" class="ajax-form" style="display:inline;">
        <input type="hidden" name="withdraw_id" value="<?= $w['id'] ?>">
        <input type="hidden" name="ajax" value="1">
        <button name="action" value="approve" class="btn btn-gold btn-sm">Approve</button>
        <button name="action" value="reject" class="btn btn-danger btn-sm">Reject</button>
        <button name="action" value="dispute" class="btn btn-warning btn-sm">Dispute</button>
      </form>
    <?php elseif($w['status']=='dispute'): ?>
      <form method="post" class="ajax-form" style="display:inline;">
        <input type="hidden" name="withdraw_id" value="<?= $w['id'] ?>">
        <input type="hidden" name="ajax" value="1">
        <button name="action" value="release" class="btn btn-gold btn-sm">Release</button>
        <button name="action" value="deduct" class="btn btn-danger btn-sm">Deduct</button>
      </form>
    <?php else: ?> - <?php endif; ?>
  </td>
</tr>
