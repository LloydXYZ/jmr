<?php
require_once 'includes/auth-check.php';

if (isLoggedIn()) {
	redirectToDashboard();
} else {
	header("Location: auth/login.php");
	exit;
}