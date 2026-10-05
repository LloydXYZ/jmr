<?php
// includes/reservations-list.php
// Shared by admin/reservations.php and staff/reservations.php

$pageTitle = 'Reservations';

$validStatuses = ['pending', 'confirmed', 'scheduled', 'out_for_delivery', 'completed', 'cancelled'];

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$dateFilter = $_GET['date'] ?? '';

$sql = "SELECT r.*, u.full_name, c.phone
        FROM reservations r
        JOIN customers c ON r.customer_id = c.customer_id
        JOIN users u ON c.user_id = u.user_id
        WHERE 1=1";
$params = [];

// Search by customer name, phone, or reservation number (RES-005 or 5)
if ($search !== '') {
	$sql .= " AND (u.full_name LIKE ? OR c.phone LIKE ? OR r.reservation_id = ?)";
	$params[] = "%$search%";
	$params[] = "%$search%";
	$params[] = (int) preg_replace('/\D/', '', $search);
}

if (in_array($statusFilter, $validStatuses)) {
	$sql .= " AND r.status = ?";
	$params[] = $statusFilter;
}

$dateObj = DateTime::createFromFormat('Y-m-d', $dateFilter);
if ($dateObj && $dateObj->format('Y-m-d') === $dateFilter) {
	$sql .= " AND r.reservation_date = ?";
	$params[] = $dateFilter;
} else {
	$dateFilter = '';
}

$sql .= " ORDER BY r.reservation_date DESC, r.reservation_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll();

// Messages shown after an action
$messages = [
	'confirmed' => ['success', 'Reservation confirmed.'],
	'completed' => ['success', 'Reservation marked as completed.'],
	'cancelled' => ['success', 'Reservation cancelled.'],
	'nostock' => ['danger', 'Not enough gallons in stock for this action. Please add stock in Inventory first, then try again.'],
	'error' => ['danger', 'That action is not allowed for the current status of the reservation.'],
];
$msg = $_GET['msg'] ?? '';

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/navbar.php';
?>

<h1 class="page-title">Reservations</h1>

<?php if (isset($messages[$msg])): ?>
	<div class="alert alert-<?= $messages[$msg][0] ?>"><?= e($messages[$msg][1]) ?></div>
<?php endif; ?>

<div class="panel">
	<form method="GET" class="filter-bar">
		<input type="text" name="search" placeholder="Search name, phone, or RES-001" value="<?= e($search) ?>">
		<select name="status">
			<option value="">All statuses</option>
			<?php foreach ($validStatuses as $s): ?>
				<option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>>
					<?= e(ucwords(str_replace('_', ' ', $s))) ?>
				</option>
			<?php endforeach; ?>
		</select>
		<input type="date" name="date" value="<?= e($dateFilter) ?>">
		<button type="submit" class="btn-sm"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
		<a href="reservations.php" class="btn-sm gray">Reset</a>
	</form>

	<?php if (empty($reservations)): ?>
		<p class="empty-text">No reservations found.</p>
	<?php else: ?>
		<div class="table-wrap">
			<table class="data-table">
				<thead>
					<tr>
						<th>ID</th>
						<th>Customer</th>
						<th>Type</th>
						<th>Qty</th>
						<th>Date</th>
						<th>Time</th>
						<th>Status</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($reservations as $r): ?>
						<tr>
							<td><?= e(formatReservationId($r['reservation_id'])) ?></td>
							<td><?= e($r['full_name']) ?><br><small><?= e($r['phone']) ?></small></td>
							<td><?= e(typeLabel($r['reservation_type'])) ?></td>
							<td><?= e($r['quantity']) ?></td>
							<td><?= e(date('M d, Y', strtotime($r['reservation_date']))) ?></td>
							<td><?= e(ucfirst($r['time_slot'])) ?></td>
							<td><?= statusBadge($r['status']) ?></td>
							<td class="actions-cell">
								<a href="reservation-view.php?id=<?= (int) $r['reservation_id'] ?>" class="btn-sm">View</a>
								<?php if ($r['status'] === 'pending'): ?>
									<?= actionForm($r['reservation_id'], 'confirm', 'Confirm', 'success', 'Confirm this reservation?') ?>
								<?php endif; ?>
								<?php if (in_array($r['status'], ['confirmed', 'scheduled', 'out_for_delivery'])): ?>
									<?= actionForm($r['reservation_id'], 'complete', 'Complete', 'success', 'Mark this reservation as completed?') ?>
								<?php endif; ?>
								<?php if (in_array($r['status'], ['pending', 'confirmed', 'scheduled'])): ?>
									<a href="reservation-edit.php?id=<?= (int) $r['reservation_id'] ?>" class="btn-sm warning">Edit</a>
									<?= actionForm($r['reservation_id'], 'cancel', 'Cancel', 'danger', 'Cancel this reservation?') ?>
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