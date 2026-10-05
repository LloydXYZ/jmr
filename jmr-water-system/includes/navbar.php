<?php
// includes/navbar.php
if (in_array($_SESSION['role'], ['admin', 'staff'])) {
	createUpcomingDeliveryNotices($pdo);
}
$unread = unreadCount($pdo, $_SESSION['user_id']);
$notifLink = '/jmr-water-system/' . $_SESSION['role'] . '/notifications.php';
?>
<div class="main-content">
	<div class="topbar">
		<button class="menu-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
			<i class="fa-solid fa-bars"></i>
		</button>
		<div></div>
		<div class="user-info">
			<a href="<?= e($notifLink) ?>" class="bell-link" title="Notifications">
				<i class="fa-solid fa-bell"></i>
				<?php if ($unread > 0): ?>
					<span class="bell-badge"><?= $unread > 99 ? '99+' : (int) $unread ?></span>
				<?php endif; ?>
			</a>
			<i class="fa-solid fa-user"></i>
			<?= e($_SESSION['full_name']) ?> (<?= e(ucfirst($_SESSION['role'])) ?>)
			<a href="/jmr-water-system/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
		</div>
	</div>
	<div class="page-body">