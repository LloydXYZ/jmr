<?php
// includes/customer-view.php

$pageTitle = 'Customer Details';
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
	"SELECT c.customer_id, c.phone, c.address, c.created_at,
            u.full_name, u.email, u.status
     FROM customers c
     JOIN users u ON c.user_id = u.user_id
     WHERE c.customer_id = ?"
);
$stmt->execute([$id]);
$c = $stmt->fetch();

if (!$c) {
	header("Location: customers.php?msg=error");
	exit;
}

// This customer's reservation history
$stmt = $pdo->prepare(
	"SELECT * FROM reservations WHERE customer_id = ?
     ORDER BY reservation_date DESC, reservation_id DESC"
);
$stmt->execute([$id]);
$history = $stmt->fetchAll();

// This customer's paid payments
$stmt = $pdo->prepare(
	"SELECT p.payment_id, p.reservation_id, p.amount, p.payment_method, p.date_paid
     FROM payments p
     JOIN reservations r ON p.reservation_id = r.reservation_id
     WHERE r.customer_id = ? AND p.payment_status = 'paid'
     ORDER BY p.date_paid DESC"
);
$stmt->execute([$id]);
$paymentHistory = $stmt->fetchAll();
$totalPaid = array_sum(array_column($paymentHistory, 'amount'));

$messages = [
	'updated' => ['success', 'Customer updated.'],
	'deactivated' => ['success', 'Customer deactivated. They can no longer log in.'],
	'activated' => ['success', 'Customer activated.'],
	'error' => ['danger', 'That action could not be completed.'],
];
$msg = $_GET['msg'] ?? '';

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/navbar.php';
?>

<h1 class="page-title"><?= e($c['full_name']) ?></h1>

<?php if (isset($messages[$msg])): ?>
	<div class="alert alert-<?= $messages[$msg][0] ?>"><?= e($messages[$msg][1]) ?></div>
<?php endif; ?>

<div class="panel form-panel">
	<dl class="detail-list">
		<dt>Account Status</dt>
		<dd><?= accountBadge($c['status']) ?></dd>

		<dt>Email</dt>
		<dd><?= e($c['email']) ?></dd>

		<dt>Phone</dt>
		<dd><?= e($c['phone']) ?></dd>

		<dt>Address</dt>
		<dd><?= nl2br(e($c['address'])) ?></dd>

		<dt>Date Registered</dt>
		<dd><?= e(date('F d, Y', strtotime($c['created_at']))) ?></dd>
	</dl>

	<p style="margin-top:24px;">
		<a href="customers.php" class="btn-sm gray"><i class="fa-solid fa-arrow-left"></i> Back</a>
		<a href="customer-edit.php?id=<?= (int) $c['customer_id'] ?>" class="btn-sm warning">Edit</a>
	<form method="POST" action="customer-action.php" style="display:inline;"
		onsubmit="return confirm('<?= $c['status'] === 'active' ? 'Deactivate this customer?' : 'Activate this customer?' ?>');">
		<input type="hidden" name="customer_id" value="<?= (int) $c['customer_id'] ?>">
		<input type="hidden" name="action" value="<?= $c['status'] === 'active' ? 'deactivate' : 'activate' ?>">
		<input type="hidden" name="return" value="view">
		<?php if ($c['status'] === 'active'): ?>
			<button type="submit" class="btn-sm danger">Deactivate</button>
		<?php else: ?>
			<button type="submit" class="btn-sm success">Activate</button>
		<?php endif; ?>
	</form>
	</p>
</div>

<div class="panel">
	<h3>Reservation History</h3>
	<?php if (empty($history)): ?>
		<p class="empty-text">This customer has no reservations yet.</p>
	<?php else: ?>
		<div class="table-wrap">
			<table class="data-table">
				<thead>
					<tr>
						<th>ID</th>
						<th>Type</th>
						<th>Qty</th>
						<th>Date</th>
						<th>Time</th>
						<th>Status</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($history as $r): ?>
						<tr>
							<td><?= e(formatReservationId($r['reservation_id'])) ?></td>
							<td><?= e(typeLabel($r['reservation_type'])) ?></td>
							<td><?= e($r['quantity']) ?></td>
							<td><?= e(date('M d, Y', strtotime($r['reservation_date']))) ?></td>
							<td><?= e(ucfirst($r['time_slot'])) ?></td>
							<td><?= statusBadge($r['status']) ?></td>
							<td><a href="reservation-view.php?id=<?= (int) $r['reservation_id'] ?>" class="btn-sm">View</a></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>

<div class="panel">
	<h3>Payment History</h3>
	<?php if (empty($paymentHistory)): ?>
		<p class="empty-text">No payments recorded for this customer yet.</p>
	<?php else: ?>
		<p>Total paid: <strong><?= e(peso($totalPaid)) ?></strong></p>
		<div class="table-wrap">
			<table class="data-table">
				<thead>
					<tr>
						<th>Payment</th>
						<th>Reservation</th>
						<th>Amount</th>
						<th>Method</th>
						<th>Date Paid</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($paymentHistory as $p): ?>
						<tr>
							<td><?= e(formatPaymentId($p['payment_id'])) ?></td>
							<td><a
									href="reservation-view.php?id=<?= (int) $p['reservation_id'] ?>"><?= e(formatReservationId($p['reservation_id'])) ?></a>
							</td>
							<td><?= e(peso($p['amount'])) ?></td>
							<td>COD</td>
							<td><?= e(date('M d, Y h:i A', strtotime($p['date_paid']))) ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>