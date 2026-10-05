<?php
// includes/customer-edit.php

$pageTitle = 'Edit Customer';
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
	"SELECT c.customer_id, c.user_id, c.phone, c.address, u.full_name, u.email
     FROM customers c
     JOIN users u ON c.user_id = u.user_id
     WHERE c.customer_id = ?"
);
$stmt->execute([$id]);
$c = $stmt->fetch();

if (!$c) {
	header("Location: customers.php?msg=error");
	exit;
}

$errors = [];
$full_name = $c['full_name'];
$email = $c['email'];
$phone = $c['phone'];
$address = $c['address'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$full_name = trim($_POST['full_name'] ?? '');
	$email = trim($_POST['email'] ?? '');
	$phone = trim($_POST['phone'] ?? '');
	$address = trim($_POST['address'] ?? '');
	$password = $_POST['password'] ?? '';   // optional

	if ($full_name === '' || $email === '' || $phone === '' || $address === '') {
		$errors[] = "Name, email, phone, and address are required.";
	}
	if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$errors[] = "Please enter a valid email address.";
	}
	if ($phone !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
		$errors[] = "Please enter a valid phone number.";
	}
	if ($password !== '' && strlen($password) < 6) {
		$errors[] = "New password must be at least 6 characters.";
	}

	// Email must be unique, ignoring this customer's own account
	if (empty($errors)) {
		$stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id <> ?");
		$stmt->execute([$email, $c['user_id']]);
		if ($stmt->fetch()) {
			$errors[] = "That email is used by another account.";
		}
	}

	if (empty($errors)) {
		try {
			$pdo->beginTransaction();

			if ($password !== '') {
				$hash = password_hash($password, PASSWORD_DEFAULT);
				$stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, password = ? WHERE user_id = ?");
				$stmt->execute([$full_name, $email, $hash, $c['user_id']]);
			} else {
				$stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE user_id = ?");
				$stmt->execute([$full_name, $email, $c['user_id']]);
			}

			$stmt = $pdo->prepare("UPDATE customers SET phone = ?, address = ? WHERE customer_id = ?");
			$stmt->execute([$phone, $address, $id]);

			$pdo->commit();
			header("Location: customer-view.php?id=$id&msg=updated");
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

<h1 class="page-title">Edit Customer</h1>

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
				<label>Reset Password (optional)</label>
				<input type="text" name="password" placeholder="Leave blank to keep current">
			</div>
		</div>
		<div class="form-group">
			<label>Address</label>
			<textarea name="address" rows="2" required><?= e($address) ?></textarea>
		</div>

		<button type="submit" class="btn-inline"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
		<a href="customer-view.php?id=<?= (int) $id ?>" class="btn-sm gray" style="margin-left:8px;">Cancel</a>
	</form>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>