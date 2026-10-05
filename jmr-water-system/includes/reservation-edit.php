<?php
// includes/reservation-edit.php

$pageTitle = 'Edit Reservation';
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
	"SELECT r.*, u.full_name
     FROM reservations r
     JOIN customers c ON r.customer_id = c.customer_id
     JOIN users u ON c.user_id = u.user_id
     WHERE r.reservation_id = ?"
);
$stmt->execute([$id]);
$r = $stmt->fetch();

if (!$r) {
	header("Location: reservations.php");
	exit;
}

// Only active reservations can be edited
if (!in_array($r['status'], ['pending', 'confirmed', 'scheduled'])) {
	header("Location: reservation-view.php?id=$id&msg=noedit");
	exit;
}

// A paid reservation can no longer be edited (the paid amount is already recorded)
$stmt = $pdo->prepare("SELECT payment_id FROM payments WHERE reservation_id = ? AND payment_status = 'paid'");
$stmt->execute([$id]);
if ($stmt->fetch()) {
	header("Location: reservation-view.php?id=$id&msg=noedit");
	exit;
}

$errors = [];

// Start with the saved values
$type = $r['reservation_type'];
$quantity = $r['quantity'];
$date = $r['reservation_date'];
$slot = $r['time_slot'];
$address = $r['delivery_address'] ?? '';
$notes = $r['notes'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$type = $_POST['reservation_type'] ?? '';
	$quantity = (int) ($_POST['quantity'] ?? 0);
	$date = $_POST['reservation_date'] ?? '';
	$slot = $_POST['time_slot'] ?? '';
	$address = trim($_POST['delivery_address'] ?? '');
	$notes = trim($_POST['notes'] ?? '');

	if (!in_array($type, ['delivery', 'pickup'])) {
		$errors[] = "Please choose Delivery or Pickup.";
	}
	if ($quantity < 1 || $quantity > 100) {
		$errors[] = "Quantity must be between 1 and 100 gallons.";
	}
	if (!in_array($slot, ['morning', 'noon', 'afternoon'])) {
		$errors[] = "Please choose a time slot.";
	}

	$dateObj = DateTime::createFromFormat('Y-m-d', $date);
	if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
		$errors[] = "Please choose a valid date.";
	} elseif ($date < date('Y-m-d') && $date !== $r['reservation_date']) {
		// Past dates are only allowed if the date was not changed
		$errors[] = "Reservation date cannot be moved to a past date.";
	}

	if ($type === 'delivery' && $address === '') {
		$errors[] = "Delivery address is required for delivery.";
	}
	$saveAddress = ($type === 'pickup') ? null : $address;
	if ($r['status'] === 'scheduled' && $type !== 'delivery') {
		$errors[] = "This reservation has a scheduled delivery. Cancel the delivery first before changing it to pickup.";
	}

	// A confirmed or scheduled reservation already holds its gallons, so only an increase needs stock
	if (empty($errors) && in_array($r['status'], ['confirmed', 'scheduled']) && $quantity > $r['quantity']) {
		$invCheck = getInventorySummary($pdo);
		if ($quantity - $r['quantity'] > $invCheck['available']) {
			$errors[] = "Not enough available stock for that quantity. Available gallons: " . $invCheck['available'] . ".";
		}
	}

	if (empty($errors)) {
		$stmt = $pdo->prepare(
			"UPDATE reservations
             SET reservation_type = ?, quantity = ?, total_amount = unit_price * ?,
                 reservation_date = ?, time_slot = ?, delivery_address = ?, notes = ?
             WHERE reservation_id = ?
               AND status IN ('pending', 'confirmed', 'scheduled')"
		);
		$stmt->execute([
			$type,
			$quantity,
			$quantity,
			$date,
			$slot,
			$saveAddress,
			$notes === '' ? null : $notes,
			$id
		]);

		// Keep a scheduled delivery on the same date and time slot
		$stmt = $pdo->prepare(
			"UPDATE deliveries SET delivery_date = ?, time_slot = ?
             WHERE reservation_id = ? AND status = 'scheduled'"
		);
		$stmt->execute([$date, $slot, $id]);

		header("Location: reservation-view.php?id=$id&msg=updated");
		exit;
	}
}

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/navbar.php';
?>

<h1 class="page-title">Edit <?= e(formatReservationId($r['reservation_id'])) ?></h1>

<?php if (!empty($errors)): ?>
	<div class="alert alert-danger">
		<?php foreach ($errors as $err): ?>
			<div><?= e($err) ?></div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<div class="panel form-panel">
	<p><strong>Customer:</strong> <?= e($r['full_name']) ?> &nbsp; <?= statusBadge($r['status']) ?></p>

	<form method="POST" action="">
		<div class="form-row">
			<div class="form-group">
				<label>Reservation Type</label>
				<select name="reservation_type" id="reservation_type">
					<option value="delivery" <?= $type === 'delivery' ? 'selected' : '' ?>>Delivery</option>
					<option value="pickup" <?= $type === 'pickup' ? 'selected' : '' ?>>Customer Pickup</option>
				</select>
			</div>
			<div class="form-group">
				<label>Quantity (20-liter gallons)</label>
				<input type="number" name="quantity" min="1" max="100" value="<?= e($quantity) ?>" required>
			</div>
		</div>

		<div class="form-row">
			<div class="form-group">
				<label>Date</label>
				<input type="date" name="reservation_date" value="<?= e($date) ?>" required>
			</div>
			<div class="form-group">
				<label>Time Slot</label>
				<select name="time_slot" required>
					<option value="morning" <?= $slot === 'morning' ? 'selected' : '' ?>>Morning</option>
					<option value="noon" <?= $slot === 'noon' ? 'selected' : '' ?>>Noon</option>
					<option value="afternoon" <?= $slot === 'afternoon' ? 'selected' : '' ?>>Afternoon</option>
				</select>
			</div>
		</div>

		<div class="form-group" id="address_group">
			<label>Delivery Address</label>
			<textarea name="delivery_address" rows="2"><?= e($address) ?></textarea>
		</div>

		<div class="form-group">
			<label>Notes</label>
			<textarea name="notes" rows="2"><?= e($notes) ?></textarea>
		</div>

		<button type="submit" class="btn-inline"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
		<a href="reservation-view.php?id=<?= (int) $id ?>" class="btn-sm gray" style="margin-left:8px;">Cancel</a>
	</form>
</div>

<script>
	const typeSelect = document.getElementById('reservation_type');
	const addressGroup = document.getElementById('address_group');
	function toggleAddress() {
		addressGroup.style.display = typeSelect.value === 'delivery' ? 'block' : 'none';
	}
	typeSelect.addEventListener('change', toggleAddress);
	toggleAddress();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>