<?php
// includes/deliveries-list.php
// Shared by admin/deliveries.php and staff/deliveries.php

$pageTitle = 'Deliveries';

$validStatuses = ['scheduled', 'out_for_delivery', 'delivered', 'cancelled'];
$validViews = ['today', 'upcoming', 'all'];

$view = $_GET['view'] ?? 'today';
if (!in_array($view, $validViews)) {
	$view = 'today';
}
$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

$sql = "SELECT d.*, r.quantity, r.delivery_address,
               u.full_name AS customer_name, c.phone,
               e.full_name AS staff_name
        FROM deliveries d
        JOIN reservations r ON d.reservation_id = r.reservation_id
        JOIN customers c ON r.customer_id = c.customer_id
        JOIN users u ON c.user_id = u.user_id
        JOIN employees e ON d.employee_id = e.employee_id
        WHERE 1=1";
$params = [];

if ($view === 'today') {
	$sql .= " AND d.delivery_date = CURDATE()";
} elseif ($view === 'upcoming') {
	$sql .= " AND d.delivery_date > CURDATE()";
}

if (in_array($statusFilter, $validStatuses)) {
	$sql .= " AND d.status = ?";
	$params[] = $statusFilter;
}

// Search by customer name, staff name, or reservation number
if ($search !== '') {
	$sql .= " AND (u.full_name LIKE ? OR e.full_name LIKE ? OR r.reservation_id = ?)";
	$params[] = "%$search%";
	$params[] = "%$search%";
	$params[] = (int) preg_replace('/\D/', '', $search);
}

$order = ($view === 'all') ? "d.delivery_date DESC" : "d.delivery_date ASC";
$sql .= " ORDER BY $order, FIELD(d.time_slot, 'morning', 'noon', 'afternoon'), d.delivery_id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$deliveries = $stmt->fetchAll();

// Confirmed delivery reservations that still need a schedule
$awaiting = $pdo->query(
	"SELECT r.*, u.full_name, c.phone
     FROM reservations r
     JOIN customers c ON r.customer_id = c.customer_id
     JOIN users u ON c.user_id = u.user_id
     WHERE r.reservation_type = 'delivery' AND r.status = 'confirmed'
     ORDER BY r.reservation_date ASC, r.reservation_id ASC"
)->fetchAll();

$messages = [
	'scheduled' => ['success', 'Delivery scheduled.'],
	'updated' => ['success', 'Delivery updated.'],
	'started' => ['success', 'Delivery is now Out for Delivery.'],
	'delivered' => ['success', 'Delivery marked as Delivered. The reservation is now Completed.'],
	'cancelled' => ['success', 'Delivery cancelled. The reservation is back to Confirmed and can be rescheduled.'],
	'nostock' => ['danger', 'Not enough gallons in stock for this action. Please add stock in Inventory first, then try again.'],
	'error' => ['danger', 'That action is not allowed for the current status of the delivery.'],
];
$msg = $_GET['msg'] ?? '';

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/navbar.php';
?>

<h1 class="page-title">Deliveries</h1>

<?php if (isset($messages[$msg])): ?>
	<div class="alert alert-<?= $messages[$msg][0] ?>"><?= e($messages[$msg][1]) ?></div>
<?php endif; ?>

<?php if (!empty($awaiting)): ?>
	<div class="panel">
		<h3>Awaiting Scheduling (<?= count($awaiting) ?>)</h3>
		<div class="table-wrap">
			<table class="data-table">
				<thead>
					<tr>
						<th>Reservation</th>
						<th>Customer</th>
						<th>Qty</th>
						<th>Requested Date</th>
						<th>Time</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($awaiting as $a): ?>
						<tr>
							<td><?= e(formatReservationId($a['reservation_id'])) ?></td>
							<td><?= e($a['full_name']) ?><br><small><?= e($a['phone']) ?></small></td>
							<td><?= e($a['quantity']) ?></td>
							<td><?= e(date('M d, Y', strtotime($a['reservation_date']))) ?></td>
							<td><?= e(ucfirst($a['time_slot'])) ?></td>
							<td><a href="delivery-form.php?reservation_id=<?= (int) $a['reservation_id'] ?>"
									class="btn-sm success">Schedule</a></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
<?php endif; ?>

<div class="panel">
	<form method="GET" class="filter-bar">
		<select name="view">
			<option value="today" <?= $view === 'today' ? 'selected' : '' ?>>Today</option>
			<option value="upcoming" <?= $view === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
			<option value="all" <?= $view === 'all' ? 'selected' : '' ?>>All dates</option>
		</select>
		<select name="status">
			<option value="">All statuses</option>
			<?php foreach ($validStatuses as $s): ?>
				<option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>>
					<?= e(ucwords(str_replace('_', ' ', $s))) ?>
				</option>
			<?php endforeach; ?>
		</select>
		<input type="text" name="search" placeholder="Customer, staff, or RES-001" value="<?= e($search) ?>">
		<button type="submit" class="btn-sm"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
		<a href="deliveries.php" class="btn-sm gray">Reset</a>
	</form>

	<?php if (empty($deliveries)): ?>
		<p class="empty-text">No deliveries found for this view.</p>
	<?php else: ?>
		<div class="table-wrap">
			<table class="data-table">
				<thead>
					<tr>
						<th>Reservation</th>
						<th>Customer</th>
						<th>Address</th>
						<th>Qty</th>
						<th>Date</th>
						<th>Time</th>
						<th>Delivery Staff</th>
						<th>Status</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($deliveries as $d): ?>
						<tr>
							<td><a
									href="reservation-view.php?id=<?= (int) $d['reservation_id'] ?>"><?= e(formatReservationId($d['reservation_id'])) ?></a>
							</td>
							<td><?= e($d['customer_name']) ?><br><small><?= e($d['phone']) ?></small></td>
							<td><?= nl2br(e($d['delivery_address'])) ?></td>
							<td><?= e($d['quantity']) ?></td>
							<td><?= e(date('M d, Y', strtotime($d['delivery_date']))) ?></td>
							<td><?= e(ucfirst($d['time_slot'])) ?></td>
							<td><?= e($d['staff_name']) ?></td>
							<td><?= deliveryBadge($d['status']) ?></td>
							<td class="actions-cell">
								<?php if ($d['status'] === 'scheduled'): ?>
									<?= deliveryActionForm($d['delivery_id'], 'start', 'Out for Delivery', 'warning', 'Mark this delivery as out for delivery?', $view) ?>
									<a href="delivery-form.php?id=<?= (int) $d['delivery_id'] ?>" class="btn-sm">Edit</a>
									<?= deliveryActionForm($d['delivery_id'], 'cancel', 'Cancel', 'danger', 'Cancel this delivery?', $view) ?>
								<?php elseif ($d['status'] === 'out_for_delivery'): ?>
									<?= deliveryActionForm($d['delivery_id'], 'deliver', 'Mark Delivered', 'success', 'Mark this delivery as delivered?', $view) ?>
									<?= deliveryActionForm($d['delivery_id'], 'cancel', 'Cancel', 'danger', 'Cancel this delivery?', $view) ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>