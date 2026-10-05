<?php
require_once 'config/database.php';

$email = 'admin@jmr.com';
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE users SET password = ?, role = 'admin', status = 'active' WHERE email = ?");
$stmt->execute([$hash, $email]);

echo "Admin password reset. Rows updated: " . $stmt->rowCount();
echo "<br>Email: $email<br>Password: $password";