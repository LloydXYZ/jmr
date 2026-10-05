<?php
require_once 'config/database.php';

$accounts = [
	['System Administrator', 'admin@jmr.com', 'admin123', 'admin'],
	['Test Staff', 'staff@jmr.com', 'staff123', 'staff'],
];

foreach ($accounts as $a) {
	$stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
	$stmt->execute([$a[1]]);
	if ($stmt->fetch()) {
		echo "{$a[1]} already exists.<br>";
		continue;
	}
	$stmt = $pdo->prepare(
		"INSERT INTO users (full_name, email, password, role, status) VALUES (?, ?, ?, ?, 'active')"
	);
	$stmt->execute([$a[0], $a[1], password_hash($a[2], PASSWORD_DEFAULT), $a[3]]);
	echo "Created {$a[3]}: {$a[1]} / {$a[2]}<br>";
}