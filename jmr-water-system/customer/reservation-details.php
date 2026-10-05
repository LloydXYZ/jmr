<?php
require_once '../config/database.php';
require_once '../includes/auth-check.php';

requireRole(['customer']);

$pageTitle = 'Reservation Details';
$id = (int) ($_GET['id'] ?? 0);

// Fetch the reservation ONLY if it belongs to this customer
$stmt = $pdo->prepare(
	"SELECT r.*
     FROM reservations r
     JOIN customers c ON r.customer_id = c.customer_id
     WHERE r.reservation_id = ? AND c.user_id = ?"
);
$stmt->execute([$id, $_SESSION['user_id']]);
$r = $stmt->fetch();

if (!$r) {
	header("Location: my-reservations.php");
	exit;
}

$canCancel = in_array($r['status'], ['pending', 'confirmed', 'scheduled']);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/navbar.php';
?>

<h1 class="page-title">Reservation <?= e(formatReservationId($r['reservation_id'])) ?></h1>

<div class="panel form-panel">
	<dl class="detail-list">
		<dt>Status</dt>
		<dd><?= statusBadge($r['status']) ?></dd>

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
		<?php endif; ?>
	</dl>

	<p style="margin-top:24px;">
		<a href="my-reservations.php" class="btn-sm gray"><i class="fa-solid fa-arrow-left"></i> Back</a>
		<?php if ($canCancel): ?>
		<form method="POST" action="cancel-reservation.php" style="display:inline;"
			onsubmit="return confirm('Cancel this reservation?');">
			<input type="hidden" name="reservation_id" value="<?= (int) $r['reservation_id'] ?>">
			<button type="submit" class="btn-sm danger">Cancel Reservation</button>
		</form>
	<?php endif; ?>
	</p>
</div>

<?php require_once '../includes/footer.php'; ?>