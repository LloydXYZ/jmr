<?php
require_once '../config/database.php';
require_once '../includes/auth-check.php';
requireRole(['staff']);

$pageTitle = 'Staff Dashboard';

$todayReservations = $pdo->query(
	"SELECT COUNT(*) FROM reservations WHERE reservation_date = CURDATE() AND status <> 'cancelled'"
)->fetchColumn();

$pendingReservations = $pdo->query(
	"SELECT COUNT(*) FROM reservations WHERE status = 'pending'"
)->fetchColumn();

$todayDeliveries = $pdo->query(
	"SELECT COUNT(*) FROM deliveries WHERE delivery_date = CURDATE() AND status <> 'cancelled'"
)->fetchColumn();

$awaitingCount = $pdo->query(
	"SELECT COUNT(*) FROM reservations WHERE reservation_type = 'delivery' AND status = 'confirmed'"
)->fetchColumn();

$pendingList = $pdo->query(
	"SELECT r.*, u.full_name
     FROM reservations r
     JOIN customers c ON r.customer_id = c.customer_id
     JOIN users u ON c.user_id = u.user_id
     WHERE r.status = 'pending'
     ORDER BY r.reservation_date ASC, r.reservation_id ASC
     LIMIT 5"
)->fetchAll();

$todayList = $pdo->query(
	"SELECT d.*, r.quantity, u.full_name AS customer_name, e.full_name AS staff_name
     FROM deliveries d
     JOIN reservations r ON d.reservation_id = r.reservation_id
     JOIN customers c ON r.customer_id = c.customer_id
     JOIN users u ON c.user_id = u.user_id
     JOIN employees e ON d.employee_id = e.employee_id
     WHERE d.delivery_date = CURDATE()
     ORDER BY FIELD(d.time_slot, 'morning', 'noon', 'afternoon'), d.delivery_id"
)->fetchAll();

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/navbar.php';
?>

<h1 class="page-title">Welcome, <?= e($_SESSION['full_name']) ?></h1>

<div class="card-grid">
		<div class="stat-card">
				<div class="icon"><i class="fa-solid fa-calendar-check"></i></div>
				<div><div class="label">Today's Reservations</div><div class="value"><?= (int) $todayReservations ?></div></div>
		</div>
		<div class="stat-card">
				<div class="icon orange"><i class="fa-solid fa-clock"></i></div>
				<div><div class="label">Pending Reservations</div><div class="value"><?= (int) $pendingReservations ?></div></div>
		</div>
		<div class="stat-card">
				<div class="icon green"><i class="fa-solid fa-truck"></i></div>
				<div><div class="label">Today's Deliveries</div><div class="value"><?= (int) $todayDeliveries ?></div></div>
		</div>
		<div class="stat-card">
				<div class="icon red"><i class="fa-solid fa-calendar-plus"></i></div>
				<div><div class="label">Awaiting Scheduling</div><div class="value"><?= (int) $awaitingCount ?></div></div>
		</div>
</div>

<div class="panel">
		<h3>Pending Reservations (need confirming)</h3>
		<?php if (empty($pendingList)): ?>
					<p class="empty-text">No pending reservations.</p>
		<?php else: ?>
					<div class="table-wrap">
							<table class="data-table">
									<thead>
											<tr><th>ID</th><th>Customer</th><th>Qty</th><th>Type</th><th>Date</th><th>Status</th></tr>
									</thead>
									<tbody>
									<?php foreach ($pendingList as $r): ?>
												<tr>
														<td><a href="reservation-view.php?id=<?= (int) $r['reservation_id'] ?>"><?= e(formatReservationId($r['reservation_id'])) ?></a></td>
														<td><?= e($r['full_name']) ?></td>
														<td><?= e($r['quantity']) ?></td>
														<td><?= e(typeLabel($r['reservation_type'])) ?></td>
														<td><?= e(date('M d, Y', strtotime($r['reservation_date']))) ?></td>
														<td><?= statusBadge($r['status']) ?></td>
												</tr>
									<?php endforeach; ?>
									</tbody>
							</table>
					</div>
					<p style="margin-top:12px;"><a href="reservations.php?status=pending">View all pending &rarr;</a></p>
		<?php endif; ?>
</div>

<div class="panel">
		<h3>Today's Deliveries</h3>
		<?php if (empty($todayList)): ?>
					<p class="empty-text">No deliveries scheduled for today.</p>
		<?php else: ?>
					<div class="table-wrap">
							<table class="data-table">
									<thead>
											<tr><th>Customer</th><th>Qty</th><th>Time</th><th>Delivery Staff</th><th>Status</th></tr>
									</thead>
									<tbody>
									<?php foreach ($todayList as $d): ?>
												<tr>
														<td><?= e($d['customer_name']) ?></td>
														<td><?= e($d['quantity']) ?></td>
														<td><?= e(ucfirst($d['time_slot'])) ?></td>
														<td><?= e($d['staff_name']) ?></td>
														<td><?= deliveryBadge($d['status']) ?></td>
												</tr>
									<?php endforeach; ?>
									</tbody>
							</table>
					</div>
					<p style="margin-top:12px;"><a href="deliveries.php">View all deliveries &rarr;</a></p>
		<?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>