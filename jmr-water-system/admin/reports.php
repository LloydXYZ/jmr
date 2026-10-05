<?php
require_once '../config/database.php';
require_once '../includes/auth-check.php';
requireRole(['admin']);

$pageTitle = 'Reports';

$reportTypes = [
	'reservation' => ['Reservation Report', 'fa-calendar-check'],
	'delivery' => ['Delivery Report', 'fa-truck'],
	'payment' => ['Payment Report', 'fa-money-bill-wave'],
	'income' => ['Income Report', 'fa-peso-sign'],
	'inventory' => ['Inventory Report', 'fa-bottle-water'],
	'payroll' => ['Payroll Report', 'fa-wallet'],
];

$report = $_GET['report'] ?? 'reservation';
if (!isset($reportTypes[$report])) {
	$report = 'reservation';
}

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
}

$search = trim($_GET['search'] ?? '');
$group = $_GET['group'] ?? 'day';
if (!in_array($group, ['day', 'week', 'month'], true)) {
	$group = 'day';
}

$hasSearch = in_array($report, ['reservation', 'delivery', 'payment', 'payroll'], true);
$like = "%$search%";
$searchId = (int) preg_replace('/\D/', '', $search);

$summary = [];   // [label, value]
$headers = [];   // table headings
$tableRows = [];   // each row = array of cells (already escaped HTML)
$footer = '';
$dateNote = '';

// =============== RESERVATION REPORT ===============
if ($report === 'reservation') {
	$dateNote = 'Filtered by reservation date.';

	$sql = "SELECT r.*, u.full_name
            FROM reservations r
            JOIN customers c ON r.customer_id = c.customer_id
            JOIN users u ON c.user_id = u.user_id
            WHERE r.reservation_date BETWEEN ? AND ?";
	$params = [$from, $to];
	if ($search !== '') {
		$sql .= " AND (u.full_name LIKE ? OR r.reservation_id = ?)";
		$params[] = $like;
		$params[] = $searchId;
	}
	$sql .= " ORDER BY r.reservation_date DESC, r.reservation_id DESC";

	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);
	$data = $stmt->fetchAll();

	$c = array_count_values(array_column($data, 'status'));
	$summary = [
		['Total Reservations', count($data)],
		['Completed', $c['completed'] ?? 0],
		['Pending', $c['pending'] ?? 0],
		['Confirmed', $c['confirmed'] ?? 0],
		['Scheduled / Out for Delivery', ($c['scheduled'] ?? 0) + ($c['out_for_delivery'] ?? 0)],
		['Cancelled', $c['cancelled'] ?? 0],
	];
	$headers = ['ID', 'Customer', 'Type', 'Qty', 'Date', 'Time', 'Amount', 'Status'];
	foreach ($data as $r) {
		$tableRows[] = [
			e(formatReservationId($r['reservation_id'])),
			e($r['full_name']),
			e(typeLabel($r['reservation_type'])),
			e($r['quantity']),
			e(date('M d, Y', strtotime($r['reservation_date']))),
			e(ucfirst($r['time_slot'])),
			e(peso($r['total_amount'])),
			statusBadge($r['status']),
		];
	}

	// =============== DELIVERY REPORT ===============
} elseif ($report === 'delivery') {
	$dateNote = 'Filtered by delivery date.';

	$sql = "SELECT d.*, r.quantity, u.full_name AS customer_name, e.full_name AS staff_name
            FROM deliveries d
            JOIN reservations r ON d.reservation_id = r.reservation_id
            JOIN customers c ON r.customer_id = c.customer_id
            JOIN users u ON c.user_id = u.user_id
            JOIN employees e ON d.employee_id = e.employee_id
            WHERE d.delivery_date BETWEEN ? AND ?";
	$params = [$from, $to];
	if ($search !== '') {
		$sql .= " AND (u.full_name LIKE ? OR e.full_name LIKE ? OR r.reservation_id = ?)";
		$params[] = $like;
		$params[] = $like;
		$params[] = $searchId;
	}
	$sql .= " ORDER BY d.delivery_date DESC, FIELD(d.time_slot, 'morning', 'noon', 'afternoon'), d.delivery_id DESC";

	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);
	$data = $stmt->fetchAll();

	$delivered = 0;
	$cancelled = 0;
	$upcoming = 0;
	foreach ($data as $d) {
		if ($d['status'] === 'delivered') {
			$delivered++;
		}
		if ($d['status'] === 'cancelled') {
			$cancelled++;
		}
		if (in_array($d['status'], ['scheduled', 'out_for_delivery'], true) && $d['delivery_date'] >= date('Y-m-d')) {
			$upcoming++;
		}
	}
	$summary = [
		['Total Deliveries', count($data)],
		['Completed (Delivered)', $delivered],
		['Cancelled', $cancelled],
		['Upcoming', $upcoming],
	];
	$headers = ['Reservation', 'Customer', 'Qty', 'Date', 'Time', 'Delivery Staff', 'Status'];
	foreach ($data as $d) {
		$tableRows[] = [
			e(formatReservationId($d['reservation_id'])),
			e($d['customer_name']),
			e($d['quantity']),
			e(date('M d, Y', strtotime($d['delivery_date']))),
			e(ucfirst($d['time_slot'])),
			e($d['staff_name']),
			deliveryBadge($d['status']),
		];
	}

	// =============== PAYMENT REPORT ===============
} elseif ($report === 'payment') {
	$dateNote = 'Filtered by the date paid, or by the reservation date if still unpaid.';

	$sql = "SELECT r.reservation_id, r.total_amount, r.reservation_date, r.status AS reservation_status,
                   u.full_name, p.amount AS paid_amount, p.payment_status, p.date_paid
            FROM reservations r
            JOIN customers c ON r.customer_id = c.customer_id
            JOIN users u ON c.user_id = u.user_id
            LEFT JOIN payments p ON p.reservation_id = r.reservation_id
            WHERE r.status IN ('confirmed', 'scheduled', 'out_for_delivery', 'completed')
              AND DATE(COALESCE(p.date_paid, r.reservation_date)) BETWEEN ? AND ?";
	$params = [$from, $to];
	if ($search !== '') {
		$sql .= " AND (u.full_name LIKE ? OR r.reservation_id = ?)";
		$params[] = $like;
		$params[] = $searchId;
	}
	$sql .= " ORDER BY COALESCE(p.date_paid, r.reservation_date) DESC, r.reservation_id DESC";

	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);
	$data = $stmt->fetchAll();

	$collected = 0;
	$paidCount = 0;
	$unpaidAmt = 0;
	$unpaidCount = 0;
	foreach ($data as $p) {
		if ($p['payment_status'] === 'paid') {
			$collected += $p['paid_amount'];
			$paidCount++;
		} else {
			$unpaidAmt += $p['total_amount'];
			$unpaidCount++;
		}
	}
	$summary = [
		['Paid Transactions', $paidCount],
		['Unpaid Transactions', $unpaidCount],
		['Total Collected', peso($collected)],
		['Unpaid Amount', peso($unpaidAmt)],
	];
	$headers = ['Reservation', 'Customer', 'Amount', 'Method', 'Payment', 'Date', 'Reservation Status'];
	foreach ($data as $p) {
		$isPaid = ($p['payment_status'] === 'paid');
		$tableRows[] = [
			e(formatReservationId($p['reservation_id'])),
			e($p['full_name']),
			e(peso($isPaid ? $p['paid_amount'] : $p['total_amount'])),
			'COD',
			paymentBadge($isPaid ? 'paid' : 'unpaid'),
			$isPaid ? e(date('M d, Y h:i A', strtotime($p['date_paid']))) : e(date('M d, Y', strtotime($p['reservation_date']))),
			statusBadge($p['reservation_status']),
		];
	}

	// =============== INCOME REPORT ===============
} elseif ($report === 'income') {
	$dateNote = 'Only paid payments are counted, on the date they were paid. Weeks start on Monday.';

	// These three expressions are our own fixed text, never user input
	$exprs = [
		'day' => "DATE(date_paid)",
		'week' => "DATE_SUB(DATE(date_paid), INTERVAL WEEKDAY(date_paid) DAY)",
		'month' => "DATE_FORMAT(date_paid, '%Y-%m-01')",
	];
	$expr = $exprs[$group];

	$stmt = $pdo->prepare(
		"SELECT $expr AS period_start, COUNT(*) AS cnt, SUM(amount) AS amt
         FROM payments
         WHERE payment_status = 'paid' AND DATE(date_paid) BETWEEN ? AND ?
         GROUP BY period_start
         ORDER BY period_start DESC"
	);
	$stmt->execute([$from, $to]);
	$data = $stmt->fetchAll();

	$totalAmt = 0;
	$totalCnt = 0;
	foreach ($data as $d) {
		$totalAmt += $d['amt'];
		$totalCnt += $d['cnt'];
	}

	$summary = [
		['Total Income', peso($totalAmt)],
		['Paid Transactions', $totalCnt],
		['Periods Shown', count($data)],
	];

	$labels = ['day' => 'Day', 'week' => 'Week', 'month' => 'Month'];
	$headers = [$labels[$group], 'Paid Transactions', 'Income Received'];
	foreach ($data as $d) {
		$ts = strtotime($d['period_start']);
		if ($group === 'day') {
			$label = date('M d, Y (D)', $ts);
		} elseif ($group === 'week') {
			$label = 'Week of ' . date('M d, Y', $ts);
		} else {
			$label = date('F Y', $ts);
		}
		$tableRows[] = [e($label), (int) $d['cnt'], '<strong>' . e(peso($d['amt'])) . '</strong>'];
	}
	$footer = 'Total: ' . e(peso($totalAmt)) . ' from ' . (int) $totalCnt . ' paid transaction(s)';

	// =============== INVENTORY REPORT ===============
} elseif ($report === 'inventory') {
	$dateNote = 'The stock cards show the current stock. The movement list follows the date range.';

	$inv = getInventorySummary($pdo);

	$stmt = $pdo->prepare(
		"SELECT t.*, u.full_name
         FROM inventory_transactions t
         LEFT JOIN users u ON t.created_by = u.user_id
         WHERE DATE(t.created_at) BETWEEN ? AND ?
         ORDER BY t.created_at DESC, t.transaction_id DESC"
	);
	$stmt->execute([$from, $to]);
	$data = $stmt->fetchAll();

	$added = 0;
	$deducted = 0;
	$delivered = 0;
	foreach ($data as $t) {
		if ($t['transaction_type'] === 'add') {
			$added += $t['quantity'];
		}
		if ($t['transaction_type'] === 'deduct') {
			$deducted += $t['quantity'];
		}
		if ($t['transaction_type'] === 'deliver') {
			$delivered += $t['quantity'];
		}
	}
	$summary = [
		['In Stock (now)', $inv['on_hand']],
		['Reserved Gallons (now)', $inv['reserved']],
		['Available Gallons (now)', $inv['available']],
		['Delivered / Used (all time)', $inv['used']],
		['Added in this range', $added],
		['Deducted in this range', $deducted],
		['Delivered in this range', $delivered],
	];
	$headers = ['Date', 'Type', 'Change', 'Balance', 'Reservation', 'Remarks', 'By'];
	foreach ($data as $t) {
		$tableRows[] = [
			e(date('M d, Y h:i A', strtotime($t['created_at']))),
			inventoryTypeBadge($t['transaction_type']),
			'<strong>' . ($t['transaction_type'] === 'add' ? '+' : '-') . (int) $t['quantity'] . '</strong>',
			(int) $t['balance_after'],
			$t['reservation_id'] ? e(formatReservationId($t['reservation_id'])) : '—',
			e($t['remarks']),
			e($t['full_name'] ?? 'System'),
		];
	}

	// =============== PAYROLL REPORT ===============
} elseif ($report === 'payroll') {
	$dateNote = 'Filtered by payroll date.';

	$sql = "SELECT p.*, e.full_name, e.job_position
            FROM payroll p
            JOIN employees e ON p.employee_id = e.employee_id
            WHERE p.payroll_date BETWEEN ? AND ?";
	$params = [$from, $to];
	if ($search !== '') {
		$sql .= " AND (e.full_name LIKE ? OR e.employee_id = ?)";
		$params[] = $like;
		$params[] = $searchId;
	}
	$sql .= " ORDER BY p.payroll_date DESC, p.payroll_id DESC";

	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);
	$data = $stmt->fetchAll();

	$paid = 0;
	$unpaid = 0;
	foreach ($data as $p) {
		if ($p['payment_status'] === 'paid') {
			$paid += $p['amount'];
		} else {
			$unpaid += $p['amount'];
		}
	}
	$summary = [
		['Total Payroll (Paid)', peso($paid)],
		['Unpaid Payroll', peso($unpaid)],
		['Records', count($data)],
	];
	$headers = ['ID', 'Employee', 'Position', 'Period', 'Salary / Amount', 'Status', 'Payroll Date'];
	foreach ($data as $p) {
		$tableRows[] = [
			e(formatPayrollId($p['payroll_id'])),
			e($p['full_name']),
			e($p['job_position']),
			e($p['payroll_period']),
			e(peso($p['amount'])),
			payrollBadge($p['payment_status']),
			e(date('M d, Y', strtotime($p['payroll_date']))),
		];
	}
	$footer = 'Total payroll paid: ' . e(peso($paid)) . ' | Unpaid: ' . e(peso($unpaid));
}

$reportTitle = $reportTypes[$report][0];
$query = 'from=' . urlencode($from) . '&to=' . urlencode($to);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/navbar.php';
?>

<h1 class="page-title no-print">Reports</h1>

<div class="report-tabs no-print">
		<?php foreach ($reportTypes as $key => $t): ?>
					<a href="reports.php?report=<?= e($key) ?>&<?= $query ?>" class="<?= $key === $report ? 'active' : '' ?>">
							<i class="fa-solid <?= e($t[1]) ?>"></i> <?= e($t[0]) ?>
					</a>
		<?php endforeach; ?>
</div>

<div class="panel no-print">
		<form method="GET" class="filter-bar">
				<input type="hidden" name="report" value="<?= e($report) ?>">
				<?php if ($hasSearch): ?>
							<input type="text" name="search" placeholder="Search..." value="<?= e($search) ?>">
				<?php endif; ?>
				<?php if ($report === 'income'): ?>
							<select name="group">
									<option value="day"   <?= $group === 'day' ? 'selected' : '' ?>>Daily</option>
									<option value="week"  <?= $group === 'week' ? 'selected' : '' ?>>Weekly</option>
									<option value="month" <?= $group === 'month' ? 'selected' : '' ?>>Monthly</option>
							</select>
				<?php endif; ?>
				<label>From</label><input type="date" name="from" value="<?= e($from) ?>">
				<label>To</label><input type="date" name="to" value="<?= e($to) ?>">
				<button type="submit" class="btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
				<a href="reports.php?report=<?= e($report) ?>" class="btn-sm gray">Reset</a>
				<button type="button" class="btn-sm gray" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
		</form>
		<p class="form-hint"><?= e($dateNote) ?></p>
</div>

<div class="print-only">
		<h2>JMR Water Refilling Station</h2>
		<h3><?= e($reportTitle) ?></h3>
		<p>Period: <?= e(date('M d, Y', strtotime($from))) ?> to <?= e(date('M d, Y', strtotime($to))) ?>
			 | Generated: <?= e(date('M d, Y h:i A')) ?></p>
</div>

<div class="card-grid">
		<?php foreach ($summary as $s): ?>
					<div class="stat-card">
							<div class="icon"><i class="fa-solid fa-chart-column"></i></div>
							<div><div class="label"><?= e($s[0]) ?></div><div class="value"><?= e((string) $s[1]) ?></div></div>
					</div>
		<?php endforeach; ?>
</div>

<div class="panel">
		<h3><?= e($reportTitle) ?>: <?= e(date('M d, Y', strtotime($from))) ?> to <?= e(date('M d, Y', strtotime($to))) ?></h3>

		<?php if (empty($tableRows)): ?>
					<p class="empty-text">No records found for this report and date range.</p>
		<?php else: ?>
					<div class="table-wrap">
							<table class="data-table">
									<thead>
											<tr><?php foreach ($headers as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr>
									</thead>
									<tbody>
									<?php foreach ($tableRows as $row): ?>
												<tr><?php foreach ($row as $cell): ?><td><?= $cell ?></td><?php endforeach; ?></tr>
									<?php endforeach; ?>
									</tbody>
									<?php if ($footer !== ''): ?>
												<tfoot><tr><th colspan="<?= count($headers) ?>"><?= $footer ?></th></tr></tfoot>
									<?php endif; ?>
							</table>
					</div>
					<p class="form-hint" style="margin-top:10px;"><?= count($tableRows) ?> row(s)</p>
		<?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>