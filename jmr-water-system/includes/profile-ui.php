<?php
$pageTitle = 'My Profile';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/navbar.php';
?>
<h1 class="page-title">My Profile</h1>
<div class="alert alert-info">UI preview. Saving is not connected yet.</div>

<div class="panel form-panel">
	<h3>Edit Profile</h3>
	<form onsubmit="alert('Coming soon'); return false;">
		<div class="form-row">
			<div class="form-group"><label>Full Name</label><input type="text" value="<?= e($_SESSION['full_name']) ?>"></div>
			<div class="form-group"><label>Email</label><input type="email"></div>
		</div>
		<?php if ($_SESSION['role'] === 'customer'): ?>
			<div class="form-group"><label>Phone</label><input type="text"></div>
			<div class="form-group"><label>Address</label><textarea rows="2"></textarea></div>
		<?php endif; ?>
		<button class="btn-inline">Save Profile</button>
	</form>
</div>

<div class="panel form-panel">
	<h3>Change Password</h3>
	<form onsubmit="alert('Coming soon'); return false;">
		<div class="form-group"><label>Current Password</label><input type="password"></div>
		<div class="form-row">
			<div class="form-group"><label>New Password</label><input type="password"></div>
			<div class="form-group"><label>Confirm New Password</label><input type="password"></div>
		</div>
		<button class="btn-inline">Change Password</button>
	</form>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>