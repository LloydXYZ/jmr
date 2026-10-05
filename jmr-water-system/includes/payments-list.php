<?php
// includes/payments-list.php
// Shared by admin/payments.php and staff/payments.php

$pageTitle = 'Payments';

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['from'] ?? '';
$dateTo = $_GET['to'] ?? '';

$isDate = function ($d) {
	$o = DateTime::createFromFormat('Y-m-d', $d);
	return $o && $o->format('Y-m-d') === $d;
};
if (!$isDate($dateFrom)) {
	$dateFrom = '';
}
if (!$isDate($dateTo)) {
	$dateTo = '';
}

// Confirmed (or later) reservations, with their payment if one exists
$sql = "SELECT r.reservation_id, r.reservation_type, r.quantity, r.total_amount,
               r.reservation_date, r.status AS reservation_status,
               u.full_name, c.phone,
               p.payment_id, p.amount AS paid_amount, p.payment_status, p.date_paid
        FROM reservations r
        JOIN customers c ON r.customer_id = c.customer_id
        JOIN users u ON c.user_id = u.user_id
        LEFT JOIN payments p ON p.reservation_id = r.reservation_id
        WHERE r.status IN ('confirmed', 'scheduled', 'out_for_delivery', 'completed')";
$params = [];

if ($search !== '') {
	$sql .= " AND (u.full_name LIKE ? OR c.phone LIKE ? OR r.reservation_id = ?)";
	$params[] = "%$search%";
	$params[] = "%$search%";
	$params[] = (int) preg_replace('/\D/', '', $search);
}

// Date = the date paid, or the reservation date if it is still unpaid
if ($dateFrom !== '') {
	$sql .= " AND DATE(COALESCE(p.date_paid, r.reservation_date)) >= ?";
	$params[] = $dateFrom;
}
if ($dateTo !== '') {
	$sql .= " AND DATE(COALESCE(p.date_paid, r.reservation_date)) <= ?";
	$params[] = $dateTo;
}

// Unpaid first, then paid (newest first)
$sql .= " ORDER BY CASE WHEN p.payment_status = 'paid' THEN 1 ELSE 0 END,
                   COALESCE(p.date_paid, r.reservation_date) DESC, r.reservation_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$allRows = $stmt->fetchAll();

// Totals use the search and date filters, but not the status filter
$totalCollected = 0;
$paidCount = 0;
$unpaidTotal = 0;
$unpaidCount = 0;
$rows = [];

foreach ($allRows as $row) {
	$isPaid = ($row['payment_status'] === 'paid');
	if ($isPaid) {
		$totalCollected += $row['paid_amount'];
		$paidCount++;
	} else {
		$unpaidTotal += $row['total_amount'];
		$unpaidCount++;
	}

	if ($statusFilter === 'paid' && !$isPaid) {
		continue;
	}
	if ($statusFilter === 'unpaid' && $isPaid) {
		continue;
	}
	$rows[] = $row;
}

$messages = [
	'recorded' => ['success', 'Payment recorded.'],
	'unpaid' => ['success', 'Payment marked as unpaid.'],
	'error' => ['danger', 'That action could not be completed.'],
];
$msg = $_GET['msg'] ?? '';

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/navbar.php';
?>

<h1 class="page-title">Payments (Cash on Delivery)</h1>

<?php if (isset($messages[$msg])): ?>
	<div class="alert alert-<?= $messages[$msg][0] ?>"><?= e($messages[$msg][1]) ?></div>
<?php endif; ?>

<div class="card-grid">
	<div class="stat-card">
		<div class="icon green"><i class="fa-solid fa-peso-sign"></i></div>
		<div>
			<div class="label">Total Collected</div>
			<div class="value"><?= e(peso($totalCollected)) ?></div>
		</div>
	</div>
	<div class="stat-card">
		<div class="icon"><i class="fa-solid fa-circle-check"></i></div>
		<div>
			<div class="label">Paid Transactions</div>
			<div class="value"><?= (int) $paidCount ?></div>
		</div>
	</div>
	<div class="stat-card">
		<div class="icon red"><i class="fa-solid fa-file-invoice-dollar"></i></div>
		<div>
			<div class="label">Unpaid Amount</div>
			<div class="value"><?= e(peso($unpaidTotal)) ?></div>
		</div>
	</div>
	<div class="stat-card">
		<div class="icon orange"><i class="fa-solid fa-hourglass-half"></i></div>
		<div>
			<div class="label">Unpaid Transactions</div>
			<div class="value"><?= (int) $unpaidCount ?></div>
		</div>
	</div>
</div>

<div class="panel">
	<form method="GET" class="filter-bar">
		<input type="text" name="search" placeholder="Search customer, phone, or RES-001" value="<?= e($search) ?>">
		<select name="status">
			<option value="">All statuses</option>
			<option value="unpaid" <?= $statusFilter === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
			<option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
		</select>
		<label>From</label><input type="date" name="from" value="<?= e($dateFrom) ?>">
		<label>To</label><input type="date" name="to" value="<?= e($dateTo) ?>">
		<button type="submit" class="btn-sm"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
		<a href="payments.php" class="btn-sm gray">Reset</a>
	</form>
	<p class="form-hint" style="margin-bottom:12px;">
		The date filter uses the date paid, or the reservation date if the payment is still unpaid.
		The cards above follow the search and date filters.
	</p>

	<?php if (empty($rows)): ?>
		<p class="empty-text">No payments found.</p>
	<?php else: ?>
		<div class="table-wrap">
			<table class="data-table">
				<thead>
					<tr>
						<th>Payment</th>
						<th>Reservation</th>
						<th>Customer</th>
						<th>Amount</th>
						<th>Method</th>
						<th>Status</th>
						<th>Date</th>
						<th>Reservation Status</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($rows as $row):
						$isPaid = ($row['payment_status'] === 'paid');
						?>
						<tr>
							<td><?= $isPaid ? e(formatPaymentId($row['payment_id'])) : '—' ?></td>
							<td><a
									href="reservation-view.php?id=<?= (int) $row['reservation_id'] ?>"><?= e(formatReservationId($row['reservation_id'])) ?></a>
							</td>
							<td><?= e($row['full_name']) ?><br><small><?= e($row['phone']) ?></small></td>
							<td><?= e(peso($isPaid ? $row['paid_amount'] : $row['total_amount'])) ?></td>
							<td>COD</td>
							<td><?= paymentBadge($isPaid ? 'paid' : 'unpaid') ?></td>
							<td>
								<?php if ($isPaid): ?>
									<?= e(date('M d, Y h:i A', strtotime($row['date_paid']))) ?>
								<?php else: ?>
									<small>Due <?= e(date('M d, Y', strtotime($row['reservation_date']))) ?></small>
								<?php endif; ?>
							</td>
							<td><?= statusBadge($row['reservation_status']) ?></td>
							<td class="actions-cell">
								<?php if (!$isPaid): ?>
									<?= paymentActionForm(
										$row['reservation_id'],
										'record',
										'Record Payment',
										'success',
										'Record cash payment of ' . peso($row['total_amount']) . '?'
									) ?>
								<?php elseif ($_SESSION['role'] === 'admin'): ?>
									<?= paymentActionForm(
										$row['reservation_id'],
										'unpaid',
										'Mark Unpaid',
										'warning',
										'Mark this payment as unpaid?'
									) ?>
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