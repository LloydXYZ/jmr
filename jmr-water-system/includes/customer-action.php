<?php
// includes/customer-action.php
// Handles Deactivate and Activate. No page design, it redirects.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("Location: customers.php");
	exit;
}

$id = (int) ($_POST['customer_id'] ?? 0);
$action = $_POST['action'] ?? '';
$return = $_POST['return'] ?? 'list';

$msg = 'error';

if ($action === 'deactivate' || $action === 'activate') {
	$newStatus = ($action === 'deactivate') ? 'inactive' : 'active';

	// Only touches users that are linked to a customer, so staff/admin accounts are never affected
	$stmt = $pdo->prepare(
		"UPDATE users u
         JOIN customers c ON c.user_id = u.user_id
         SET u.status = ?
         WHERE c.customer_id = ?"
	);
	$stmt->execute([$newStatus, $id]);

	// rowCount() is 0 if the status was already that value, so check the customer exists instead
	$check = $pdo->prepare("SELECT customer_id FROM customers WHERE customer_id = ?");
	$check->execute([$id]);
	if ($check->fetch()) {
		$msg = ($action === 'deactivate') ? 'deactivated' : 'activated';
	}
}

if ($return === 'view' && $msg !== 'error') {
	header("Location: customer-view.php?id=$id&msg=$msg");
} else {
	header("Location: customers.php?msg=$msg");
}
exit;