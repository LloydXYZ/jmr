<?php
$pageTitle = 'Inventory';
$rows = [
	['Oct 05, 2026', 'Stock added', '+50', 'Admin'],
	['Oct 05, 2026', 'Reserved (RES-004)', '-5', 'System'],
	['Oct 06, 2026', 'Delivered (RES-004)', '-5', 'Staff']
];
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/navbar.php';
?>
<h1 class="page-title">Inventory (20-liter gallons)</h1>
<div class="alert alert-info">UI preview with sample data.</div>
<div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation"></i> Low stock warning appears here when
	gallons fall below the limit.</div>

<div class="card-grid">
	<div class="stat-card">
		<div class="icon"><i class="fa-solid fa-bottle-water"></i></div>
		<div>
			<div class="label">Available</div>
			<div class="value">45</div>
		</div>
	</div>
	<div class="stat-card">
		<div class="icon orange"><i class="fa-solid fa-bookmark"></i></div>
		<div>
			<div class="label">Reserved</div>
			<div class="value">5</div>
		</div>
	</div>
	<div class="stat-card">
		<div class="icon green"><i class="fa-solid fa-truck"></i></div>
		<div>
			<div class="label">Delivered / Used</div>
			<div class="value">120</div>
		</div>
	</div>
</div>

<div class="panel form-panel">
	<h3>Add / Deduct Stock</h3>
	<form onsubmit="alert('Coming soon'); return false;">
		<div class="form-row">
			<div class="form-group"><label>Action</label><select>
					<option>Add stock</option>
					<option>Deduct stock</option>
				</select></div>
			<div class="form-group"><label>Quantity</label><input type="number" min="1"></div>
		</div>
		<div class="form-group"><label>Remarks</label><input type="text"></div>
		<button class="btn-inline">Save</button>
	</form>
</div>

<div class="panel">
	<h3>Inventory History</h3>
	<div class="table-wrap">
		<table class="data-table">
			<thead>
				<tr>
					<th>Date</th>
					<th>Description</th>
					<th>Change</th>
					<th>By</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($rows as $r): ?>
					<tr>
						<td><?= e($r[0]) ?></td>
						<td><?= e($r[1]) ?></td>
						<td><?= e($r[2]) ?></td>
						<td><?= e($r[3]) ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>