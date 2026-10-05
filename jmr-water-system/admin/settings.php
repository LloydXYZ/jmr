<?php
require_once '../config/database.php';
require_once '../includes/auth-check.php';
requireRole(['admin']);

$pageTitle = 'Settings';
$errors = [];
$saved = isset($_GET['saved']);
$price = getPricePerGallon($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$input = trim($_POST['price_per_gallon'] ?? '');
	$newPrice = filter_var($input, FILTER_VALIDATE_FLOAT);

	if ($newPrice === false || $newPrice <= 0 || $newPrice > 10000) {
		$errors[] = "Price must be a number greater than 0 and not more than 10,000.";
		$price = $input;
	} else {
		$stmt = $pdo->prepare(
			"INSERT INTO settings (setting_key, setting_value) VALUES ('price_per_gallon', ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
		);
		$stmt->execute([number_format($newPrice, 2, '.', '')]);
		logActivity($pdo, "Changed the price per gallon to " . peso($newPrice));

		header("Location: settings.php?saved=1");
		exit;
	}
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/navbar.php';
?>

<h1 class="page-title">Settings</h1>

<?php if ($saved): ?>
	<div class="alert alert-success">Price updated. It applies to new reservations only.</div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
	<div class="alert alert-danger">
		<?php foreach ($errors as $err): ?>
			<div>
				<?= e($err) ?>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<div class="panel form-panel">
	<h3>Water Price</h3>
	<form method="POST" action="">
		<div class="form-group" style="max-width:260px;">
			<label>Price per 20-liter gallon (₱)</label>
			<input type="number" name="price_per_gallon" step="0.01" min="0.01" value="<?= e($price) ?>" required>
			<div class="form-hint">Existing reservations keep the price they were made with.</div>
		</div>
		<button type="submit" class="btn-inline"><i class="fa-solid fa-floppy-disk"></i> Save Price</button>
	</form>
</div>

<?php require_once '../includes/footer.php'; ?>