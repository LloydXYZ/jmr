<?php
require_once '../config/database.php';
require_once '../includes/auth-check.php';

requireRole(['customer']);

$pageTitle = 'My Reservations';

// Get this customer's customer_id
$stmt = $pdo->prepare("SELECT customer_id FROM customers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$customer = $stmt->fetch();
if (!$customer) {
	die("Customer record not found. Please contact the administrator.");
}

// Optional status filter
$validStatuses = ['pending', 'confirmed', 'scheduled', 'out_for_delivery', 'completed', 'cancelled'];
$statusFilter = $_GET['status'] ?? '';

$sql = "SELECT * FROM reservations WHERE customer_id = ?";
$params = [$customer['customer_id']];

if (in_array($statusFilter, $validStatuses)) {
	$sql .= " AND status = ?";
	$params[] = $statusFilter;
}
$sql .= " ORDER BY reservation_date DESC, reservation_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll();

// Cancellable statuses
$cancellable = ['pending', 'confirmed', 'scheduled'];

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/navbar.php';
?>

<h1 class="page-title">My Reservations</h1>

<?php if (isset($_GET['cancelled'])): ?>
	<div class="alert alert-success">Your reservation was cancelled.</div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
	<div class="alert alert-danger">That reservation could not be cancelled. It may already be out for delivery, completed,
		or cancelled.</div>
<?php endif; ?>

<div class="panel">
	<form method="GET" class="filter-bar">
		<label for="status"><strong>Filter by status:</strong></label>
		<select name="status" id="status" onchange="this.form.submit()">
			<option value="">All</option>
			<?php foreach ($validStatuses as $s): ?>
				<option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>>
					<?= e(ucwords(str_replace('_', ' ', $s))) ?>
				</option>
			<?php endforeach; ?>
		</select>
		<a href="reservation.php" class="btn-sm"><i class="fa-solid fa-plus"></i> New Reservation</a>
	</form>

	<?php if (empty($reservations)): ?>
		<p class="empty-text">No reservations found.</p>
	<?php else: ?>
		<div class="table-wrap">
			<table class="data-table">
				<thead>
					<tr>
						<th>ID</th>
						<th>Type</th>
						<th>Qty</th>
						<th>Date</th>
						<th>Time Slot</th>
						<th>Status</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($reservations as $r): ?>
						<tr>
							<td><?= e(formatReservationId($r['reservation_id'])) ?></td>
							<td><?= e(typeLabel($r['reservation_type'])) ?></td>
							<td><?= e($r['quantity']) ?></td>
							<td><?= e(date('M d, Y', strtotime($r['reservation_date']))) ?></td>
							<td><?= e(ucfirst($r['time_slot'])) ?></td>
							<td><?= statusBadge($r['status']) ?></td>
							<td>
								<a href="reservation-details.php?id=<?= (int) $r['reservation_id'] ?>" class="btn-sm">View</a>
								<?php if (in_array($r['status'], $cancellable)): ?>
									<form method="POST" action="cancel-reservation.php" style="display:inline;"
										onsubmit="return confirm('Cancel this reservation?');">
										<input type="hidden" name="reservation_id" value="<?= (int) $r['reservation_id'] ?>">
										<button type="submit" class="btn-sm danger">Cancel</button>
									</form>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>