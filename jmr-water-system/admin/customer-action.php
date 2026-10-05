<?php
require_once '../config/database.php';
require_once '../includes/auth-check.php';
requireRole(['admin']);
require_once '../includes/customer-action.php';