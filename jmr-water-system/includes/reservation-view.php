<?php
// includes/reservation-view.php

$pageTitle = 'Reservation Details';
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
	"SELECT r.*, u.full_name, u.email, c.phone, c.address AS profile_address,
            cu.full_name AS cancelled_by_name, cu.role AS cancelled_by_role
     FROM reservations r
     JOIN customers c ON r.customer_id = c.customer_id
     JOIN users u ON c.user_id = u.user_id
     LEFT JOIN users cu ON r.cancelled_by = cu.user_id
     WHERE r.reservation_id = ?"
);
$stmt->execute([$id]);
$r = $stmt->fetch();

$pay = getPayment($pdo, $r['reservation_id']);
$payStatus = $pay['payment_status'] ?? 'unpaid';

if (!$r) {
	header("Location: reservations.php");
	exit;
}

$messages = [
	'updated' => ['success', 'Reservation updated.'],
	'confirmed' => ['success', 'Reservation confirmed.'],
	'completed' => ['success', 'Reservation marked as completed.'],
	'cancelled' => ['success', 'Reservation cancelled.'],
	'noedit' => ['danger', 'This reservation can no longer be edited.'],
	'nostock' => ['danger', 'Not enough gallons in stock for this action. Please add stock in Inventory first, then try again.'],
	'error' => ['danger', 'That action is not allowed for the current status of the reservation.'],
];
$msg = $_GET['msg'] ?? '';

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/navbar.php';
?>

<h1 class="page-title">Reservation <?= e(formatReservationId($r['reservation_id'])) ?></h1>

<?php if (isset($messages[$msg])): ?>
	<div class="alert alert-<?= $messages[$msg][0] ?>"><?= e($messages[$msg][1]) ?></div>
<?php endif; ?>

<div class="panel form-panel">
	<dl class="detail-list">
		<dt>Status</dt>
		<dd><?= statusBadge($r['status']) ?></dd>

		<?php if ($r['status'] !== 'cancelled'): ?>
			<dt>Payment</dt>
			<dd>
				<?= paymentBadge($payStatus) ?>
				<?php if ($payStatus === 'paid'): ?>
					on
					<?= e(date('M d, Y h:i A', strtotime($pay['date_paid']))) ?>
				<?php endif; ?>
			</dd>
		<?php endif; ?>

		<dt>Customer</dt>
		<dd><?= e($r['full_name']) ?></dd>

		<dt>Email</dt>
		<dd><?= e($r['email']) ?></dd>

		<dt>Phone</dt>
		<dd><?= e($r['phone']) ?></dd>

		<dt>Type</dt>
		<dd><?= e(typeLabel($r['reservation_type'])) ?></dd>

		<dt>Quantity</dt>
		<dd><?= e($r['quantity']) ?> x 20-liter gallon(s)</dd>

		<dt>Price</dt>
		<dd>
			<?= e(peso($r['unit_price'])) ?> per gallon
		</dd>

		<dt>Total Amount</dt>
		<dd><strong>
				<?= e(peso($r['total_amount'])) ?>
			</strong> (Cash on Delivery)</dd>

		<dt>Date</dt>
		<dd><?= e(date('F d, Y', strtotime($r['reservation_date']))) ?></dd>

		<dt>Time Slot</dt>
		<dd><?= e(ucfirst($r['time_slot'])) ?></dd>

		<?php if ($r['reservation_type'] === 'delivery'): ?>
			<dt>Delivery Address</dt>
			<dd><?= nl2br(e($r['delivery_address'])) ?></dd>
		<?php endif; ?>

		<dt>Notes</dt>
		<dd><?= $r['notes'] ? nl2br(e($r['notes'])) : '<span class="empty-text">None</span>' ?></dd>

		<dt>Submitted</dt>
		<dd><?= e(date('M d, Y h:i A', strtotime($r['created_at']))) ?></dd>

		<?php if ($r['status'] === 'cancelled' && $r['cancelled_at']): ?>
			<dt>Cancelled On</dt>
			<dd><?= e(date('M d, Y h:i A', strtotime($r['cancelled_at']))) ?></dd>
			<dt>Cancelled By</dt>
			<dd><?= e($r['cancelled_by_name']) ?> (<?= e(ucfirst($r['cancelled_by_role'])) ?>)</dd>
		<?php endif; ?>
	</dl>

	<p style="margin-top:24px;">
		<a href="reservations.php" class="btn-sm gray"><i class="fa-solid fa-arrow-left"></i> Back</a>

		<?php if ($r['status'] === 'pending'): ?>
			<?= actionForm($r['reservation_id'], 'confirm', 'Confirm', 'success', 'Confirm this reservation?', 'view') ?>
		<?php endif; ?>
		<?php if (in_array($r['status'], ['confirmed', 'scheduled', 'out_for_delivery'])): ?>
			<?= actionForm($r['reservation_id'], 'complete', 'Mark Completed', 'success', 'Mark this reservation as completed?', 'view') ?>
		<?php endif; ?>
		<?php if (in_array($r['status'], ['pending', 'confirmed', 'scheduled'])): ?>
			<a href="reservation-edit.php?id=<?= (int) $r['reservation_id'] ?>" class="btn-sm warning">Edit</a>
			<?= actionForm($r['reservation_id'], 'cancel', 'Cancel', 'danger', 'Cancel this reservation?', 'view') ?>
		<?php endif; ?>
	</p>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>