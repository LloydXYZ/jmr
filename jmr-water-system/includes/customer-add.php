<?php
// includes/customer-add.php

$pageTitle = 'Add Customer';
$errors = [];
$full_name = $email = $phone = $address = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$full_name = trim($_POST['full_name'] ?? '');
	$email = trim($_POST['email'] ?? '');
	$phone = trim($_POST['phone'] ?? '');
	$address = trim($_POST['address'] ?? '');
	$password = $_POST['password'] ?? '';

	if ($full_name === '' || $email === '' || $phone === '' || $address === '' || $password === '') {
		$errors[] = "All fields are required.";
	}
	if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$errors[] = "Please enter a valid email address.";
	}
	if ($phone !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
		$errors[] = "Please enter a valid phone number.";
	}
	if ($password !== '' && strlen($password) < 6) {
		$errors[] = "Password must be at least 6 characters.";
	}

	if (empty($errors)) {
		$stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
		$stmt->execute([$email]);
		if ($stmt->fetch()) {
			$errors[] = "That email is already registered.";
		}
	}

	if (empty($errors)) {
		try {
			$pdo->beginTransaction();

			$hash = password_hash($password, PASSWORD_DEFAULT);
			$stmt = $pdo->prepare(
				"INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'customer')"
			);
			$stmt->execute([$full_name, $email, $hash]);
			$user_id = $pdo->lastInsertId();

			$stmt = $pdo->prepare("INSERT INTO customers (user_id, phone, address) VALUES (?, ?, ?)");
			$stmt->execute([$user_id, $phone, $address]);

			$pdo->commit();
			header("Location: customers.php?msg=added");
			exit;
		} catch (Exception $ex) {
			$pdo->rollBack();
			$errors[] = "Something went wrong. Please try again.";
		}
	}
}

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/navbar.php';
?>

<h1 class="page-title">Add Customer</h1>

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
				<label>Email</label>
				<input type="email" name="email" value="<?= e($email) ?>" required>
			</div>
		</div>
		<div class="form-row">
			<div class="form-group">
				<label>Phone Number</label>
				<input type="text" name="phone" value="<?= e($phone) ?>" required>
			</div>
			<div class="form-group">
				<label>Temporary Password</label>
				<input type="text" name="password" required>
				<div class="form-hint">Tell the customer this password so they can log in.</div>
			</div>
		</div>
		<div class="form-group">
			<label>Address</label>
			<textarea name="address" rows="2" required><?= e($address) ?></textarea>
		</div>

		<button type="submit" class="btn-inline"><i class="fa-solid fa-floppy-disk"></i> Save Customer</button>
		<a href="customers.php" class="btn-sm gray" style="margin-left:8px;">Cancel</a>
	</form>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>