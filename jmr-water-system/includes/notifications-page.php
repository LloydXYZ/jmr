<?php
// includes/notifications-page.php
// Shared by admin, staff, and customer notifications.php

$pageTitle = 'Notifications';
$userId = $_SESSION['user_id'];

if (in_array($_SESSION['role'], ['admin', 'staff'])) {
	createUpcomingDeliveryNotices($pdo);
}

// ---- Mark as read / mark all as read ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = $_POST['action'] ?? '';

	if ($action === 'read') {
		$stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
		$stmt->execute([(int) ($_POST['notification_id'] ?? 0), $userId]);
	} elseif ($action === 'all') {
		$stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
		$stmt->execute([$userId]);
	}

	header("Location: notifications.php" . (($_POST['filter'] ?? '') === 'unread' ? '?filter=unread' : ''));
	exit;
}

$filter = ($_GET['filter'] ?? '') === 'unread' ? 'unread' : '';

$sql = "SELECT * FROM notifications WHERE user_id = ?";
if ($filter === 'unread') {
	$sql .= " AND is_read = 0";
}
$sql .= " ORDER BY created_at DESC, notification_id DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute([$userId]);
$items = $stmt->fetchAll();

$unread = unreadCount($pdo, $userId);

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/navbar.php';
?>

<h1 class="page-title">Notifications</h1>

<div class="panel">
	<div class="filter-bar">
		<a href="notifications.php" class="btn-sm <?= $filter === '' ? '' : 'gray' ?>">All</a>
		<a href="notifications.php?filter=unread" class="btn-sm <?= $filter === 'unread' ? '' : 'gray' ?>">Unread
			(<?= (int) $unread ?>)</a>

		<?php if ($unread > 0): ?>
			<form method="POST" action="" style="display:inline; margin-left:auto;">
				<input type="hidden" name="action" value="all">
				<input type="hidden" name="filter" value="<?= e($filter) ?>">
				<button type="submit" class="btn-sm success"><i class="fa-solid fa-check-double"></i> Mark all as read</button>
			</form>
		<?php endif; ?>
	</div>

	<?php if (empty($items)): ?>
		<p class="empty-text"><?= $filter === 'unread' ? 'No unread notifications.' : 'No notifications yet.' ?></p>
	<?php else: ?>
		<?php foreach ($items as $n): ?>
			<div class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>">
				<div class="notif-row">
					<div>
						<i class="fa-solid <?= e(notificationIcon($n['notification_type'])) ?> notif-icon"></i>
						<?= e($n['message']) ?>
						<small><?= e(date('M d, Y h:i A', strtotime($n['created_at']))) ?> · <?= e(timeAgo($n['created_at'])) ?></small>
					</div>
					<?php if (!$n['is_read']): ?>
						<form method="POST" action="">
							<input type="hidden" name="action" value="read">
							<input type="hidden" name="notification_id" value="<?= (int) $n['notification_id'] ?>">
							<input type="hidden" name="filter" value="<?= e($filter) ?>">
							<button type="submit" class="btn-sm">Mark as read</button>
						</form>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>