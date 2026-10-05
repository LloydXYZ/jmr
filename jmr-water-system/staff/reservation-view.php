<?php
require_once '../config/database.php';
require_once '../includes/auth-check.php';
requireRole(['staff']);
require_once '../includes/reservation-view.php';