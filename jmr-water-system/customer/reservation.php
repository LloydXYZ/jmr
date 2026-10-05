<?php
require_once '../config/database.php';
require_once '../includes/auth-check.php';

requireRole(['customer']);

$pageTitle = 'Make Reservation';

// Find this customer's customer_id and saved address
$stmt = $pdo->prepare("SELECT customer_id, address FROM customers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$customer = $stmt->fetch();
$price = getPricePerGallon($pdo);

if (!$customer) {
	die("Customer record not found. Please contact the administrator.");
}

$errors = [];
$success = isset($_GET['success']);

// Default form values
$type = 'delivery';
$quantity = 1;
$date = '';
$slot = '';
$address = $customer['address'];
$notes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$type = $_POST['reservation_type'] ?? '';
	$quantity = (int) ($_POST['quantity'] ?? 0);
	$date = $_POST['reservation_date'] ?? '';
	$slot = $_POST['time_slot'] ?? '';
	$address = trim($_POST['delivery_address'] ?? '');
	$notes = trim($_POST['notes'] ?? '');

	// Validate
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
	} elseif ($date < date('Y-m-d')) {
		$errors[] = "Reservation date cannot be in the past.";
	}

	if ($type === 'delivery' && $address === '') {
		$errors[] = "Delivery address is required for delivery.";
	}
	if ($type === 'pickup') {
		$address = null; // pickup does not need an address
	}

	// Save
	if (empty($errors)) {
		$stmt = $pdo->prepare(
			"INSERT INTO reservations
             (customer_id, reservation_type, quantity, unit_price, total_amount,
              reservation_date, time_slot, delivery_address, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
		);
		$stmt->execute([
			$customer['customer_id'],
			$type,
			$quantity,
			$price,
			$quantity * $price,
			$date,
			$slot,
			$address,
			$notes === '' ? null : $notes
		]);

		$newId = $pdo->lastInsertId();
		$ref = formatReservationId($newId);
		notifyUser($pdo, $_SESSION['user_id'], "Your reservation $ref was submitted and is waiting for confirmation.", 'reservation');
		notifyStaff($pdo, "New reservation $ref from {$_SESSION['full_name']} ($quantity gallon(s)).", 'reservation');
		logActivity($pdo, "Submitted reservation $ref");

		header("Location: reservation.php?success=1");
		exit;
	}
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/navbar.php';
?>

<h1 class="page-title">Make a Reservation</h1>

<?php if ($success): ?>
	<div class="alert alert-success">Your reservation was submitted! It is now <strong>Pending</strong> until the station
		confirms it.</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
	<div class="alert alert-danger">
		<?php foreach ($errors as $err): ?>
			<div><?= e($err) ?></div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<div class="panel form-panel">
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
				<input type="number" name="quantity" id="quantity" min="1" max="100" value="<?= e($quantity) ?>" required>
				<div class="form-hint">Price: <?= e(peso($price)) ?> per gallon. Total: <strong id="total"></strong> (pay on
					delivery)</div>
			</div>
		</div>

		<div class="form-row">
			<div class="form-group">
				<label>Date</label>
				<input type="date" name="reservation_date" min="<?= date('Y-m-d') ?>" value="<?= e($date) ?>" required>
			</div>
			<div class="form-group">
				<label>Time Slot</label>
				<select name="time_slot" required>
					<option value="">-- Select --</option>
					<option value="morning" <?= $slot === 'morning' ? 'selected' : '' ?>>Morning</option>
					<option value="noon" <?= $slot === 'noon' ? 'selected' : '' ?>>Noon</option>
					<option value="afternoon" <?= $slot === 'afternoon' ? 'selected' : '' ?>>Afternoon</option>
				</select>
			</div>
		</div>

		<div class="form-group" id="address_group">
			<label>Delivery Address</label>
			<textarea name="delivery_address" rows="2"><?= e($address) ?></textarea>
			<div class="form-hint">Pre-filled from your profile. You can change it for this order.</div>
		</div>

		<div class="form-group">
			<label>Notes (optional)</label>
			<textarea name="notes" rows="2"><?= e($notes) ?></textarea>
		</div>

		<button type="submit" class="btn-inline"><i class="fa-solid fa-paper-plane"></i> Submit Reservation</button>
	</form>
</div>

<script>
	// Hide the address box when "Customer Pickup" is selected
	const typeSelect = document.getElementById('reservation_type');
	const addressGroup = document.getElementById('address_group');

	function toggleAddress() {
		addressGroup.style.display = typeSelect.value === 'delivery' ? 'block' : 'none';
	}

	typeSelect.addEventListener('change', toggleAddress);
	toggleAddress(); // run once when the page loads

	const qtyInput = document.getElementById('quantity');
	const price = <?= json_encode((float) $price) ?>;
	function updateTotal() {
		const q = parseInt(qtyInput.value) || 0;
		document.getElementById('total').textContent = '₱' + (q * price).toFixed(2);
	}
	qtyInput.addEventListener('input', updateTotal);
	updateTotal();
</script>

<?php require_once '../includes/footer.php'; ?>