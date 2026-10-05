<?php
$base = '/jmr-water-system/';
$role = $_SESSION['role'];

function activeLink($file)
{
	return basename($_SERVER['PHP_SELF']) === $file ? 'active' : '';
}
// Prints one menu link
function navItem($base, $role, $file, $icon, $label)
{
	echo '<li><a href="' . $base . $role . '/' . $file . '" class="' . activeLink($file) . '">'
		. '<i class="fa-solid ' . $icon . '"></i> ' . $label . '</a></li>';
}
?>
<aside class="sidebar" id="sidebar">
	<div class="brand"><i class="fa-solid fa-droplet"></i> JMR Water</div>
	<ul>
		<?php
		if ($role === 'admin' || $role === 'staff') {
			navItem($base, $role, 'dashboard.php', 'fa-gauge', 'Dashboard');
			navItem($base, $role, 'reservations.php', 'fa-calendar-check', 'Reservations');
			navItem($base, $role, 'deliveries.php', 'fa-truck', 'Deliveries');
			navItem($base, $role, 'calendar.php', 'fa-calendar-days', 'Calendar');
			navItem($base, $role, 'payments.php', 'fa-money-bill-wave', 'Payments');
			navItem($base, $role, 'inventory.php', 'fa-bottle-water', 'Inventory');
			navItem($base, $role, 'customers.php', 'fa-users', 'Customers');
		}
		if ($role === 'admin') {
			navItem($base, $role, 'employees.php', 'fa-id-badge', 'Employees');
			navItem($base, $role, 'payroll.php', 'fa-wallet', 'Payroll');
			navItem($base, $role, 'income.php', 'fa-peso-sign', 'Income');
			navItem($base, $role, 'reports.php', 'fa-chart-column', 'Reports');
			navItem($base, $role, 'activity-log.php', 'fa-clock-rotate-left', 'Activity Log');
			navItem($base, $role, 'settings.php', 'fa-gear', 'Settings');
		}
		if ($role === 'customer') {
			navItem($base, $role, 'dashboard.php', 'fa-house', 'Dashboard');
			navItem($base, $role, 'reservation.php', 'fa-plus', 'Make Reservation');
			navItem($base, $role, 'my-reservations.php', 'fa-list', 'My Reservations');
		}
		navItem($base, $role, 'notifications.php', 'fa-bell', 'Notifications');
		navItem($base, $role, 'profile.php', 'fa-user-gear', 'My Profile');
		?>
		<li><a href="<?= $base ?>auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
	</ul>
</aside>