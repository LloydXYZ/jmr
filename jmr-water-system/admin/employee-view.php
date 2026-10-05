<?php
require_once '../config/database.php';
require_once '../includes/auth-check.php';
requireRole(['admin']);

$pageTitle = 'Employee Details';
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM employees WHERE employee_id = ?");

$stmt = $pdo->prepare(
	"SELECT * FROM payroll WHERE employee_id = ? ORDER BY payroll_date DESC, payroll_id DESC"
);
$stmt->execute([$id]);
$payrollHistory = $stmt->fetchAll();
$totalPaidToEmployee = 0;
foreach ($payrollHistory as $ph) {
	if ($ph['payment_status'] === 'paid') {
		$totalPaidToEmployee += $ph['amount'];
	}
}

$stmt->execute([$id]);
$emp = $stmt->fetch();

if (!$emp) {
	header("Location: employees.php?msg=error");
	exit;
}

$messages = [
	'updated' => ['success', 'Employee updated.'],
	'deactivated' => ['success', 'Employee deactivated.'],
	'activated' => ['success', 'Employee activated.'],
	'error' => ['danger', 'That action could not be completed.'],
];
$msg = $_GET['msg'] ?? '';

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/navbar.php';
?>

<h1 class="page-title"><?= e($emp['full_name']) ?></h1>

<?php if (isset($messages[$msg])): ?>
	<div class="alert alert-<?= $messages[$msg][0] ?>"><?= e($messages[$msg][1]) ?></div>
<?php endif; ?>

<div class="panel form-panel">
	<dl class="detail-list">
		<dt>Employee ID</dt>
		<dd><?= e(formatEmployeeId($emp['employee_id'])) ?></dd>

		<dt>Status</dt>
		<dd><?= accountBadge($emp['status']) ?></dd>

		<dt>Position</dt>
		<dd><?= e($emp['job_position']) ?></dd>

		<dt>Contact Number</dt>
		<dd><?= e($emp['contact_number']) ?></dd>

		<dt>Monthly Salary</dt>
		<dd>₱<?= number_format($emp['monthly_salary'], 2) ?></dd>

		<dt>Date Hired</dt>
		<dd><?= e(date('F d, Y', strtotime($emp['date_hired']))) ?></dd>
	</dl>

	<p style="margin-top:24px;">
		<a href="employees.php" class="btn-sm gray"><i class="fa-solid fa-arrow-left"></i> Back</a>
		<a href="employee-form.php?id=<?= (int) $emp['employee_id'] ?>" class="btn-sm warning">Edit</a>
	<form method="POST" action="employee-action.php" style="display:inline;"
		onsubmit="return confirm('<?= $emp['status'] === 'active' ? 'Deactivate this employee?' : 'Activate this employee?' ?>');">
		<input type="hidden" name="employee_id" value="<?= (int) $emp['employee_id'] ?>">
		<input type="hidden" name="action" value="<?= $emp['status'] === 'active' ? 'deactivate' : 'activate' ?>">
		<input type="hidden" name="return" value="view">
		<?php if ($emp['status'] === 'active'): ?>
			<button type="submit" class="btn-sm danger">Deactivate</button>
		<?php else: ?>
			<button type="submit" class="btn-sm success">Activate</button>
		<?php endif; ?>
	</form>
	</p>
</div>

<div class="panel">
	<h3>Payroll History</h3>
	<?php if (empty($payrollHistory)): ?>
		<p class="empty-text">No payroll recorded for this employee yet. <a href="payroll.php">Record payroll</a></p>
	<?php else: ?>
		<p>Total paid: <strong><?= e(peso($totalPaidToEmployee)) ?></strong></p>
		<div class="table-wrap">
			<table class="data-table">
				<thead>
					<tr>
						<th>ID</th>
						<th>Period</th>
						<th>Amount</th>
						<th>Status</th>
						<th>Payroll Date</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($payrollHistory as $ph): ?>
						<tr>
							<td><?= e(formatPayrollId($ph['payroll_id'])) ?></td>
							<td><?= e($ph['payroll_period']) ?></td>
							<td><?= e(peso($ph['amount'])) ?></td>
							<td><?= payrollBadge($ph['payment_status']) ?></td>
							<td><?= e(date('M d, Y', strtotime($ph['payroll_date']))) ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
<?php require_once '../includes/footer.php'; ?>