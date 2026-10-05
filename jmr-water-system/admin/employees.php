<?php
require_once '../config/database.php';
require_once '../includes/auth-check.php';
requireRole(['admin']);

$pageTitle = 'Employees';

$search = trim($_GET['search'] ?? '');
$positionFilter = $_GET['position'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$sql = "SELECT * FROM employees WHERE 1=1";
$params = [];

// Search by name, contact number, or employee number (EMP-003 or 3)
if ($search !== '') {
	$sql .= " AND (full_name LIKE ? OR contact_number LIKE ? OR employee_id = ?)";
	$params[] = "%$search%";
	$params[] = "%$search%";
	$params[] = (int) preg_replace('/\D/', '', $search);
}

if (in_array($positionFilter, employeePositions())) {
	$sql .= " AND job_position = ?";
	$params[] = $positionFilter;
}

if (in_array($statusFilter, ['active', 'inactive'])) {
	$sql .= " AND status = ?";
	$params[] = $statusFilter;
}

$sql .= " ORDER BY full_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();

$messages = [
	'added' => ['success', 'Employee added.'],
	'deactivated' => ['success', 'Employee deactivated.'],
	'activated' => ['success', 'Employee activated.'],
	'error' => ['danger', 'That action could not be completed.'],
];
$msg = $_GET['msg'] ?? '';

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/navbar.php';
?>

<h1 class="page-title">Employees</h1>

<?php if (isset($messages[$msg])): ?>
	<div class="alert alert-<?= $messages[$msg][0] ?>"><?= e($messages[$msg][1]) ?></div>
<?php endif; ?>

<div class="panel">
	<form method="GET" class="filter-bar">
		<input type="text" name="search" placeholder="Search name, contact, or EMP-001" value="<?= e($search) ?>">
		<select name="position">
			<option value="">All positions</option>
			<?php foreach (employeePositions() as $p): ?>
				<option value="<?= e($p) ?>" <?= $positionFilter === $p ? 'selected' : '' ?>><?= e($p) ?></option>
			<?php endforeach; ?>
		</select>
		<select name="status">
			<option value="">All statuses</option>
			<option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
			<option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
		</select>
		<button type="submit" class="btn-sm"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
		<a href="employees.php" class="btn-sm gray">Reset</a>
		<a href="employee-form.php" class="btn-sm success"><i class="fa-solid fa-plus"></i> Add Employee</a>
	</form>

	<?php if (empty($employees)): ?>
		<p class="empty-text">No employees found.</p>
	<?php else: ?>
		<div class="table-wrap">
			<table class="data-table">
				<thead>
					<tr>
						<th>ID</th>
						<th>Name</th>
						<th>Position</th>
						<th>Contact</th>
						<th>Monthly Salary</th>
						<th>Date Hired</th>
						<th>Status</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($employees as $emp): ?>
						<tr>
							<td><?= e(formatEmployeeId($emp['employee_id'])) ?></td>
							<td><?= e($emp['full_name']) ?></td>
							<td><?= e($emp['job_position']) ?></td>
							<td><?= e($emp['contact_number']) ?></td>
							<td>₱<?= number_format($emp['monthly_salary'], 2) ?></td>
							<td><?= e(date('M d, Y', strtotime($emp['date_hired']))) ?></td>
							<td><?= accountBadge($emp['status']) ?></td>
							<td class="actions-cell">
								<a href="employee-view.php?id=<?= (int) $emp['employee_id'] ?>" class="btn-sm">View</a>
								<a href="employee-form.php?id=<?= (int) $emp['employee_id'] ?>" class="btn-sm warning">Edit</a>
								<form method="POST" action="employee-action.php" style="display:inline;"
									onsubmit="return confirm('<?= $emp['status'] === 'active' ? 'Deactivate this employee?' : 'Activate this employee?' ?>');">
									<input type="hidden" name="employee_id" value="<?= (int) $emp['employee_id'] ?>">
									<input type="hidden" name="action" value="<?= $emp['status'] === 'active' ? 'deactivate' : 'activate' ?>">
									<input type="hidden" name="return" value="list">
									<?php if ($emp['status'] === 'active'): ?>
										<button type="submit" class="btn-sm danger">Deactivate</button>
									<?php else: ?>
										<button type="submit" class="btn-sm success">Activate</button>
									<?php endif; ?>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>