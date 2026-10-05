<?php
date_default_timezone_set('Asia/Manila');
// config/database.php
// This file connects PHP to MySQL using PDO.
// Every page that needs the database will include this file.

$host = 'localhost';
$dbname = 'jmr_water_db';
$username = 'root';   // default XAMPP username
$password = '';       // default XAMPP password is empty

try {
	$pdo = new PDO(
		"mysql:host=$host;dbname=$dbname;charset=utf8mb4",
		$username,
		$password
	);

	// Show real errors while we are developing
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	// Return rows as associative arrays: $row['full_name']
	$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
	die("Database connection failed: " . $e->getMessage());
}