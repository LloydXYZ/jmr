<?php
require_once '../config/database.php';
require_once '../includes/auth-check.php';
requireRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("Location: payroll.php");
	exit;
}

$id = (int) ($_POST['payroll_id'] ?? 0);
$action = $_POST['action'] ?? '';
$month = $_POST['month'] ?? '';
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
	$month = '';
}

$msg = 'error';

if ($action === 'pay') {
	// Only an unpaid record can be marked as paid
	$stmt = $pdo->prepare(
		"UPDATE payroll SET payment_status = 'paid' WHERE payroll_id = ? AND payment_status = 'unpaid'"
	);
	$stmt->execute([$id]);
	if ($stmt->rowCount() === 1) {
		$msg = 'paid';
		logActivity($pdo, "Marked payroll " . formatPayrollId($id) . " as paid");
	}

} elseif ($action === 'delete') {
	// Only an unpaid record can be deleted. Paid records stay as history.
	$stmt = $pdo->prepare("DELETE FROM payroll WHERE payroll_id = ? AND payment_status = 'unpaid'");
	$stmt->execute([$id]);
	if ($stmt->rowCount() === 1) {
		$msg = 'deleted';
		logActivity($pdo, "Deleted unpaid payroll record " . formatPayrollId($id));
	}
}

header("Location: payroll.php?month=$month&msg=$msg");
exit;