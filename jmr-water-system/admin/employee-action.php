<?php
require_once '../config/database.php';
require_once '../includes/auth-check.php';
requireRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("Location: employees.php");
	exit;
}

$id = (int) ($_POST['employee_id'] ?? 0);
$action = $_POST['action'] ?? '';
$return = $_POST['return'] ?? 'list';

$msg = 'error';

if ($action === 'deactivate' || $action === 'activate') {
	$newStatus = ($action === 'deactivate') ? 'inactive' : 'active';

	$stmt = $pdo->prepare("UPDATE employees SET status = ? WHERE employee_id = ?");
	$stmt->execute([$newStatus, $id]);

	// rowCount() is 0 if the status didn't change, so check the employee exists instead
	$check = $pdo->prepare("SELECT employee_id FROM employees WHERE employee_id = ?");
	$check->execute([$id]);
	if ($check->fetch()) {
		$msg = ($action === 'deactivate') ? 'deactivated' : 'activated';
	}
}

if ($return === 'view' && $msg !== 'error') {
	header("Location: employee-view.php?id=$id&msg=$msg");
} else {
	header("Location: employees.php?msg=$msg");
}
exit;