<?php
$pageTitle = 'Payments';
$rows = [
	['PAY-001', 'RES-004', 'Maria Santos', 300, 'Paid', 'Oct 10, 2026'],
	['—', 'RES-005', 'Juan Cruz', 150, 'Unpaid', '—'],
	['PAY-002', 'RES-006', 'Ana Reyes', 100, 'Paid', 'Oct 11, 2026']
];
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/navbar.php';
?>
<h1 class="page-title">Payments (Cash on Delivery)</h1>
<div class="alert alert-info">UI preview with sample data. Payment is COD only.</div>

<div class="card-grid">
	<div class="stat-card">
		<div class="icon green"><i class="fa-solid fa-peso-sign"></i></div>
		<div>
			<div class="label">Total Collected</div>
			<div class="value">₱400.00</div>
		</div>
	</div>
	<div class="stat-card">
		<div class="icon red"><i class="fa-solid fa-file-invoice-dollar"></i></div>
		<div>
			<div class="label">Unpaid</div>
			<div class="value">₱150.00</div>
		</div>
	</div>
</div>

<div class="panel">
	<form class="filter-bar" onsubmit="alert('Coming soon'); return false;">
		<input type="text" placeholder="Search customer or RES-001">
		<select>
			<option>All statuses</option>
			<option>Unpaid</option>
			<option>Paid</option>
		</select>
		<input type="date"><input type="date">
		<button class="btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
	</form>
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
					<th>Date Paid</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($rows as $r): ?>
					<tr>
						<td><?= e($r[0]) ?></td>
						<td><?= e($r[1]) ?></td>
						<td><?= e($r[2]) ?></td>
						<td>₱<?= number_format($r[3], 2) ?></td>
						<td>COD</td>
						<td><span class="badge <?= $r[4] === 'Paid' ? 'badge-success' : 'badge-danger' ?>"><?= e($r[4]) ?></span></td>
						<td><?= e($r[5]) ?></td>
						<td><?php if ($r[4] === 'Unpaid'): ?><button class="btn-sm success" onclick="alert('Coming soon')">Record
									Payment</button><?php endif; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>