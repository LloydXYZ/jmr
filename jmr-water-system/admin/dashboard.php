<?php
require_once '../config/database.php';
require_once '../includes/auth-check.php';
requireRole(['admin']);

$pageTitle = 'Admin Dashboard';

$totalCustomers = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$totalEmployees = $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn();

$todayReservations = $pdo->query(
	"SELECT COUNT(*) FROM reservations WHERE reservation_date = CURDATE() AND status <> 'cancelled'"
)->fetchColumn();

$pendingReservations = $pdo->query(
	"SELECT COUNT(*) FROM reservations WHERE status = 'pending'"
)->fetchColumn();

$todayDeliveries = $pdo->query(
	"SELECT COUNT(*) FROM deliveries WHERE delivery_date = CURDATE() AND status <> 'cancelled'"
)->fetchColumn();

// Unpaid = confirmed (or later) reservations with no paid payment
$unpaidTransactions = $pdo->query(
	"SELECT COUNT(*)
     FROM reservations r
     LEFT JOIN payments p ON p.reservation_id = r.reservation_id
     WHERE r.status IN ('confirmed', 'scheduled', 'out_for_delivery', 'completed')
       AND (p.payment_status IS NULL OR p.payment_status <> 'paid')"
)->fetchColumn();

// Income = payments recorded as paid
$todayIncome = $pdo->query(
	"SELECT COALESCE(SUM(amount), 0) FROM payments
     WHERE payment_status = 'paid' AND DATE(date_paid) = CURDATE()"
)->fetchColumn();

$monthlyIncome = $pdo->query(
	"SELECT COALESCE(SUM(amount), 0) FROM payments
     WHERE payment_status = 'paid'
       AND YEAR(date_paid) = YEAR(CURDATE()) AND MONTH(date_paid) = MONTH(CURDATE())"
)->fetchColumn();

$inv = getInventorySummary($pdo);

$recent = $pdo->query(
	"SELECT r.*, u.full_name
     FROM reservations r
     JOIN customers c ON r.customer_id = c.customer_id
     JOIN users u ON c.user_id = u.user_id
     ORDER BY r.created_at DESC, r.reservation_id DESC
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

$recentPayments = $pdo->query(
	"SELECT p.amount, p.payment_status, p.date_paid, u.full_name
     FROM payments p
     JOIN reservations r ON p.reservation_id = r.reservation_id
     JOIN customers c ON r.customer_id = c.customer_id
     JOIN users u ON c.user_id = u.user_id
     WHERE p.payment_status = 'paid'
     ORDER BY p.date_paid DESC
     LIMIT 5"
)->fetchAll();

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/navbar.php';
?>

<h1 class="page-title">Welcome, <?= e($_SESSION['full_name']) ?></h1>

<?php if ($inv['is_low']): ?>
	<div class="alert alert-warning">
		<i class="fa-solid fa-triangle-exclamation"></i>
		Low stock: only <strong><?= (int) $inv['available'] ?></strong> gallon(s) available.
		<a href="inventory.php">Go to Inventory</a>
	</div>
<?php endif; ?>

<div class="card-grid">
	<div class="stat-card">
		<div class="icon"><i class="fa-solid fa-calendar-check"></i></div>
		<div>
			<div class="label">Today's Reservations</div>
			<div class="value"><?= (int) $todayReservations ?></div>
		</div>
	</div>
	<div class="stat-card">
		<div class="icon green"><i class="fa-solid fa-truck"></i></div>
		<div>
			<div class="label">Today's Deliveries</div>
			<div class="value"><?= (int) $todayDeliveries ?></div>
		</div>
	</div>
	<div class="stat-card">
		<div class="icon orange"><i class="fa-solid fa-clock"></i></div>
		<div>
			<div class="label">Pending Reservations</div>
			<div class="value"><?= (int) $pendingReservations ?></div>
		</div>
	</div>
	<div class="stat-card">
		<div class="icon red"><i class="fa-solid fa-file-invoice-dollar"></i></div>
		<div>
			<div class="label">Unpaid Transactions</div>
			<div class="value"><?= (int) $unpaidTransactions ?></div>
		</div>
	</div>
	<div class="stat-card">
		<div class="icon"><i class="fa-solid fa-bottle-water"></i></div>
		<div>
			<div class="label">Available Gallons</div>
			<div class="value"><?= (int) $inv['available'] ?></div>
		</div>
	</div>
	<div class="stat-card">
		<div class="icon green"><i class="fa-solid fa-peso-sign"></i></div>
		<div>
			<div class="label">Today's Income</div>
			<div class="value"><?= e(peso($todayIncome)) ?></div>
		</div>
	</div>
	<div class="stat-card">
		<div class="icon green"><i class="fa-solid fa-coins"></i></div>
		<div>
			<div class="label">Monthly Income</div>
			<div class="value"><?= e(peso($monthlyIncome)) ?></div>
		</div>
	</div>
	<div class="stat-card">
		<div class="icon"><i class="fa-solid fa-users"></i></div>
		<div>
			<div class="label">Total Customers</div>
			<div class="value"><?= (int) $totalCustomers ?></div>
		</div>
	</div>
	<div class="stat-card">
		<div class="icon orange"><i class="fa-solid fa-id-badge"></i></div>
		<div>
			<div class="label">Total Employees</div>
			<div class="value"><?= (int) $totalEmployees ?></div>
		</div>
	</div>
</div>

<div class="panel">
	<h3>Recent Reservations</h3>
	<?php if (empty($recent)): ?>
		<p class="empty-text">No reservations yet.</p>
	<?php else: ?>
		<div class="table-wrap">
			<table class="data-table">
				<thead>
					<tr>
						<th>ID</th>
						<th>Customer</th>
						<th>Qty</th>
						<th>Type</th>
						<th>Date</th>
						<th>Status</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($recent as $r): ?>
						<tr>
							<td><a
									href="reservation-view.php?id=<?= (int) $r['reservation_id'] ?>"><?= e(formatReservationId($r['reservation_id'])) ?></a>
							</td>
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
		<p style="margin-top:12px;"><a href="reservations.php">View all reservations &rarr;</a></p>
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
					<tr>
						<th>Customer</th>
						<th>Qty</th>
						<th>Time</th>
						<th>Delivery Staff</th>
						<th>Status</th>
					</tr>
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

<div class="panel">
	<h3>Recent Payments</h3>
	<?php if (empty($recentPayments)): ?>
		<p class="empty-text">No payments recorded yet.</p>
	<?php else: ?>
		<div class="table-wrap">
			<table class="data-table">
				<thead>
					<tr>
						<th>Customer</th>
						<th>Amount</th>
						<th>Status</th>
						<th>Date</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($recentPayments as $p): ?>
						<tr>
							<td><?= e($p['full_name']) ?></td>
							<td><?= e(peso($p['amount'])) ?></td>
							<td><?= paymentBadge($p['payment_status']) ?></td>
							<td><?= e(date('M d, Y h:i A', strtotime($p['date_paid']))) ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p style="margin-top:12px;"><a href="payments.php">View all payments &rarr;</a></p>
	<?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>