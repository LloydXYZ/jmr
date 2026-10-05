<?php
session_start();

// Remove all session data
$_SESSION = [];
session_destroy();

header("Location: login.php?loggedout=1");
exit;