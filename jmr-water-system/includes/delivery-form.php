<?php
// includes/delivery-form.php

$deliveryId = (int) ($_GET['id'] ?? 0);
$isEdit = $deliveryId > 0;
$pageTitle = $isEdit ? 'Edit Delivery' : 'Schedule Delivery';

// Most deliveries one staff member may handle in the same time slot on the same day.
// Change this number if the station needs more or fewer.
$maxPerSlot = 3;

if ($isEdit) {
	// Load the delivery being edited (only "scheduled" ones can be edited)
	$stmt = $pdo->prepare(
		"SELECT d.*, r.quantity, r.unit_price, r.total_amount, r.delivery_address, u.full_name AS customer_name
         FROM deliveries d
         JOIN reservations r ON d.reservation_id = r.reservation_id
         JOIN customers c ON r.customer_id = c.customer_id
         JOIN users u ON c.user_id = u.user_id
         WHERE d.delivery_id = ?"
	);
	$stmt->execute([$deliveryId]);
	$row = $stmt->fetch();

	if (!$row || $row['status'] !== 'scheduled') {
		header("Location: deliveries.php?view=all&msg=error");
		exit;
	}

	$reservationId = $row['reservation_id'];
	$employeeId = $row['employee_id'];
	$date = $row['delivery_date'];
	$slot = $row['time_slot'];
	$originalDate = $row['delivery_date'];
	$currentEmployee = $row['employee_id'];
} else {
	// Load the reservation being scheduled (must be a confirmed delivery)
	$reservationId = (int) ($_GET['reservation_id'] ?? 0);

	$stmt = $pdo->prepare(
		"SELECT r.*, u.full_name AS customer_name
         FROM reservations r
         JOIN customers c ON r.customer_id = c.customer_id
         JOIN users u ON c.user_id = u.user_id
         WHERE r.reservation_id = ?
           AND r.reservation_type = 'delivery'
           AND r.status = 'confirmed'"
	);
	$stmt->execute([$reservationId]);
	$row = $stmt->fetch();

	if (!$row) {
		header("Location: deliveries.php?view=all&msg=error");
		exit;
	}

	$employeeId = '';
	$date = $row['reservation_date'];
	$slot = $row['time_slot'];
	$originalDate = null;
	$currentEmployee = 0;
}

// Active Delivery Staff (plus the currently assigned one when editing)
$stmt = $pdo->prepare(
	"SELECT employee_id, full_name FROM employees
     WHERE job_position = 'Delivery Staff' AND (status = 'active' OR employee_id = ?)
     ORDER BY full_name"
);
$stmt->execute([$currentEmployee]);
$staffList = $stmt->fetchAll();
$staffIds = array_column($staffList, 'employee_id');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$employeeId = (int) ($_POST['employee_id'] ?? 0);
	$date = $_POST['delivery_date'] ?? '';
	$slot = $_POST['time_slot'] ?? '';

	if (!in_array($employeeId, $staffIds)) {
		$errors[] = "Please choose a delivery staff.";
	}
	if (!in_array($slot, ['morning', 'noon', 'afternoon'])) {
		$errors[] = "Please choose a time slot.";
	}

	$dateObj = DateTime::createFromFormat('Y-m-d', $date);
	if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
		$errors[] = "Please choose a valid date.";
	} elseif ($date < date('Y-m-d') && $date !== $originalDate) {
		$errors[] = "Delivery date cannot be in the past.";
	}

	// Is this staff already full in that slot?
	if (empty($errors)) {
		$stmt = $pdo->prepare(
			"SELECT COUNT(*) FROM deliveries
             WHERE employee_id = ? AND delivery_date = ? AND time_slot = ?
               AND status IN ('scheduled', 'out_for_delivery')
               AND delivery_id <> ?"
		);
		$stmt->execute([$employeeId, $date, $slot, $deliveryId]);
		if ($stmt->fetchColumn() >= $maxPerSlot) {
			$errors[] = "That staff already has $maxPerSlot deliveries in that time slot. Choose another staff, date, or time slot.";
		}
	}

	if (empty($errors)) {
		try {
			$pdo->beginTransaction();

			if ($isEdit) {
				$stmt = $pdo->prepare(
					"UPDATE deliveries SET employee_id = ?, delivery_date = ?, time_slot = ?
                     WHERE delivery_id = ? AND status = 'scheduled'"
				);
				$stmt->execute([$employeeId, $date, $slot, $deliveryId]);

				// Keep the reservation's date and time slot the same as the delivery
				$stmt = $pdo->prepare(
					"UPDATE reservations SET reservation_date = ?, time_slot = ? WHERE reservation_id = ?"
				);
				$stmt->execute([$date, $slot, $reservationId]);

				$pdo->commit();

				$ref = formatReservationId($reservationId);
				notifyReservationCustomer(
					$pdo,
					$reservationId,
					"Your delivery for $ref was rescheduled to " . date('M d, Y', strtotime($date)) . " (" . ucfirst($slot) . ").",
					'delivery'
				);
				logActivity($pdo, "Updated the delivery schedule of $ref");

				header("Location: deliveries.php?view=all&msg=updated");
				exit;
			}

			// New delivery: make sure there is no active one already (no duplicates)
			$stmt = $pdo->prepare(
				"SELECT delivery_id FROM deliveries WHERE reservation_id = ? AND status <> 'cancelled'"
			);
			$stmt->execute([$reservationId]);

			if ($stmt->fetch()) {
				$pdo->rollBack();
				$errors[] = "This reservation already has an active delivery.";
			} else {
				$stmt = $pdo->prepare(
					"INSERT INTO deliveries (reservation_id, employee_id, delivery_date, time_slot, created_by)
                     VALUES (?, ?, ?, ?, ?)"
				);
				$stmt->execute([$reservationId, $employeeId, $date, $slot, $_SESSION['user_id']]);

				// Reservation becomes "scheduled" (only if it is still confirmed)
				$stmt = $pdo->prepare(
					"UPDATE reservations
                     SET status = 'scheduled', reservation_date = ?, time_slot = ?
                     WHERE reservation_id = ? AND status = 'confirmed'"
				);
				$stmt->execute([$date, $slot, $reservationId]);

				if ($stmt->rowCount() !== 1) {
					$pdo->rollBack();
					$errors[] = "This reservation is no longer confirmed, so it cannot be scheduled.";
				} else {
					$pdo->commit();

					$ref = formatReservationId($reservationId);
					notifyReservationCustomer(
						$pdo,
						$reservationId,
						"Your delivery for $ref is scheduled on " . date('M d, Y', strtotime($date)) . " (" . ucfirst($slot) . ").",
						'delivery'
					);
					logActivity($pdo, "Scheduled a delivery for $ref on " . date('M d, Y', strtotime($date)));

					header("Location: deliveries.php?view=all&msg=scheduled");
					exit;
				}
			}
		} catch (PDOException $ex) {
			if ($pdo->inTransaction()) {
				$pdo->rollBack();
			}
			$errors[] = "Something went wrong. Please try again.";
		}
	}
}

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/navbar.php';
?>

<h1 class="page-title"><?= e($pageTitle) ?>: <?= e(formatReservationId($reservationId)) ?></h1>

<?php if (!empty($errors)): ?>
	<div class="alert alert-danger">
		<?php foreach ($errors as $err): ?>
			<div><?= e($err) ?></div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<div class="panel form-panel">
	<dl class="detail-list" style="margin-bottom:20px;">
		<dt>Customer</dt>
		<dd><?= e($row['customer_name']) ?></dd>
		<dt>Quantity</dt>
		<dd><?= e($row['quantity']) ?> x 20-liter gallon(s)</dd>
		<dt>Total Amount</dt>
		<dd><strong>
				<?= e(peso($row['total_amount'])) ?>
			</strong> (Cash on Delivery)</dd>
		<dt>Delivery Address</dt>
		<dd><?= nl2br(e($row['delivery_address'])) ?></dd>
	</dl>

	<?php if (empty($staffList)): ?>
		<div class="alert alert-warning">
			There is no active Delivery Staff. Ask the administrator to add one under Employees first.
		</div>
		<a href="deliveries.php" class="btn-sm gray">Back</a>
	<?php else: ?>
		<form method="POST" action="">
			<div class="form-group">
				<label>Delivery Staff</label>
				<select name="employee_id" required>
					<option value="">-- Select --</option>
					<?php foreach ($staffList as $s): ?>
						<option value="<?= (int) $s['employee_id'] ?>" <?= (int) $employeeId === (int) $s['employee_id'] ? 'selected' : '' ?>>
							<?= e($s['full_name']) ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="form-row">
				<div class="form-group">
					<label>Delivery Date</label>
					<input type="date" name="delivery_date" min="<?= date('Y-m-d') ?>" value="<?= e($date) ?>" required>
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
			<div class="form-hint" style="margin-bottom:16px;">
				The date and time slot start with what the customer requested. Changing them here also updates the reservation.
			</div>

			<button type="submit" class="btn-inline"><i class="fa-solid fa-floppy-disk"></i> Save Delivery</button>
			<a href="deliveries.php" class="btn-sm gray" style="margin-left:8px;">Cancel</a>
		</form>
	<?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>