<?php
require_once '../config/database.php';
require_once '../includes/auth-check.php';
requireRole(['admin']);

$pageTitle = 'Income';

// ---- 1. Fixed summary cards (not affected by the filter) ----
$sum = $pdo->query(
	"SELECT
        COALESCE(SUM(CASE WHEN DATE(date_paid) = CURDATE() THEN amount END), 0) AS today,
        COALESCE(SUM(CASE WHEN YEARWEEK(date_paid, 1) = YEARWEEK(CURDATE(), 1) THEN amount END), 0) AS week,
        COALESCE(SUM(CASE WHEN YEAR(date_paid) = YEAR(CURDATE())
                           AND MONTH(date_paid) = MONTH(CURDATE()) THEN amount END), 0) AS month,
        COALESCE(SUM(amount), 0) AS total,
        COUNT(*) AS paid_count
     FROM payments
     WHERE payment_status = 'paid'"
)->fetch();

// Unpaid = confirmed (or later) reservations without a paid payment
$unpaidAll = $pdo->query(
	"SELECT COUNT(*) AS cnt, COALESCE(SUM(r.total_amount), 0) AS amt
     FROM reservations r
     LEFT JOIN payments p ON p.reservation_id = r.reservation_id
     WHERE r.status IN ('confirmed', 'scheduled', 'out_for_delivery', 'completed')
       AND (p.payment_status IS NULL OR p.payment_status <> 'paid')"
)->fetch();

// ---- 2. Date filter (default: from the 1st of this month until today) ----
$isDate = function ($d) {
	$o = DateTime::createFromFormat('Y-m-d', $d);
	return $o && $o->format('Y-m-d') === $d;
};

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
if (!$isDate($from)) {
	$from = date('Y-m-01');
}
if (!$isDate($to)) {
	$to = date('Y-m-d');
}
if ($from > $to) {
	$tmp = $from;
	$from = $to;
	$to = $tmp;
}   // swap if typed backwards

// ---- 3. Income per day (paid payments, by date paid) ----
$stmt = $pdo->prepare(
	"SELECT DATE(date_paid) AS d, COUNT(*) AS cnt, SUM(amount) AS amt
     FROM payments
     WHERE payment_status = 'paid' AND DATE(date_paid) BETWEEN ? AND ?
     GROUP BY DATE(date_paid)"
);
$stmt->execute([$from, $to]);
$paidRows = $stmt->fetchAll();

// ---- 4. Unpaid per day (by reservation date) ----
$stmt = $pdo->prepare(
	"SELECT r.reservation_date AS d, COUNT(*) AS cnt, SUM(r.total_amount) AS amt
     FROM reservations r
     LEFT JOIN payments p ON p.reservation_id = r.reservation_id
     WHERE r.status IN ('confirmed', 'scheduled', 'out_for_delivery', 'completed')
       AND (p.payment_status IS NULL OR p.payment_status <> 'paid')
       AND r.reservation_date BETWEEN ? AND ?
     GROUP BY r.reservation_date"
);
$stmt->execute([$from, $to]);
$unpaidRows = $stmt->fetchAll();

// ---- 5. Merge both lists by date ----
$days = [];
$blank = ['paid_cnt' => 0, 'paid_amt' => 0, 'unpaid_cnt' => 0, 'unpaid_amt' => 0];

foreach ($paidRows as $r) {
	$days[$r['d']] = $blank;
	$days[$r['d']]['paid_cnt'] = (int) $r['cnt'];
	$days[$r['d']]['paid_amt'] = (float) $r['amt'];
}
foreach ($unpaidRows as $r) {
	if (!isset($days[$r['d']])) {
		$days[$r['d']] = $blank;
	}
	$days[$r['d']]['unpaid_cnt'] = (int) $r['cnt'];
	$days[$r['d']]['unpaid_amt'] = (float) $r['amt'];
}
krsort($days);   // newest date first

$rangePaidCount = array_sum(array_column($days, 'paid_cnt'));
$rangePaidAmount = array_sum(array_column($days, 'paid_amt'));
$rangeUnpaidCount = array_sum(array_column($days, 'unpaid_cnt'));
$rangeUnpaidAmt = array_sum(array_column($days, 'unpaid_amt'));

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/navbar.php';
?>

<h1 class="page-title">Income Monitoring</h1>

<div class="card-grid">
		<div class="stat-card">
				<div class="icon green"><i class="fa-solid fa-peso-sign"></i></div>
				<div><div class="label">Today</div><div class="value"><?= e(peso($sum['today'])) ?></div></div>
		</div>
		<div class="stat-card">
				<div class="icon green"><i class="fa-solid fa-calendar-week"></i></div>
				<div><div class="label">This Week</div><div class="value"><?= e(peso($sum['week'])) ?></div></div>
		</div>
		<div class="stat-card">
				<div class="icon green"><i class="fa-solid fa-coins"></i></div>
				<div><div class="label">This Month</div><div class="value"><?= e(peso($sum['month'])) ?></div></div>
		</div>
		<div class="stat-card">
				<div class="icon"><i class="fa-solid fa-sack-dollar"></i></div>
				<div><div class="label">Total Income</div><div class="value"><?= e(peso($sum['total'])) ?></div></div>
		</div>
		<div class="stat-card">
				<div class="icon"><i class="fa-solid fa-circle-check"></i></div>
				<div><div class="label">Paid Transactions</div><div class="value"><?= (int) $sum['paid_count'] ?></div></div>
		</div>
		<div class="stat-card">
				<div class="icon red"><i class="fa-solid fa-file-invoice-dollar"></i></div>
				<div>
						<div class="label">Unpaid (<?= (int) $unpaidAll['cnt'] ?> transactions)</div>
						<div class="value"><?= e(peso($unpaidAll['amt'])) ?></div>
				</div>
		</div>
</div>

<div class="panel">
		<h3>Income by Date</h3>
		<form method="GET" class="filter-bar no-print">
				<label>From</label><input type="date" name="from" value="<?= e($from) ?>">
				<label>To</label><input type="date" name="to" value="<?= e($to) ?>">
				<button type="submit" class="btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
				<a href="income.php" class="btn-sm gray">Reset</a>
				<button type="button" class="btn-sm gray" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
		</form>

		<p style="margin-bottom:12px;">
				<strong><?= e(date('M d, Y', strtotime($from))) ?></strong> to
				<strong><?= e(date('M d, Y', strtotime($to))) ?></strong>:
				income <strong><?= e(peso($rangePaidAmount)) ?></strong> from <?= (int) $rangePaidCount ?> paid transaction(s),
				unpaid <strong><?= e(peso($rangeUnpaidAmt)) ?></strong> (<?= (int) $rangeUnpaidCount ?>).
		</p>
		<p class="form-hint" style="margin-bottom:12px;">
				Income is counted on the date it was paid. Unpaid amounts are counted on the reservation date.
		</p>

		<?php if (empty($days)): ?>
					<p class="empty-text">No income or unpaid transactions in this date range.</p>
		<?php else: ?>
					<div class="table-wrap">
							<table class="data-table">
									<thead>
											<tr>
													<th>Date</th><th>Paid Transactions</th><th>Income Received</th>
													<th>Unpaid Transactions</th><th>Unpaid Amount</th>
											</tr>
									</thead>
									<tbody>
									<?php foreach ($days as $date => $d): ?>
												<tr>
														<td><?= e(date('M d, Y (D)', strtotime($date))) ?></td>
														<td><?= (int) $d['paid_cnt'] ?></td>
														<td><strong><?= e(peso($d['paid_amt'])) ?></strong></td>
														<td><?= (int) $d['unpaid_cnt'] ?></td>
														<td><?= e(peso($d['unpaid_amt'])) ?></td>
												</tr>
									<?php endforeach; ?>
									</tbody>
									<tfoot>
											<tr>
													<th>Total</th>
													<th><?= (int) $rangePaidCount ?></th>
													<th><?= e(peso($rangePaidAmount)) ?></th>
													<th><?= (int) $rangeUnpaidCount ?></th>
													<th><?= e(peso($rangeUnpaidAmt)) ?></th>
											</tr>
									</tfoot>
							</table>
					</div>
		<?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>