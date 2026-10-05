<?php
require_once '../config/database.php';
require_once '../includes/auth-check.php';

requireRole(['customer']);

$pageTitle = 'My Dashboard';

$stmt = $pdo->prepare("SELECT customer_id FROM customers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$customer = $stmt->fetch();
if (!$customer) {
	die("Customer record not found. Please contact the administrator.");
}
$cid = $customer['customer_id'];

// Current reservations: not yet completed or cancelled
$stmt = $pdo->prepare(
	"SELECT * FROM reservations
     WHERE customer_id = ? AND status NOT IN ('completed', 'cancelled')
     ORDER BY reservation_date ASC, reservation_id ASC"
);
$stmt->execute([$cid]);
$current = $stmt->fetchAll();

// Upcoming delivery: nearest confirmed delivery from today onward
$stmt = $pdo->prepare(
	"SELECT * FROM reservations
     WHERE customer_id = ?
       AND reservation_type = 'delivery'
       AND status IN ('confirmed', 'scheduled', 'out_for_delivery')
       AND reservation_date >= CURDATE()
     ORDER BY reservation_date ASC
     LIMIT 1"
);
$stmt->execute([$cid]);
$upcoming = $stmt->fetch();

// History: the 5 most recent reservations of any status
$stmt = $pdo->prepare(
	"SELECT * FROM reservations
     WHERE customer_id = ?
     ORDER BY created_at DESC, reservation_id DESC
     LIMIT 5"
);
$stmt->execute([$cid]);
$history = $stmt->fetchAll();

$stmt = $pdo->prepare(
	"SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, notification_id DESC LIMIT 5"
);
$stmt->execute([$_SESSION['user_id']]);
$recentNotifs = $stmt->fetchAll();

// Payment status (Cash on Delivery)
$stmt = $pdo->prepare(
	"SELECT r.reservation_id, r.reservation_date, r.total_amount,
            p.payment_status, p.date_paid
     FROM reservations r
     LEFT JOIN payments p ON p.reservation_id = r.reservation_id
     WHERE r.customer_id = ? AND r.status <> 'cancelled'
     ORDER BY r.created_at DESC, r.reservation_id DESC"
);
$stmt->execute([$cid]);
$payRows = $stmt->fetchAll();
$amountDue = 0;
foreach ($payRows as $pr) {
	if (($pr['payment_status'] ?? '') !== 'paid') {
		$amountDue += $pr['total_amount'];
	}
}
$payRows = array_slice($payRows, 0, 5);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/navbar.php';
?>

<h1 class="page-title">Welcome, <?= e($_SESSION['full_name']) ?>!</h1>

<div class="panel">
	<p>Need water? Make a reservation for delivery or pickup.</p>
	<a href="/jmr-water-system/customer/reservation.php" class="btn-inline btn-big">
		<i class="fa-solid fa-plus"></i> Make Reservation
	</a>
</div>

<div class="panel">
	<h3>Current Reservations</h3>
	<?php if (empty($current)): ?>
		<p class="empty-text">You have no active reservations.</p>
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
					<?php foreach ($current as $r): ?>
						<tr>
							<td><?= e(formatReservationId($r['reservation_id'])) ?></td>
							<td><?= e(typeLabel($r['reservation_type'])) ?></td>
							<td><?= e($r['quantity']) ?></td>
							<td><?= e(date('M d, Y', strtotime($r['reservation_date']))) ?></td>
							<td><?= e(ucfirst($r['time_slot'])) ?></td>
							<td><?= statusBadge($r['status']) ?></td>
							<td><a href="reservation-details.php?id=<?= (int) $r['reservation_id'] ?>" class="btn-sm">View</a></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>

<div class="panel">
	<h3>Upcoming Delivery</h3>
	<?php if (!$upcoming): ?>
		<p class="empty-text">No upcoming delivery.</p>
	<?php else: ?>
		<p>
			<strong><?= e(formatReservationId($upcoming['reservation_id'])) ?></strong>:
			<?= e($upcoming['quantity']) ?> gallon(s) on
			<?= e(date('F d, Y', strtotime($upcoming['reservation_date']))) ?>
			(<?= e(ucfirst($upcoming['time_slot'])) ?>)
			<?= statusBadge($upcoming['status']) ?>
		</p>
	<?php endif; ?>
</div>

<div class="panel">
	<h3>Payment Status (Cash on Delivery)</h3>
	<?php if (empty($payRows)): ?>
		<p class="empty-text">No payments to show yet.</p>
	<?php else: ?>
		<p>Amount to pay on delivery or pickup: <strong><?= e(peso($amountDue)) ?></strong></p>
		<div class="table-wrap">
			<table class="data-table">
				<thead>
					<tr>
						<th>Reservation</th>
						<th>Date</th>
						<th>Amount</th>
						<th>Payment</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($payRows as $pr): ?>
						<tr>
							<td><?= e(formatReservationId($pr['reservation_id'])) ?></td>
							<td><?= e(date('M d, Y', strtotime($pr['reservation_date']))) ?></td>
							<td><?= e(peso($pr['total_amount'])) ?></td>
							<td>
								<?= paymentBadge(($pr['payment_status'] ?? '') === 'paid' ? 'paid' : 'unpaid') ?>
								<?php if (($pr['payment_status'] ?? '') === 'paid'): ?>
									<small><?= e(date('M d, Y', strtotime($pr['date_paid']))) ?></small>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>

<div class="panel">
	<h3>Recent Notifications</h3>
	<?php if (empty($recentNotifs)): ?>
		<p class="empty-text">No notifications yet.</p>
	<?php else: ?>
		<?php foreach ($recentNotifs as $n): ?>
			<div class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>">
				<i class="fa-solid <?= e(notificationIcon($n['notification_type'])) ?> notif-icon"></i>
				<?= e($n['message']) ?>
				<small>
					<?= e(timeAgo($n['created_at'])) ?>
				</small>
			</div>
		<?php endforeach; ?>
		<p style="margin-top:12px;"><a href="notifications.php">View all notifications &rarr;</a></p>
	<?php endif; ?>
</div>

<div class="panel">
	<h3>Reservation History</h3>
	<?php if (empty($history)): ?>
		<p class="empty-text">No reservations yet.</p>
	<?php else: ?>
		<div class="table-wrap">
			<table class="data-table">
				<thead>
					<tr>
						<th>ID</th>
						<th>Type</th>
						<th>Qty</th>
						<th>Date</th>
						<th>Status</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($history as $r): ?>
						<tr>
							<td><?= e(formatReservationId($r['reservation_id'])) ?></td>
							<td><?= e(typeLabel($r['reservation_type'])) ?></td>
							<td><?= e($r['quantity']) ?></td>
							<td><?= e(date('M d, Y', strtotime($r['reservation_date']))) ?></td>
							<td><?= statusBadge($r['status']) ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p style="margin-top:12px;"><a href="my-reservations.php">View all reservations &rarr;</a></p>
	<?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>