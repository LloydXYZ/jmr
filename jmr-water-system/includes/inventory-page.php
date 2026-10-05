<?php
// includes/inventory-page.php
// Shared by admin/inventory.php and staff/inventory.php

$pageTitle = 'Inventory';
$errors = [];
$isAdmin = ($_SESSION['role'] === 'admin');

$inv = getInventorySummary($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$form = $_POST['form'] ?? '';

	if ($form === 'stock') {
		$type = $_POST['stock_action'] ?? '';
		$qty = (int) ($_POST['quantity'] ?? 0);
		$remarks = trim($_POST['remarks'] ?? '');

		if (!in_array($type, ['add', 'deduct'])) {
			$errors[] = "Please choose Add stock or Deduct stock.";
		}
		if ($qty < 1 || $qty > 10000) {
			$errors[] = "Quantity must be between 1 and 10,000.";
		}
		if (strlen($remarks) > 200) {
			$errors[] = "Remarks must be 200 characters or less.";
		}

		if (empty($errors)) {
			if ($remarks === '') {
				$remarks = ($type === 'add') ? 'Stock added' : 'Stock deducted';
			}
			if (changeStock($pdo, $type, $qty, $_SESSION['user_id'], null, $remarks)) {
				logActivity($pdo, ($type === 'add' ? "Added $qty gallon(s) to inventory" : "Deducted $qty gallon(s) from inventory"));
				if ($type === 'deduct') {
					checkLowStock($pdo);
				}
				header("Location: inventory.php?msg=" . ($type === 'add' ? 'added' : 'deducted'));
				exit;
			}
			$errors[] = "You can only deduct gallons that are available (in stock minus reserved).";
		}

	} elseif ($form === 'limit' && $isAdmin) {
		$limit = filter_var($_POST['low_stock_limit'] ?? '', FILTER_VALIDATE_INT);
		if ($limit === false || $limit < 0 || $limit > 10000) {
			$errors[] = "The warning level must be a whole number from 0 to 10,000.";
		} else {
			$stmt = $pdo->prepare("UPDATE inventory SET low_stock_limit = ? WHERE inventory_id = ?");
			$stmt->execute([$limit, $inv['inventory_id']]);
			logActivity($pdo, "Changed the low-stock warning level to $limit gallon(s)");

			header("Location: inventory.php?msg=limit");
			exit;
		}
	}
}

// History (latest 100), with an optional type filter
$typeFilter = $_GET['type'] ?? '';
$sql = "SELECT t.*, u.full_name
        FROM inventory_transactions t
        LEFT JOIN users u ON t.created_by = u.user_id
        WHERE 1=1";
$params = [];
if (in_array($typeFilter, ['add', 'deduct', 'deliver'])) {
	$sql .= " AND t.transaction_type = ?";
	$params[] = $typeFilter;
}
$sql .= " ORDER BY t.created_at DESC, t.transaction_id DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$history = $stmt->fetchAll();

$messages = [
	'added' => ['success', 'Stock added.'],
	'deducted' => ['success', 'Stock deducted.'],
	'limit' => ['success', 'Low-stock warning level updated.'],
];
$msg = $_GET['msg'] ?? '';

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/navbar.php';
?>

<h1 class="page-title">Inventory (20-liter gallons)</h1>

<?php if (isset($messages[$msg])): ?>
	<div class="alert alert-<?= $messages[$msg][0] ?>"><?= e($messages[$msg][1]) ?></div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
	<div class="alert alert-danger">
		<?php foreach ($errors as $err): ?>
			<div><?= e($err) ?></div><?php endforeach; ?>
	</div>
<?php endif; ?>

<?php if ($inv['available'] < 0): ?>
	<div class="alert alert-danger">
		<i class="fa-solid fa-triangle-exclamation"></i>
		Reserved gallons (<?= (int) $inv['reserved'] ?>) are more than the stock on hand (<?= (int) $inv['on_hand'] ?>).
		Add stock to cover the difference.
	</div>
<?php elseif ($inv['is_low']): ?>
	<div class="alert alert-warning">
		<i class="fa-solid fa-triangle-exclamation"></i>
		Low stock: only <strong><?= (int) $inv['available'] ?></strong> gallon(s) available
		(warning level is <?= (int) $inv['low_limit'] ?>).
	</div>
<?php endif; ?>

<div class="card-grid">
	<div class="stat-card">
		<div class="icon"><i class="fa-solid fa-warehouse"></i></div>
		<div>
			<div class="label">In Stock</div>
			<div class="value"><?= (int) $inv['on_hand'] ?></div>
		</div>
	</div>
	<div class="stat-card">
		<div class="icon orange"><i class="fa-solid fa-bookmark"></i></div>
		<div>
			<div class="label">Reserved</div>
			<div class="value"><?= (int) $inv['reserved'] ?></div>
		</div>
	</div>
	<div class="stat-card">
		<div class="icon green"><i class="fa-solid fa-bottle-water"></i></div>
		<div>
			<div class="label">Available</div>
			<div class="value"><?= (int) $inv['available'] ?></div>
		</div>
	</div>
	<div class="stat-card">
		<div class="icon"><i class="fa-solid fa-truck"></i></div>
		<div>
			<div class="label">Delivered / Used</div>
			<div class="value"><?= (int) $inv['used'] ?></div>
		</div>
	</div>
</div>

<p class="form-hint" style="margin-bottom:16px;">
	Available = In Stock minus Reserved. Reserved gallons come from Confirmed, Scheduled, and Out for Delivery
	reservations. Gallons leave the stock only when a reservation is Completed.
</p>

<div class="panel form-panel">
	<h3>Add / Deduct Stock</h3>
	<form method="POST" action="">
		<input type="hidden" name="form" value="stock">
		<div class="form-row">
			<div class="form-group">
				<label>Action</label>
				<select name="stock_action" required>
					<option value="add">Add stock</option>
					<option value="deduct">Deduct stock (damaged, leaked, etc.)</option>
				</select>
			</div>
			<div class="form-group">
				<label>Quantity (gallons)</label>
				<input type="number" name="quantity" min="1" max="10000" required>
			</div>
		</div>
		<div class="form-group">
			<label>Remarks (optional)</label>
			<input type="text" name="remarks" maxlength="200" placeholder="Example: New delivery from supplier">
		</div>
		<button type="submit" class="btn-inline"><i class="fa-solid fa-floppy-disk"></i> Save</button>
	</form>
</div>

<div class="panel form-panel">
	<h3>Low-Stock Warning Level</h3>
	<?php if ($isAdmin): ?>
		<form method="POST" action="">
			<input type="hidden" name="form" value="limit">
			<div class="form-group" style="max-width:260px;">
				<label>Warn me when available gallons are at or below</label>
				<input type="number" name="low_stock_limit" min="0" max="10000" value="<?= (int) $inv['low_limit'] ?>" required>
			</div>
			<button type="submit" class="btn-inline">Save Level</button>
		</form>
	<?php else: ?>
		<p>The warning appears when available gallons are at or below <strong><?= (int) $inv['low_limit'] ?></strong>.
			Only the administrator can change this level.</p>
	<?php endif; ?>
</div>

<div class="panel">
	<h3>Inventory History</h3>
	<form method="GET" class="filter-bar">
		<select name="type" onchange="this.form.submit()">
			<option value="">All movements</option>
			<option value="add" <?= $typeFilter === 'add' ? 'selected' : '' ?>>Stock Added</option>
			<option value="deduct" <?= $typeFilter === 'deduct' ? 'selected' : '' ?>>Stock Deducted</option>
			<option value="deliver" <?= $typeFilter === 'deliver' ? 'selected' : '' ?>>Delivered</option>
		</select>
		<a href="inventory.php" class="btn-sm gray">Reset</a>
	</form>

	<?php if (empty($history)): ?>
		<p class="empty-text">No inventory movements yet.</p>
	<?php else: ?>
		<div class="table-wrap">
			<table class="data-table">
				<thead>
					<tr>
						<th>Date</th>
						<th>Type</th>
						<th>Change</th>
						<th>Balance</th>
						<th>Reservation</th>
						<th>Remarks</th>
						<th>By</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($history as $t): ?>
						<tr>
							<td><?= e(date('M d, Y h:i A', strtotime($t['created_at']))) ?></td>
							<td><?= inventoryTypeBadge($t['transaction_type']) ?></td>
							<td><strong><?= $t['transaction_type'] === 'add' ? '+' : '-' ?><?= (int) $t['quantity'] ?></strong></td>
							<td><?= (int) $t['balance_after'] ?></td>
							<td>
								<?php if ($t['reservation_id']): ?>
									<a
										href="reservation-view.php?id=<?= (int) $t['reservation_id'] ?>"><?= e(formatReservationId($t['reservation_id'])) ?></a>
								<?php else: ?>—<?php endif; ?>
							</td>
							<td><?= e($t['remarks']) ?></td>
							<td><?= e($t['full_name'] ?? 'System') ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>