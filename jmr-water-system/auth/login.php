<?php
require_once '../config/database.php';
require_once '../includes/auth-check.php';

if (isLoggedIn()) {
	redirectToDashboard();
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$email = trim($_POST['email'] ?? '');
	$password = $_POST['password'] ?? '';

	if ($email === '' || $password === '') {
		$error = "Please enter your email and password.";
	} else {
		$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
		$stmt->execute([$email]);
		$user = $stmt->fetch();

		if ($user && password_verify($password, $user['password'])) {
			if ($user['status'] !== 'active') {
				$error = "Your account is inactive. Please contact the administrator.";
			} else {
				// Prevents session fixation attacks
				session_regenerate_id(true);

				$_SESSION['user_id'] = $user['user_id'];
				$_SESSION['full_name'] = $user['full_name'];
				$_SESSION['role'] = $user['role'];
				logActivity($pdo, "Logged in");

				redirectToDashboard();
			}
		} else {
			// Same message for wrong email or wrong password (safer)
			$error = "Invalid email or password.";
		}
	}
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Login - JMR Water Refilling Station</title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
	<link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
	<div class="auth-wrapper">
		<div class="auth-card">
			<div class="logo"><i class="fa-solid fa-droplet"></i></div>
			<h2>JMR Water Refilling Station</h2>
			<p class="subtitle">Please login to continue</p>

			<?php if (isset($_GET['registered'])): ?>
				<div class="alert alert-success">Registration successful! You can now login.</div>
			<?php endif; ?>

			<?php if (isset($_GET['loggedout'])): ?>
				<div class="alert alert-success">You have been logged out.</div>
			<?php endif; ?>

			<?php if ($error !== ''): ?>
				<div class="alert alert-danger"><?= e($error) ?></div>
			<?php endif; ?>

			<form method="POST" action="">
				<div class="form-group">
					<label>Email</label>
					<input type="email" name="email" value="<?= e($email) ?>" required>
				</div>
				<div class="form-group">
					<label>Password</label>
					<input type="password" name="password" required>
				</div>
				<button type="submit" class="btn">Login</button>
			</form>

			<div class="auth-link">
				No account yet? <a href="register.php">Register here</a>
			</div>
		</div>
	</div>
</body>

</html>