<?php
// includes/customers-list.php
// Shared by admin/customers.php and staff/customers.php

$pageTitle = 'Customers';

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$sql = "SELECT c.customer_id, c.phone, c.address, c.created_at,
               u.full_name, u.email, u.status,
               (SELECT COUNT(*) FROM reservations r WHERE r.customer_id = c.customer_id) AS total_reservations
        FROM customers c
        JOIN users u ON c.user_id = u.user_id
        WHERE 1=1";
$params = [];

if ($search !== '') {
	$sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR c.phone LIKE ?)";
	$params[] = "%$search%";
	$params[] = "%$search%";
	$params[] = "%$search%";
}

if (in_array($statusFilter, ['active', 'inactive'])) {
	$sql .= " AND u.status = ?";
	$params[] = $statusFilter;
}

$sql .= " ORDER BY c.created_at DESC, c.customer_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$messages = [
	'added' => ['success', 'Customer added.'],
	'deactivated' => ['success', 'Customer deactivated. They can no longer log in.'],
	'activated' => ['success', 'Customer activated.'],
	'error' => ['danger', 'That action could not be completed.'],
];
$msg = $_GET['msg'] ?? '';

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/navbar.php';
?>

<h1 class="page-title">Customers</h1>

<?php if (isset($messages[$msg])): ?>
	<div class="alert alert-<?= $messages[$msg][0] ?>"><?= e($messages[$msg][1]) ?></div>
<?php endif; ?>

<div class="panel">
	<form method="GET" class="filter-bar">
		<input type="text" name="search" placeholder="Search name, email, or phone" value="<?= e($search) ?>">
		<select name="status">
			<option value="">All accounts</option>
			<option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
			<option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
		</select>
		<button type="submit" class="btn-sm"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
		<a href="customers.php" class="btn-sm gray">Reset</a>
		<a href="customer-add.php" class="btn-sm success"><i class="fa-solid fa-plus"></i> Add Customer</a>
	</form>

	<?php if (empty($customers)): ?>
		<p class="empty-text">No customers found.</p>
	<?php else: ?>
		<div class="table-wrap">
			<table class="data-table">
				<thead>
					<tr>
						<th>Name</th>
						<th>Email</th>
						<th>Phone</th>
						<th>Reservations</th>
						<th>Registered</th>
						<th>Status</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($customers as $c): ?>
						<tr>
							<td><?= e($c['full_name']) ?></td>
							<td><?= e($c['email']) ?></td>
							<td><?= e($c['phone']) ?></td>
							<td><?= (int) $c['total_reservations'] ?></td>
							<td><?= e(date('M d, Y', strtotime($c['created_at']))) ?></td>
							<td><?= accountBadge($c['status']) ?></td>
							<td class="actions-cell">
								<a href="customer-view.php?id=<?= (int) $c['customer_id'] ?>" class="btn-sm">View</a>
								<a href="customer-edit.php?id=<?= (int) $c['customer_id'] ?>" class="btn-sm warning">Edit</a>
								<form method="POST" action="customer-action.php" style="display:inline;"
									onsubmit="return confirm('<?= $c['status'] === 'active' ? 'Deactivate this customer?' : 'Activate this customer?' ?>');">
									<input type="hidden" name="customer_id" value="<?= (int) $c['customer_id'] ?>">
									<input type="hidden" name="action" value="<?= $c['status'] === 'active' ? 'deactivate' : 'activate' ?>">
									<input type="hidden" name="return" value="list">
									<?php if ($c['status'] === 'active'): ?>
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

<?php require_once __DIR__ . '/footer.php'; ?>