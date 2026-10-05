<?php
// includes/calendar.php
// Shared by admin/calendar.php and staff/calendar.php

$pageTitle = 'Delivery Calendar';

// ---- 1. Which month are we showing? (?month=2026-10) ----
$monthParam = $_GET['month'] ?? date('Y-m');
$first = DateTime::createFromFormat('!Y-m', $monthParam);   // "!" sets the day to the 1st
if (!$first || $first->format('Y-m') !== $monthParam) {
	$first = DateTime::createFromFormat('!Y-m', date('Y-m'));
}

$monthKey = $first->format('Y-m');
$daysInMonth = (int) $first->format('t');
$startWeekday = (int) $first->format('w');                  // 0 = Sunday
$startDate = $first->format('Y-m-01');
$endDate = $first->format('Y-m-t');

$prevMonth = (clone $first)->modify('-1 month')->format('Y-m');
$nextMonth = (clone $first)->modify('+1 month')->format('Y-m');

// ---- 2. Load this month's events ----
$events = [];

// Deliveries (cancelled ones are hidden)
$stmt = $pdo->prepare(
	"SELECT d.delivery_date AS event_date, d.time_slot, d.status, d.reservation_id,
            r.quantity, r.delivery_address,
            u.full_name AS customer_name, c.phone,
            e.full_name AS staff_name
     FROM deliveries d
     JOIN reservations r ON d.reservation_id = r.reservation_id
     JOIN customers c ON r.customer_id = c.customer_id
     JOIN users u ON c.user_id = u.user_id
     JOIN employees e ON d.employee_id = e.employee_id
     WHERE d.delivery_date BETWEEN ? AND ?
       AND d.status <> 'cancelled'"
);
$stmt->execute([$startDate, $endDate]);
foreach ($stmt->fetchAll() as $row) {
	$row['type'] = 'delivery';
	$events[] = $row;
}

// Customer pickups (they have no delivery record, so they come from reservations)
$stmt = $pdo->prepare(
	"SELECT r.reservation_date AS event_date, r.time_slot, r.status, r.reservation_id,
            r.quantity, NULL AS delivery_address,
            u.full_name AS customer_name, c.phone,
            NULL AS staff_name
     FROM reservations r
     JOIN customers c ON r.customer_id = c.customer_id
     JOIN users u ON c.user_id = u.user_id
     WHERE r.reservation_type = 'pickup'
       AND r.reservation_date BETWEEN ? AND ?
       AND r.status <> 'cancelled'"
);
$stmt->execute([$startDate, $endDate]);
foreach ($stmt->fetchAll() as $row) {
	$row['type'] = 'pickup';
	$events[] = $row;
}

// Group by date
$eventsByDate = [];
foreach ($events as $ev) {
	$eventsByDate[$ev['event_date']][] = $ev;
}

// Sort each day: morning, noon, afternoon
$slotOrder = ['morning' => 1, 'noon' => 2, 'afternoon' => 3];
foreach ($eventsByDate as $date => $list) {
	usort($list, function ($a, $b) use ($slotOrder) {
		return $slotOrder[$a['time_slot']] <=> $slotOrder[$b['time_slot']];
	});
	$eventsByDate[$date] = $list;
}

// Month totals
$totalDeliveries = 0;
$totalPickups = 0;
foreach ($events as $ev) {
	if ($ev['type'] === 'delivery') {
		$totalDeliveries++;
	} else {
		$totalPickups++;
	}
}

// Confirmed delivery reservations that still need a schedule
$awaitingCount = $pdo->query(
	"SELECT COUNT(*) FROM reservations
     WHERE reservation_type = 'delivery' AND status = 'confirmed'"
)->fetchColumn();

// Chip color class for an event
function calChipClass($ev)
{
	return $ev['type'] === 'pickup' ? 'cal-pickup' : 'cal-' . $ev['status'];
}

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/navbar.php';
?>

<h1 class="page-title">Delivery Calendar</h1>

<?php if ($awaitingCount > 0): ?>
	<div class="alert alert-warning">
		<?= (int) $awaitingCount ?> confirmed delivery reservation(s) still need scheduling.
		<a href="deliveries.php">Go to Deliveries</a>
	</div>
<?php endif; ?>

<div class="panel">
	<div class="cal-header">
		<div>
			<a href="calendar.php?month=<?= e($prevMonth) ?>" class="btn-sm" title="Previous month"><i
					class="fa-solid fa-chevron-left"></i></a>
			<a href="calendar.php" class="btn-sm gray">Today</a>
			<a href="calendar.php?month=<?= e($nextMonth) ?>" class="btn-sm" title="Next month"><i
					class="fa-solid fa-chevron-right"></i></a>
		</div>
		<h2 class="cal-title"><?= e($first->format('F Y')) ?></h2>
		<div class="cal-legend">
			<span><i class="cal-scheduled"></i> Scheduled</span>
			<span><i class="cal-out_for_delivery"></i> Out for Delivery</span>
			<span><i class="cal-delivered"></i> Delivered</span>
			<span><i class="cal-pickup"></i> Pickup</span>
		</div>
	</div>

	<p class="form-hint" style="margin-bottom:12px;">
		This month: <strong><?= $totalDeliveries ?></strong> delivery(ies) and <strong><?= $totalPickups ?></strong>
		pickup(s).
		Click a day to see the details.
	</p>

	<div class="cal-grid">
		<?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dow): ?>
			<div class="cal-dow"><?= $dow ?></div>
		<?php endforeach; ?>

		<?php for ($i = 0; $i < $startWeekday; $i++): ?>
			<div class="cal-cell empty"></div>
		<?php endfor; ?>

		<?php for ($day = 1; $day <= $daysInMonth; $day++):
			$dateKey = sprintf('%s-%02d', $monthKey, $day);
			$dayEvents = $eventsByDate[$dateKey] ?? [];
			$cellClass = 'cal-cell'
				. ($dateKey === date('Y-m-d') ? ' today' : '')
				. (!empty($dayEvents) ? ' has-events' : '');
			?>
			<div class="<?= $cellClass ?>" <?= !empty($dayEvents) ? 'onclick="openDay(\'' . e($dateKey) . '\')"' : '' ?>>
				<div class="cal-daynum">
					<span><?= $day ?></span>
					<?php if (!empty($dayEvents)): ?>
						<span class="cal-count"><?= count($dayEvents) ?></span>
					<?php endif; ?>
				</div>

				<?php foreach (array_slice($dayEvents, 0, 3) as $ev): ?>
					<span class="cal-chip <?= e(calChipClass($ev)) ?>">
						<?= e(ucfirst($ev['time_slot'])) ?> · <?= e($ev['customer_name']) ?>
					</span>
				<?php endforeach; ?>
				<?php if (count($dayEvents) > 3): ?>
					<div class="cal-more">+<?= count($dayEvents) - 3 ?> more</div>
				<?php endif; ?>
			</div>
		<?php endfor; ?>
	</div>
</div>

<?php
// ---- 3. Hidden detail blocks (one per day that has events). JavaScript copies one into the popup. ----
foreach ($eventsByDate as $dateKey => $dayEvents):
	$title = date('l, F d, Y', strtotime($dateKey));
	?>
	<div id="day-<?= e($dateKey) ?>" data-title="<?= e($title) ?>" style="display:none;">
		<div class="table-wrap">
			<table class="data-table">
				<thead>
					<tr>
						<th>Time</th>
						<th>Type</th>
						<th>Reservation</th>
						<th>Customer</th>
						<th>Qty</th>
						<th>Address / Staff</th>
						<th>Status</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($dayEvents as $ev): ?>
						<tr>
							<td><?= e(ucfirst($ev['time_slot'])) ?></td>
							<td><?= $ev['type'] === 'delivery' ? 'Delivery' : 'Customer Pickup' ?></td>
							<td><a
									href="reservation-view.php?id=<?= (int) $ev['reservation_id'] ?>"><?= e(formatReservationId($ev['reservation_id'])) ?></a>
							</td>
							<td><?= e($ev['customer_name']) ?><br><small><?= e($ev['phone']) ?></small></td>
							<td><?= e($ev['quantity']) ?></td>
							<td>
								<?php if ($ev['type'] === 'delivery'): ?>
									<?= nl2br(e($ev['delivery_address'])) ?><br>
									<small><i class="fa-solid fa-truck"></i> <?= e($ev['staff_name']) ?></small>
								<?php else: ?>
									<span class="empty-text">Pickup at the station</span>
								<?php endif; ?>
							</td>
							<td><?= $ev['type'] === 'delivery' ? deliveryBadge($ev['status']) : statusBadge($ev['status']) ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
<?php endforeach; ?>

<!-- The popup -->
<div class="modal-overlay" id="cal-modal" onclick="if (event.target === this) closeDay();">
	<div class="modal-box">
		<div class="modal-head">
			<h3 id="modal-title"></h3>
			<button type="button" class="modal-close" onclick="closeDay()">&times;</button>
		</div>
		<div id="modal-body"></div>
	</div>
</div>

<script>
	function openDay(dateKey) {
		var source = document.getElementById('day-' + dateKey);
		if (!source) { return; }
		document.getElementById('modal-title').textContent = source.getAttribute('data-title');
		document.getElementById('modal-body').innerHTML = source.innerHTML;
		document.getElementById('cal-modal').style.display = 'flex';
	}
	function closeDay() {
		document.getElementById('cal-modal').style.display = 'none';
	}
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') { closeDay(); }
	});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>