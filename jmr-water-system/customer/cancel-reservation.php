<?php
require_once '../config/database.php';
require_once '../includes/auth-check.php';

requireRole(['customer']);

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: my-reservations.php");
    exit;
}

$reservation_id = (int) ($_POST['reservation_id'] ?? 0);

// One query does all the safety checks:
//  - the reservation must belong to the logged-in customer
//  - its status must still allow cancelling
$stmt = $pdo->prepare(
    "UPDATE reservations r
     JOIN customers c ON r.customer_id = c.customer_id
     SET r.status = 'cancelled',
         r.cancelled_at = NOW(),
         r.cancelled_by = ?
     WHERE r.reservation_id = ?
       AND c.user_id = ?
       AND r.status IN ('pending', 'confirmed', 'scheduled')"
);
$stmt->execute([$_SESSION['user_id'], $reservation_id, $_SESSION['user_id']]);

if ($stmt->rowCount() === 1) {
    // If the reservation had a delivery, cancel that too
    $stmt = $pdo->prepare(
        "UPDATE deliveries SET status = 'cancelled'
         WHERE reservation_id = ? AND status IN ('scheduled', 'out_for_delivery')"
    );
    $stmt->execute([$reservation_id]);

    $ref = formatReservationId($reservation_id);
    notifyUser($pdo, $_SESSION['user_id'], "Your reservation $ref was cancelled.", 'reservation');
    notifyStaff($pdo, "Reservation $ref was cancelled by the customer {$_SESSION['full_name']}.", 'reservation');
    logActivity($pdo, "Cancelled reservation $ref");

    header("Location: my-reservations.php?cancelled=1");
} else {
    header("Location: my-reservations.php?error=1");
}
exit;