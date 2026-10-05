<?php
// includes/delivery-action.php
// Handles Start, Deliver, and Cancel. No page design, it redirects.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("Location: deliveries.php");
	exit;
}

$id = (int) ($_POST['delivery_id'] ?? 0);
$action = $_POST['action'] ?? '';
$view = $_POST['view'] ?? 'today';
if (!in_array($view, ['today', 'upcoming', 'all'])) {
	$view = 'today';
}

// Which reservation does this delivery belong to?
$stmt = $pdo->prepare("SELECT reservation_id FROM deliveries WHERE delivery_id = ?");
$stmt->execute([$id]);
$reservationId = (int) $stmt->fetchColumn();
$ref = formatReservationId($reservationId);

$msg = 'error';
$success = false;

try {
	// The delivery, reservation, and stock must change together
	$pdo->beginTransaction();

	if ($action === 'start') {
		$stmt = $pdo->prepare(
			"UPDATE deliveries SET status = 'out_for_delivery'
             WHERE delivery_id = ? AND status = 'scheduled'"
		);
		$stmt->execute([$id]);

		if ($stmt->rowCount() === 1) {
			$stmt = $pdo->prepare(
				"UPDATE reservations SET status = 'out_for_delivery'
                 WHERE reservation_id = ? AND status = 'scheduled'"
			);
			$stmt->execute([$reservationId]);
			$msg = 'started';
			$success = true;
		}

	} elseif ($action === 'deliver') {
		$stmt = $pdo->prepare(
			"UPDATE deliveries SET status = 'delivered', delivered_at = NOW()
             WHERE delivery_id = ? AND status = 'out_for_delivery'"
		);
		$stmt->execute([$id]);

		if ($stmt->rowCount() === 1) {
			$stmt = $pdo->prepare(
				"UPDATE reservations SET status = 'completed'
                 WHERE reservation_id = ? AND status IN ('scheduled', 'out_for_delivery')"
			);
			$stmt->execute([$reservationId]);

			// Take the delivered gallons out of stock
			if (deductStockForReservation($pdo, $reservationId, $_SESSION['user_id'])) {
				$msg = 'delivered';
				$success = true;
			} else {
				$msg = 'nostock';
			}
		}

	} elseif ($action === 'cancel') {
		$stmt = $pdo->prepare(
			"UPDATE deliveries SET status = 'cancelled'
             WHERE delivery_id = ? AND status IN ('scheduled', 'out_for_delivery')"
		);
		$stmt->execute([$id]);

		if ($stmt->rowCount() === 1) {
			// Back to "confirmed" so it can be scheduled again
			$stmt = $pdo->prepare(
				"UPDATE reservations SET status = 'confirmed'
                 WHERE reservation_id = ? AND status IN ('scheduled', 'out_for_delivery')"
			);
			$stmt->execute([$reservationId]);
			$msg = 'cancelled';
			$success = true;
		}
	}

	if ($success) {
		$pdo->commit();

		// Notifications and log (only after everything was saved)
		if ($action === 'start') {
			notifyReservationCustomer($pdo, $reservationId, "Your order $ref is out for delivery.", 'delivery');
			logActivity($pdo, "Marked the delivery of $ref as out for delivery");
		} elseif ($action === 'deliver') {
			notifyReservationCustomer($pdo, $reservationId, "Your order $ref was delivered. Thank you!", 'delivery');
			logActivity($pdo, "Marked $ref as delivered");
			checkLowStock($pdo);
		} elseif ($action === 'cancel') {
			notifyReservationCustomer($pdo, $reservationId, "The delivery for $ref was cancelled. It will be rescheduled.", 'delivery');
			logActivity($pdo, "Cancelled the delivery of $ref");
		}
	} else {
		$pdo->rollBack();
	}
} catch (Exception $ex) {
	if ($pdo->inTransaction()) {
		$pdo->rollBack();
	}
	$msg = 'error';
}

header("Location: deliveries.php?view=$view&msg=$msg");
exit;