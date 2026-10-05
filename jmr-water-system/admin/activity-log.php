<?php
require_once '../config/database.php';
require_once '../includes/auth-check.php';
requireRole(['admin']);

$pageTitle = 'Activity Log';

$isDate = function ($d) {
	$o = DateTime::createFromFormat('Y-m-d', $d);
	return $o && $o->format('Y-m-d') === $d;
};

$search = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
if (!$isDate($from)) {
	$from = '';
}
if (!$isDate($to)) {
	$to = '';
}

$sql = "SELECT l.*, u.full_name, u.role
        FROM activity_logs l
        LEFT JOIN users u ON l.user_id = u.user_id
        WHERE 1=1";
$params = [];

if ($search !== '') {
	$sql .= " AND (u.full_name LIKE ? OR l.action LIKE ?)";
	$params[] = "%$search%";
	$params[] = "%$search%";
}
if (in_array($roleFilter, ['admin', 'staff', 'customer'], true)) {
	$sql .= " AND u.role = ?";
	$params[] = $roleFilter;
}
if ($from !== '') {
	$sql .= " AND DATE(l.created_at) >= ?";
	$params[] = $from;
}
if ($to !== '') {
	$sql .= " AND DATE(l.created_at) <= ?";
	$params[] = $to;
}

$sql .= " ORDER BY l.created_at DESC, l.log_id DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/navbar.php';
?>

<h1 class="page-title">Activity Log</h1>

<div class="panel">
	<form method="GET" class="filter-bar no-print">
		<input type="text" name="search" placeholder="Search user or action" value="<?= e($search) ?>">
		<select name="role">
			<option value="">All roles</option>
			<option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
			<option value="staff" <?= $roleFilter === 'staff' ? 'selected' : '' ?>>Staff</option>
			<option value="customer" <?= $roleFilter === 'customer' ? 'selected' : '' ?>>Customer</option>
		</select>
		<label>From</label><input type="date" name="from" value="<?= e($from) ?>">
		<label>To</label><input type="date" name="to" value="<?= e($to) ?>">
		<button type="submit" class="btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
		<a href="activity-log.php" class="btn-sm gray">Reset</a>
		<button type="button" class="btn-sm gray" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
	</form>
	<p class="form-hint" style="margin-bottom:12px;">Showing the latest 200 matching entries.</p>

	<?php if (empty($logs)): ?>
		<p class="empty-text">No activity found.</p>
	<?php else: ?>
		<div class="table-wrap">
			<table class="data-table">
				<thead>
					<tr>
						<th>User</th>
						<th>Action</th>
						<th>Date</th>
						<th>Time</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($logs as $l): ?>
						<tr>
							<td>
								<?= e($l['full_name'] ?? 'System') ?>
								<?php if ($l['role']): ?><br><span class="role-tag"><?= e(ucfirst($l['role'])) ?></span><?php endif; ?>
							</td>
							<td><?= e($l['action']) ?></td>
							<td><?= e(date('M d, Y', strtotime($l['created_at']))) ?></td>
							<td><?= e(date('h:i A', strtotime($l['created_at']))) ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>