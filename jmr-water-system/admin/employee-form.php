<?php
require_once '../config/database.php';
require_once '../includes/auth-check.php';
requireRole(['admin']);

$id = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;
$pageTitle = $isEdit ? 'Edit Employee' : 'Add Employee';
$positions = employeePositions();

$errors = [];
$full_name = '';
$position = '';
$contact = '';
$salary = '';
$date_hired = date('Y-m-d');

// Editing: load the saved employee first
if ($isEdit) {
	$stmt = $pdo->prepare("SELECT * FROM employees WHERE employee_id = ?");
	$stmt->execute([$id]);
	$emp = $stmt->fetch();

	if (!$emp) {
		header("Location: employees.php?msg=error");
		exit;
	}

	$full_name = $emp['full_name'];
	$position = $emp['job_position'];
	$contact = $emp['contact_number'];
	$salary = $emp['monthly_salary'];
	$date_hired = $emp['date_hired'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$full_name = trim($_POST['full_name'] ?? '');
	$position = $_POST['job_position'] ?? '';
	$contact = trim($_POST['contact_number'] ?? '');
	$salary = trim($_POST['monthly_salary'] ?? '');
	$date_hired = $_POST['date_hired'] ?? '';

	if ($full_name === '') {
		$errors[] = "Full name is required.";
	}
	if (!in_array($position, $positions)) {
		$errors[] = "Please choose a position.";
	}
	if (!preg_match('/^[0-9+\-\s]{7,20}$/', $contact)) {
		$errors[] = "Please enter a valid contact number.";
	}

	$salaryNum = filter_var($salary, FILTER_VALIDATE_FLOAT);
	if ($salaryNum === false || $salaryNum < 0 || $salaryNum > 999999.99) {
		$errors[] = "Monthly salary must be a number between 0 and 999,999.99.";
	}

	$dateObj = DateTime::createFromFormat('Y-m-d', $date_hired);
	if (!$dateObj || $dateObj->format('Y-m-d') !== $date_hired) {
		$errors[] = "Please choose a valid date hired.";
	} elseif ($date_hired > date('Y-m-d')) {
		$errors[] = "Date hired cannot be in the future.";
	}

	if (empty($errors)) {
		if ($isEdit) {
			$stmt = $pdo->prepare(
				"UPDATE employees
                 SET full_name = ?, job_position = ?, contact_number = ?, monthly_salary = ?, date_hired = ?
                 WHERE employee_id = ?"
			);
			$stmt->execute([$full_name, $position, $contact, $salaryNum, $date_hired, $id]);
			header("Location: employee-view.php?id=$id&msg=updated");
		} else {
			$stmt = $pdo->prepare(
				"INSERT INTO employees (full_name, job_position, contact_number, monthly_salary, date_hired)
                 VALUES (?, ?, ?, ?, ?)"
			);
			$stmt->execute([$full_name, $position, $contact, $salaryNum, $date_hired]);
			header("Location: employees.php?msg=added");
		}
		exit;
	}
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/navbar.php';
?>

<h1 class="page-title"><?= e($pageTitle) ?></h1>

<?php if (!empty($errors)): ?>
	<div class="alert alert-danger">
		<?php foreach ($errors as $err): ?>
			<div><?= e($err) ?></div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<div class="panel form-panel">
	<form method="POST" action="">
		<div class="form-row">
			<div class="form-group">
				<label>Full Name</label>
				<input type="text" name="full_name" value="<?= e($full_name) ?>" required>
			</div>
			<div class="form-group">
				<label>Position</label>
				<select name="job_position" required>
					<option value="">-- Select --</option>
					<?php foreach ($positions as $p): ?>
						<option value="<?= e($p) ?>" <?= $position === $p ? 'selected' : '' ?>><?= e($p) ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div class="form-row">
			<div class="form-group">
				<label>Contact Number</label>
				<input type="text" name="contact_number" value="<?= e($contact) ?>" required>
			</div>
			<div class="form-group">
				<label>Monthly Salary (₱)</label>
				<input type="number" name="monthly_salary" step="0.01" min="0" value="<?= e($salary) ?>" required>
			</div>
		</div>

		<div class="form-group" style="max-width:260px;">
			<label>Date Hired</label>
			<input type="date" name="date_hired" max="<?= date('Y-m-d') ?>" value="<?= e($date_hired) ?>" required>
		</div>

		<button type="submit" class="btn-inline"><i class="fa-solid fa-floppy-disk"></i> Save Employee</button>
		<a href="<?= $isEdit ? 'employee-view.php?id=' . (int) $id : 'employees.php' ?>" class="btn-sm gray"
			style="margin-left:8px;">Cancel</a>
	</form>
</div>

<?php require_once '../includes/footer.php'; ?>