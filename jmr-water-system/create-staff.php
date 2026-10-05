<?php
require_once 'config/database.php';

$email = 'staff@jmr.com';
$password = 'staff123';
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
$stmt->execute([$email]);

if ($stmt->fetch()) {
	echo "Staff account already exists.";
} else {
	$stmt = $pdo->prepare(
		"INSERT INTO users (full_name, email, password, role, status) VALUES (?, ?, ?, 'staff', 'active')"
	);
	$stmt->execute(['Test Staff', $email, $hash]);
	echo "Staff account CREATED.<br>Email: $email<br>Password: $password";
}