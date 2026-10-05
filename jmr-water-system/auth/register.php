<?php
require_once '../config/database.php';
require_once '../includes/auth-check.php';

// Already logged in? Go to dashboard.
if (isLoggedIn()) {
	redirectToDashboard();
}

$errors = [];
$full_name = $email = $phone = $address = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	// 1. Get and clean the input
	$full_name = trim($_POST['full_name'] ?? '');
	$email = trim($_POST['email'] ?? '');
	$phone = trim($_POST['phone'] ?? '');
	$address = trim($_POST['address'] ?? '');
	$password = $_POST['password'] ?? '';
	$confirm = $_POST['confirm_password'] ?? '';

	// 2. Validate
	if ($full_name === '' || $email === '' || $phone === '' || $address === '' || $password === '') {
		$errors[] = "All fields are required.";
	}
	if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$errors[] = "Please enter a valid email address.";
	}
	if ($phone !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
		$errors[] = "Please enter a valid phone number.";
	}
	if (strlen($password) < 6) {
		$errors[] = "Password must be at least 6 characters.";
	}
	if ($password !== $confirm) {
		$errors[] = "Passwords do not match.";
	}

	// 3. Check if email is already used
	if (empty($errors)) {
		$stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
		$stmt->execute([$email]);
		if ($stmt->fetch()) {
			$errors[] = "That email is already registered.";
		}
	}

	// 4. Save to database
	if (empty($errors)) {
		try {
			$pdo->beginTransaction();

			$hash = password_hash($password, PASSWORD_DEFAULT);

			$stmt = $pdo->prepare(
				"INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'customer')"
			);
			$stmt->execute([$full_name, $email, $hash]);
			$user_id = $pdo->lastInsertId();

			$stmt = $pdo->prepare(
				"INSERT INTO customers (user_id, phone, address) VALUES (?, ?, ?)"
			);
			$stmt->execute([$user_id, $phone, $address]);

			$pdo->commit();

			header("Location: login.php?registered=1");
			exit;
		} catch (Exception $ex) {
			$pdo->rollBack();
			$errors[] = "Something went wrong. Please try again.";
		}
	}
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Register - JMR Water Refilling Station</title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
	<link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
	<div class="auth-wrapper">
		<div class="auth-card">
			<div class="logo"><i class="fa-solid fa-droplet"></i></div>
			<h2>Create Account</h2>
			<p class="subtitle">JMR Water Refilling Station</p>

			<?php if (!empty($errors)): ?>
				<div class="alert alert-danger">
					<?php foreach ($errors as $err): ?>
						<div><?= e($err) ?></div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<form method="POST" action="">
				<div class="form-group">
					<label>Full Name</label>
					<input type="text" name="full_name" value="<?= e($full_name) ?>" required>
				</div>
				<div class="form-group">
					<label>Email</label>
					<input type="email" name="email" value="<?= e($email) ?>" required>
				</div>
				<div class="form-group">
					<label>Phone Number</label>
					<input type="text" name="phone" value="<?= e($phone) ?>" required>
				</div>
				<div class="form-group">
					<label>Address</label>
					<textarea name="address" rows="2" required><?= e($address) ?></textarea>
				</div>
				<div class="form-group">
					<label>Password</label>
					<input type="password" name="password" required>
				</div>
				<div class="form-group">
					<label>Confirm Password</label>
					<input type="password" name="confirm_password" required>
				</div>
				<button type="submit" class="btn">Register</button>
			</form>

			<div class="auth-link">
				Already have an account? <a href="login.php">Login</a>
			</div>
		</div>
	</div>
</body>

</html>