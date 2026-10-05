<?php
// includes/payment-action.php
// Handles Record Payment and Mark Unpaid. No page design, it redirects.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("Location: payments.php");
	exit;
}

$reservationId = (int) ($_POST['reservation_id'] ?? 0);
$action = $_POST['action'] ?? '';
$msg = 'error';

if ($action === 'record') {
	// Only confirmed (or later) reservations can be paid
	$stmt = $pdo->prepare(
		"SELECT total_amount FROM reservations
         WHERE reservation_id = ?
           AND status IN ('confirmed', 'scheduled', 'out_for_delivery', 'completed')"
	);
	$stmt->execute([$reservationId]);
	$res = $stmt->fetch();

	if ($res) {
		$existing = getPayment($pdo, $reservationId);

		// Do nothing if it is already paid (stops double recording)
		if (!$existing || $existing['payment_status'] !== 'paid') {
			$stmt = $pdo->prepare(
				"INSERT INTO payments (reservation_id, amount, payment_method, payment_status, date_paid, received_by)
                 VALUES (?, ?, 'cod', 'paid', NOW(), ?)
                 ON DUPLICATE KEY UPDATE
                    amount = VALUES(amount), payment_status = 'paid',
                    date_paid = NOW(), received_by = VALUES(received_by)"
			);
			$stmt->execute([$reservationId, $res['total_amount'], $_SESSION['user_id']]);
			$msg = 'recorded';
			$ref = formatReservationId($reservationId);
			notifyReservationCustomer(
				$pdo,
				$reservationId,
				"Payment of " . peso($res['total_amount']) . " was recorded for $ref. Thank you!",
				'payment'
			);
			logActivity($pdo, "Recorded " . peso($res['total_amount']) . " payment for $ref");
		}
	}

} elseif ($action === 'unpaid' && $_SESSION['role'] === 'admin') {
	// Admin only: undo a payment recorded by mistake
	$stmt = $pdo->prepare(
		"UPDATE payments
         SET payment_status = 'unpaid', date_paid = NULL, received_by = NULL
         WHERE reservation_id = ? AND payment_status = 'paid'"
	);
	$stmt->execute([$reservationId]);
	if ($stmt->rowCount() === 1) {
		$msg = 'unpaid';
		logActivity($pdo, "Marked the payment of " . formatReservationId($reservationId) . " as unpaid");
	}
}

header("Location: payments.php?msg=$msg");
exit;