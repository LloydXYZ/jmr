<?php
require_once '../config/database.php';
require_once '../includes/auth-check.php';
requireRole(['admin']);

$pageTitle = 'Payroll';
$errors = [];

$isDate = function ($d) {
	$o = DateTime::createFromFormat('Y-m-d', $d);
	return $o && $o->format('Y-m-d') === $d;
};

// Active employees for the form
$employees = $pdo->query(
	"SELECT employee_id, full_name, job_position, monthly_salary
     FROM employees WHERE status = 'active' ORDER BY full_name"
)->fetchAll();
$employeeIds = array_map('intval', array_column($employees, 'employee_id'));

// Form defaults
$f_emp = 0;
$f_start = date('Y-m-01');
$f_end = date('Y-m-t');
$f_amount = '';
$f_date = date('Y-m-d');
$f_status = 'paid';

// ---- Record payroll ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$f_emp = (int) ($_POST['employee_id'] ?? 0);
	$f_start = $_POST['period_start'] ?? '';
	$f_end = $_POST['period_end'] ?? '';
	$f_amount = trim($_POST['amount'] ?? '');
	$f_date = $_POST['payroll_date'] ?? '';
	$f_status = $_POST['payment_status'] ?? '';

	if (!in_array($f_emp, $employeeIds, true)) {
		$errors[] = "Please choose an active employee.";
	}
	if (!$isDate($f_start) || !$isDate($f_end)) {
		$errors[] = "Please choose a valid payroll period (start and end date).";
	} elseif ($f_end < $f_start) {
		$errors[] = "The period end date cannot be before the start date.";
	}

	$amount = filter_var($f_amount, FILTER_VALIDATE_FLOAT);
	if ($amount === false || $amount <= 0 || $amount > 999999.99) {
		$errors[] = "Amount must be a number greater than 0 and not more than 999,999.99.";
	}

	if (!$isDate($f_date)) {
		$errors[] = "Please choose a valid payroll date.";
	}
	if (!in_array($f_status, ['paid', 'unpaid'], true)) {
		$errors[] = "Please choose Paid or Unpaid.";
	} elseif ($f_status === 'paid' && $isDate($f_date) && $f_date > date('Y-m-d')) {
		$errors[] = "A paid payroll cannot have a future date. Choose Unpaid or change the date.";
	}

	if (empty($errors)) {
		$period = date('M d, Y', strtotime($f_start)) . ' - ' . date('M d, Y', strtotime($f_end));

		// No duplicate record for the same employee and period
		$stmt = $pdo->prepare("SELECT payroll_id FROM payroll WHERE employee_id = ? AND payroll_period = ?");
		$stmt->execute([$f_emp, $period]);
		if ($stmt->fetch()) {
			$errors[] = "Payroll for that employee and period is already recorded.";
		}
	}

	if (empty($errors)) {
		$stmt = $pdo->prepare(
			"INSERT INTO payroll (employee_id, payroll_period, amount, payroll_date, payment_status, created_by)
             VALUES (?, ?, ?, ?, ?, ?)"
		);
		$stmt->execute([$f_emp, $period, $amount, $f_date, $f_status, $_SESSION['user_id']]);
		logActivity($pdo, "Recorded " . $f_status . " payroll of " . peso($amount) . " for " . formatEmployeeId($f_emp));
		header("Location: payroll.php?month=" . substr($f_date, 0, 7) . "&msg=recorded");
		exit;
	}
}

// ---- Filters for the history table ----
// First visit: current month. If the month box is cleared: all months.
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
	$month = '';
}

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$sql = "SELECT p.*, e.full_name, e.job_position
        FROM payroll p
        JOIN employees e ON p.employee_id = e.employee_id
        WHERE 1=1";
$params = [];

if ($month !== '') {
	$monthStart = $month . '-01';
	$monthEnd = date('Y-m-t', strtotime($monthStart));
	$sql .= " AND p.payroll_date BETWEEN ? AND ?";
	$params[] = $monthStart;
	$params[] = $monthEnd;
}
if ($search !== '') {
	$sql .= " AND (e.full_name LIKE ? OR e.employee_id = ?)";
	$params[] = "%$search%";
	$params[] = (int) preg_replace('/\D/', '', $search);
}
if (in_array($statusFilter, ['paid', 'unpaid'], true)) {
	$sql .= " AND p.payment_status = ?";
	$params[] = $statusFilter;
}
$sql .= " ORDER BY p.payroll_date DESC, p.payroll_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

$totalPaid = 0;
$totalUnpaid = 0;
foreach ($records as $rec) {
	if ($rec['payment_status'] === 'paid') {
		$totalPaid += $rec['amount'];
	} else {
		$totalUnpaid += $rec['amount'];
	}
}

// What the station pays in a full month if everyone is paid their salary
$monthlyBudget = array_sum(array_column($employees, 'monthly_salary'));

$messages = [
	'recorded' => ['success', 'Payroll recorded.'],
	'paid' => ['success', 'Payroll marked as paid.'],
	'deleted' => ['success', 'Unpaid payroll record deleted.'],
	'error' => ['danger', 'That action could not be completed.'],
];
$msg = $_GET['msg'] ?? '';

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/navbar.php';
?>

<h1 class="page-title">Payroll</h1>

<?php if (isset($messages[$msg])): ?>
	<div class="alert alert-<?= $messages[$msg][0] ?>"><?= e($messages[$msg][1]) ?></div>
<?php endif; ?>

<div class="card-grid">
	<div class="stat-card">
		<div class="icon green"><i class="fa-solid fa-wallet"></i></div>
		<div>
			<div class="label">Total Payroll
				Paid<?= $month !== '' ? ' (' . e(date('F Y', strtotime($month . '-01'))) . ')' : ' (all)' ?></div>
			<div class="value"><?= e(peso($totalPaid)) ?></div>
		</div>
	</div>
	<div class="stat-card">
		<div class="icon orange"><i class="fa-solid fa-hourglass-half"></i></div>
		<div>
			<div class="label">Unpaid Payroll</div>
			<div class="value"><?= e(peso($totalUnpaid)) ?></div>
		</div>
	</div>
	<div class="stat-card">
		<div class="icon"><i class="fa-solid fa-list"></i></div>
		<div>
			<div class="label">Records Shown</div>
			<div class="value"><?= count($records) ?></div>
		</div>
	</div>
	<div class="stat-card">
		<div class="icon"><i class="fa-solid fa-id-badge"></i></div>
		<div>
			<div class="label">Monthly Salary Total (active staff)</div>
			<div class="value"><?= e(peso($monthlyBudget)) ?></div>
		</div>
	</div>
</div>

<div class="panel form-panel">
	<h3>Record Payroll</h3>

	<?php if (!empty($errors)): ?>
		<div class="alert alert-danger">
			<?php foreach ($errors as $err): ?>
				<div><?= e($err) ?></div><?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if (empty($employees)): ?>
		<div class="alert alert-warning">There are no active employees. <a href="employees.php">Add one under Employees</a>
			first.</div>
	<?php else: ?>
		<form method="POST" action="">
			<div class="form-row">
				<div class="form-group">
					<label>Employee</label>
					<select name="employee_id" id="employee_id" required>
						<option value="">-- Select --</option>
						<?php foreach ($employees as $emp): ?>
							<option value="<?= (int) $emp['employee_id'] ?>" data-salary="<?= e($emp['monthly_salary']) ?>"
								<?= $f_emp === (int) $emp['employee_id'] ? 'selected' : '' ?>>
								<?= e($emp['full_name']) ?> (<?= e($emp['job_position']) ?>)
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="form-group">
					<label>Amount (₱)</label>
					<input type="number" name="amount" id="amount" step="0.01" min="0.01" value="<?= e($f_amount) ?>" required>
					<div class="form-hint">Filled with the monthly salary. Change it for a half-month or deductions.</div>
				</div>
			</div>

			<div class="form-row">
				<div class="form-group">
					<label>Period Start</label>
					<input type="date" name="period_start" value="<?= e($f_start) ?>" required>
				</div>
				<div class="form-group">
					<label>Period End</label>
					<input type="date" name="period_end" value="<?= e($f_end) ?>" required>
				</div>
			</div>

			<div class="form-row">
				<div class="form-group">
					<label>Payroll Date</label>
					<input type="date" name="payroll_date" value="<?= e($f_date) ?>" required>
				</div>
				<div class="form-group">
					<label>Payment Status</label>
					<select name="payment_status" required>
						<option value="paid" <?= $f_status === 'paid' ? 'selected' : '' ?>>Paid</option>
						<option value="unpaid" <?= $f_status === 'unpaid' ? 'selected' : '' ?>>Unpaid (pay later)</option>
					</select>
				</div>
			</div>

			<button type="submit" class="btn-inline"><i class="fa-solid fa-floppy-disk"></i> Save Payroll</button>
		</form>

		<script>
			// Fill the amount with the employee's monthly salary when an employee is chosen
			document.getElementById('employee_id').addEventListener('change', function () {
				var opt = this.options[this.selectedIndex];
				var salary = opt.getAttribute('data-salary');
				if (salary) { document.getElementById('amount').value = parseFloat(salary).toFixed(2); }
			});
		</script>
	<?php endif; ?>
</div>

<div class="panel">
	<h3>Payroll History</h3>
	<form method="GET" class="filter-bar no-print">
		<input type="month" name="month" value="<?= e($month) ?>">
		<input type="text" name="search" placeholder="Employee name or EMP-001" value="<?= e($search) ?>">
		<select name="status">
			<option value="">All statuses</option>
			<option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
			<option value="unpaid" <?= $statusFilter === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
		</select>
		<button type="submit" class="btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
		<a href="payroll.php" class="btn-sm gray">Reset</a>
		<button type="button" class="btn-sm gray" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
	</form>
	<p class="form-hint" style="margin-bottom:12px;">
		The month filter uses the payroll date. Clear the month box and click Filter to see every month.
	</p>

	<?php if (empty($records)): ?>
		<p class="empty-text">No payroll records found.</p>
	<?php else: ?>
		<div class="table-wrap">
			<table class="data-table">
				<thead>
					<tr>
						<th>ID</th>
						<th>Employee</th>
						<th>Period</th>
						<th>Amount</th>
						<th>Status</th>
						<th>Payroll Date</th>
						<th class="no-print">Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($records as $rec): ?>
						<tr>
							<td><?= e(formatPayrollId($rec['payroll_id'])) ?></td>
							<td>
								<a href="employee-view.php?id=<?= (int) $rec['employee_id'] ?>"><?= e($rec['full_name']) ?></a>
								<br><small><?= e($rec['job_position']) ?></small>
							</td>
							<td><?= e($rec['payroll_period']) ?></td>
							<td><?= e(peso($rec['amount'])) ?></td>
							<td><?= payrollBadge($rec['payment_status']) ?></td>
							<td><?= e(date('M d, Y', strtotime($rec['payroll_date']))) ?></td>
							<td class="actions-cell no-print">
								<?php if ($rec['payment_status'] === 'unpaid'): ?>
									<form method="POST" action="payroll-action.php" style="display:inline;"
										onsubmit="return confirm('Mark this payroll as paid?');">
										<input type="hidden" name="payroll_id" value="<?= (int) $rec['payroll_id'] ?>">
										<input type="hidden" name="action" value="pay">
										<input type="hidden" name="month" value="<?= e($month) ?>">
										<button type="submit" class="btn-sm success">Mark Paid</button>
									</form>
									<form method="POST" action="payroll-action.php" style="display:inline;"
										onsubmit="return confirm('Delete this unpaid payroll record?');">
										<input type="hidden" name="payroll_id" value="<?= (int) $rec['payroll_id'] ?>">
										<input type="hidden" name="action" value="delete">
										<input type="hidden" name="month" value="<?= e($month) ?>">
										<button type="submit" class="btn-sm danger">Delete</button>
									</form>
								<?php else: ?>
									<span class="empty-text">—</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr>
						<th colspan="3">Total (shown)</th>
						<th colspan="4">
							Paid <?= e(peso($totalPaid)) ?> &nbsp;|&nbsp; Unpaid <?= e(peso($totalUnpaid)) ?>
						</th>
					</tr>
				</tfoot>
			</table>
		</div>
	<?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>