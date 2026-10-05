<?php
// includes/reservation-action.php
// Handles Confirm, Complete, and Cancel. No page design, it redirects.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("Location: reservations.php");
	exit;
}

$id = (int) ($_POST['reservation_id'] ?? 0);
$action = $_POST['action'] ?? '';
$return = $_POST['return'] ?? 'list';
$ref = formatReservationId($id);

$msg = 'error';

try {
	if ($action === 'confirm') {
		$stmt = $pdo->prepare("SELECT quantity FROM reservations WHERE reservation_id = ? AND status = 'pending'");
		$stmt->execute([$id]);
		$res = $stmt->fetch();

		if ($res) {
			// Confirming reserves gallons, so there must be enough available stock
			$inv = getInventorySummary($pdo);
			if ((int) $res['quantity'] > $inv['available']) {
				$msg = 'nostock';
			} else {
				$stmt = $pdo->prepare(
					"UPDATE reservations SET status = 'confirmed'
                     WHERE reservation_id = ? AND status = 'pending'"
				);
				$stmt->execute([$id]);
				if ($stmt->rowCount() === 1) {
					$msg = 'confirmed';
					notifyReservationCustomer($pdo, $id, "Your reservation $ref was confirmed.", 'reservation');
					logActivity($pdo, "Confirmed reservation $ref");
					checkLowStock($pdo);
				}
			}
		}

	} elseif ($action === 'complete') {
		// Status change, stock deduction, and delivery update succeed or fail together
		$pdo->beginTransaction();

		$stmt = $pdo->prepare(
			"UPDATE reservations SET status = 'completed'
             WHERE reservation_id = ?
               AND status IN ('confirmed', 'scheduled', 'out_for_delivery')"
		);
		$stmt->execute([$id]);
		$updated = ($stmt->rowCount() === 1);

		if ($updated && deductStockForReservation($pdo, $id, $_SESSION['user_id'])) {
			$stmt = $pdo->prepare(
				"UPDATE deliveries SET status = 'delivered', delivered_at = NOW()
                 WHERE reservation_id = ? AND status IN ('scheduled', 'out_for_delivery')"
			);
			$stmt->execute([$id]);
			$pdo->commit();
			$msg = 'completed';

			notifyReservationCustomer($pdo, $id, "Your reservation $ref is completed. Thank you!", 'reservation');
			logActivity($pdo, "Marked reservation $ref as completed");
			checkLowStock($pdo);
		} else {
			$pdo->rollBack();
			$msg = $updated ? 'nostock' : 'error';
		}

	} elseif ($action === 'cancel') {
		$stmt = $pdo->prepare(
			"UPDATE reservations
             SET status = 'cancelled', cancelled_at = NOW(), cancelled_by = ?
             WHERE reservation_id = ?
               AND status IN ('pending', 'confirmed', 'scheduled')"
		);
		$stmt->execute([$_SESSION['user_id'], $id]);
		if ($stmt->rowCount() === 1) {
			$msg = 'cancelled';
			// If a delivery exists, cancel it too
			$stmt = $pdo->prepare(
				"UPDATE deliveries SET status = 'cancelled'
                 WHERE reservation_id = ? AND status IN ('scheduled', 'out_for_delivery')"
			);
			$stmt->execute([$id]);

			notifyReservationCustomer($pdo, $id, "Your reservation $ref was cancelled by the station.", 'reservation');
			logActivity($pdo, "Cancelled reservation $ref");
		}
	}
} catch (Exception $ex) {
	if ($pdo->inTransaction()) {
		$pdo->rollBack();
	}
	$msg = 'error';
}

if ($return === 'view') {
	header("Location: reservation-view.php?id=$id&msg=$msg");
} else {
	header("Location: reservations.php?msg=$msg");
}
exit;