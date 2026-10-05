<?php
// includes/auth-check.php
// Reusable login and role helpers.

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

// Escape output so user text can't inject HTML/JS (XSS protection)
function e($text)
{
	return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

// Is someone logged in?
function isLoggedIn()
{
	return isset($_SESSION['user_id']);
}

// Send the user to the correct dashboard for their role
function redirectToDashboard()
{
	$base = '/jmr-water-system/';
	switch ($_SESSION['role'] ?? '') {
		case 'admin':
			header("Location: {$base}admin/dashboard.php");
			break;
		case 'staff':
			header("Location: {$base}staff/dashboard.php");
			break;
		case 'customer':
			header("Location: {$base}customer/dashboard.php");
			break;
		default:
			header("Location: {$base}auth/login.php");
	}
	exit;
}

// Call at the top of any protected page.
// Example: requireRole(['admin']);  or  requireRole(['admin', 'staff']);
function requireRole($allowedRoles)
{
	if (!isLoggedIn()) {
		header("Location: /jmr-water-system/auth/login.php");
		exit;
	}
	if (!in_array($_SESSION['role'], $allowedRoles)) {
		// Logged in, but wrong role: send them back to their own dashboard
		redirectToDashboard();
	}
}

// Turn reservation number 5 into "RES-005"
function formatReservationId($id)
{
	return 'RES-' . str_pad($id, 3, '0', STR_PAD_LEFT);
}

// Show a colored badge for a reservation status
function statusBadge($status)
{
	$colors = [
		'pending' => 'badge-warning',
		'confirmed' => 'badge-primary',
		'scheduled' => 'badge-primary',
		'out_for_delivery' => 'badge-primary',
		'completed' => 'badge-success',
		'cancelled' => 'badge-danger',
	];
	$class = $colors[$status] ?? 'badge-primary';
	$label = ucwords(str_replace('_', ' ', $status));
	return "<span class=\"badge $class\">" . e($label) . "</span>";
}

// Show "Delivery" or "Customer Pickup" nicely
function typeLabel($type)
{
	return $type === 'delivery' ? 'Delivery' : 'Customer Pickup';
}

// Builds a small POST button used for Confirm / Complete / Cancel.
// Note: keep $confirmMsg free of apostrophes (').
function actionForm($id, $action, $label, $class, $confirmMsg, $return = 'list')
{
	$folder = $_SESSION['role']; // 'admin' or 'staff'
	return '<form method="POST" action="/jmr-water-system/' . e($folder) . '/reservation-action.php" '
		. 'style="display:inline;" onsubmit="return confirm(\'' . e($confirmMsg) . '\');">'
		. '<input type="hidden" name="reservation_id" value="' . (int) $id . '">'
		. '<input type="hidden" name="action" value="' . e($action) . '">'
		. '<input type="hidden" name="return" value="' . e($return) . '">'
		. '<button type="submit" class="btn-sm ' . e($class) . '">' . e($label) . '</button>'
		. '</form> ';
}

// Active / Inactive badge for user accounts
function accountBadge($status)
{
	if ($status === 'active') {
		return '<span class="badge badge-success">Active</span>';
	}
	return '<span class="badge badge-danger">Inactive</span>';
}

// Turn employee number 3 into "EMP-003"
function formatEmployeeId($id)
{
	return 'EMP-' . str_pad($id, 3, '0', STR_PAD_LEFT);
}

// The allowed positions. To add a new position later, just add it here.
function employeePositions()
{
	return ['Delivery Staff', 'Refilling Staff'];
}

// Colored badge for a delivery status
function deliveryBadge($status)
{
	$colors = [
		'scheduled' => 'badge-primary',
		'out_for_delivery' => 'badge-warning',
		'delivered' => 'badge-success',
		'cancelled' => 'badge-danger',
	];
	$class = $colors[$status] ?? 'badge-primary';
	$label = ucwords(str_replace('_', ' ', $status));
	return "<span class=\"badge $class\">" . e($label) . "</span>";
}

// POST button for Start / Deliver / Cancel on the Deliveries page.
// $view remembers which tab (today / upcoming / all) the user was on.
// Note: keep $confirmMsg free of apostrophes (').
function deliveryActionForm($id, $action, $label, $class, $confirmMsg, $view = 'today')
{
	$folder = $_SESSION['role']; // 'admin' or 'staff'
	return '<form method="POST" action="/jmr-water-system/' . e($folder) . '/delivery-action.php" '
		. 'style="display:inline;" onsubmit="return confirm(\'' . e($confirmMsg) . '\');">'
		. '<input type="hidden" name="delivery_id" value="' . (int) $id . '">'
		. '<input type="hidden" name="action" value="' . e($action) . '">'
		. '<input type="hidden" name="view" value="' . e($view) . '">'
		. '<button type="submit" class="btn-sm ' . e($class) . '">' . e($label) . '</button>'
		. '</form> ';
}

// Current price of one 20-liter gallon (set by the admin)
function getPricePerGallon($pdo)
{
	$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'price_per_gallon'");
	$stmt->execute();
	$value = $stmt->fetchColumn();
	return $value !== false ? (float) $value : 20.00;
}

// Format money: peso(40) gives ₱40.00
function peso($amount)
{
	return '₱' . number_format((float) $amount, 2);
}

// ================= PAYMENTS =================

function formatPaymentId($id)
{
	return 'PAY-' . str_pad($id, 3, '0', STR_PAD_LEFT);
}

function paymentBadge($status)
{
	if ($status === 'paid') {
		return '<span class="badge badge-success">Paid</span>';
	}
	return '<span class="badge badge-danger">Unpaid</span>';
}

// The payment row of a reservation, or false if none exists yet
function getPayment($pdo, $reservationId)
{
	$stmt = $pdo->prepare("SELECT * FROM payments WHERE reservation_id = ?");
	$stmt->execute([$reservationId]);
	return $stmt->fetch();
}

// POST button used on the Payments page. Keep $confirmMsg free of apostrophes.
function paymentActionForm($reservationId, $action, $label, $class, $confirmMsg)
{
	$folder = $_SESSION['role']; // 'admin' or 'staff'
	return '<form method="POST" action="/jmr-water-system/' . e($folder) . '/payment-action.php" '
		. 'style="display:inline;" onsubmit="return confirm(\'' . e($confirmMsg) . '\');">'
		. '<input type="hidden" name="reservation_id" value="' . (int) $reservationId . '">'
		. '<input type="hidden" name="action" value="' . e($action) . '">'
		. '<button type="submit" class="btn-sm ' . e($class) . '">' . e($label) . '</button>'
		. '</form> ';
}

// ================= INVENTORY =================

// In stock, reserved, available, delivered, and the low-stock flag
function getInventorySummary($pdo)
{
	$inv = $pdo->query(
		"SELECT inventory_id, stock_on_hand, low_stock_limit FROM inventory ORDER BY inventory_id LIMIT 1"
	)->fetch();

	// Safety: create the single inventory row if it is missing
	if (!$inv) {
		$pdo->exec("INSERT INTO inventory (item_name, stock_on_hand, low_stock_limit)
                    VALUES ('20-Liter Water Gallon', 0, 10)");
		$inv = $pdo->query(
			"SELECT inventory_id, stock_on_hand, low_stock_limit FROM inventory ORDER BY inventory_id LIMIT 1"
		)->fetch();
	}

	$reserved = (int) $pdo->query(
		"SELECT COALESCE(SUM(quantity), 0) FROM reservations
         WHERE status IN ('confirmed', 'scheduled', 'out_for_delivery')"
	)->fetchColumn();

	$used = (int) $pdo->query(
		"SELECT COALESCE(SUM(quantity), 0) FROM inventory_transactions
         WHERE transaction_type = 'deliver'"
	)->fetchColumn();

	$onHand = (int) $inv['stock_on_hand'];
	$limit = (int) $inv['low_stock_limit'];
	$available = $onHand - $reserved;

	return [
		'inventory_id' => (int) $inv['inventory_id'],
		'on_hand' => $onHand,
		'reserved' => $reserved,
		'available' => $available,
		'used' => $used,
		'low_limit' => $limit,
		'is_low' => $available <= $limit,
	];
}

// Changes the stock and writes a history row.
// $type: 'add', 'deduct', or 'deliver'. Returns true on success, false if there is not enough stock.
function changeStock($pdo, $type, $qty, $userId, $reservationId = null, $remarks = null)
{
	// If the caller already started a transaction, we join it instead of starting a new one
	$ownTransaction = !$pdo->inTransaction();
	if ($ownTransaction) {
		$pdo->beginTransaction();
	}

	try {
		// Lock the row so two people cannot change the stock at the same moment
		$inv = $pdo->query(
			"SELECT inventory_id, stock_on_hand FROM inventory ORDER BY inventory_id LIMIT 1 FOR UPDATE"
		)->fetch();
		if (!$inv) {
			throw new Exception('Inventory row is missing.');
		}

		// A reservation's gallons are only deducted once
		if ($type === 'deliver' && $reservationId) {
			$stmt = $pdo->prepare(
				"SELECT transaction_id FROM inventory_transactions
                 WHERE reservation_id = ? AND transaction_type = 'deliver'"
			);
			$stmt->execute([$reservationId]);
			if ($stmt->fetch()) {
				if ($ownTransaction) {
					$pdo->commit();
				}
				return true;
			}
		}

		$onHand = (int) $inv['stock_on_hand'];
		$newStock = ($type === 'add') ? $onHand + $qty : $onHand - $qty;
		$ok = ($newStock >= 0);

		// A manual deduction must not eat into gallons that are already reserved
		if ($ok && $type === 'deduct') {
			$reserved = (int) $pdo->query(
				"SELECT COALESCE(SUM(quantity), 0) FROM reservations
                 WHERE status IN ('confirmed', 'scheduled', 'out_for_delivery')"
			)->fetchColumn();
			$ok = ($newStock >= $reserved);
		}

		if (!$ok) {
			if ($ownTransaction) {
				$pdo->rollBack();
			}
			return false;
		}

		$stmt = $pdo->prepare("UPDATE inventory SET stock_on_hand = ? WHERE inventory_id = ?");
		$stmt->execute([$newStock, $inv['inventory_id']]);

		$stmt = $pdo->prepare(
			"INSERT INTO inventory_transactions
             (inventory_id, transaction_type, quantity, balance_after, reservation_id, remarks, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
		);
		$stmt->execute([$inv['inventory_id'], $type, $qty, $newStock, $reservationId, $remarks, $userId]);

		if ($ownTransaction) {
			$pdo->commit();
		}
		return true;

	} catch (Exception $ex) {
		if ($ownTransaction && $pdo->inTransaction()) {
			$pdo->rollBack();
		}
		throw $ex;
	}
}

// Takes a completed reservation's gallons out of stock (only once per reservation)
function deductStockForReservation($pdo, $reservationId, $userId)
{
	$stmt = $pdo->prepare("SELECT quantity FROM reservations WHERE reservation_id = ?");
	$stmt->execute([$reservationId]);
	$qty = (int) $stmt->fetchColumn();
	if ($qty < 1) {
		return false;
	}

	return changeStock(
		$pdo,
		'deliver',
		$qty,
		$userId,
		$reservationId,
		'Completed ' . formatReservationId($reservationId)
	);
}

function inventoryTypeBadge($type)
{
	$map = [
		'add' => ['badge-success', 'Stock Added'],
		'deduct' => ['badge-danger', 'Stock Deducted'],
		'deliver' => ['badge-primary', 'Delivered'],
	];
	$item = $map[$type] ?? ['badge-primary', ucfirst($type)];
	return '<span class="badge ' . $item[0] . '">' . e($item[1]) . '</span>';
}

// ================= PAYROLL =================

function formatPayrollId($id)
{
	return 'PR-' . str_pad($id, 3, '0', STR_PAD_LEFT);
}

function payrollBadge($status)
{
	if ($status === 'paid') {
		return '<span class="badge badge-success">Paid</span>';
	}
	return '<span class="badge badge-warning">Unpaid</span>';
}

// ================= NOTIFICATIONS =================

// Send one notification to one user. It never stops the main action if it fails.
function notifyUser($pdo, $userId, $message, $type)
{
	try {
		$stmt = $pdo->prepare(
			"INSERT INTO notifications (user_id, message, notification_type) VALUES (?, ?, ?)"
		);
		$stmt->execute([(int) $userId, mb_substr($message, 0, 255), $type]);
	} catch (Exception $ex) {
		// ignore
	}
}

// Send a notification to every active admin and staff user.
// $dedupe: ''            = always send
//          'unread_type' = skip users who already have an unread one of this type
//          'message'     = skip users who already got this exact message
function notifyStaff($pdo, $message, $type, $dedupe = '')
{
	try {
		$users = $pdo->query(
			"SELECT user_id FROM users WHERE role IN ('admin', 'staff') AND status = 'active'"
		)->fetchAll(PDO::FETCH_COLUMN);

		foreach ($users as $uid) {
			$chk = null;
			if ($dedupe === 'unread_type') {
				$chk = $pdo->prepare(
					"SELECT 1 FROM notifications WHERE user_id = ? AND notification_type = ? AND is_read = 0 LIMIT 1"
				);
				$chk->execute([$uid, $type]);
			} elseif ($dedupe === 'message') {
				$chk = $pdo->prepare("SELECT 1 FROM notifications WHERE user_id = ? AND message = ? LIMIT 1");
				$chk->execute([$uid, mb_substr($message, 0, 255)]);
			}
			if ($chk && $chk->fetch()) {
				continue;
			}

			notifyUser($pdo, $uid, $message, $type);
		}
	} catch (Exception $ex) {
		// ignore
	}
}

// The login user_id of the customer who owns a reservation
function getReservationCustomerUserId($pdo, $reservationId)
{
	$stmt = $pdo->prepare(
		"SELECT c.user_id FROM reservations r
         JOIN customers c ON r.customer_id = c.customer_id
         WHERE r.reservation_id = ?"
	);
	$stmt->execute([$reservationId]);
	return $stmt->fetchColumn();
}

// Notify the customer who owns a reservation
function notifyReservationCustomer($pdo, $reservationId, $message, $type)
{
	$uid = getReservationCustomerUserId($pdo, $reservationId);
	if ($uid) {
		notifyUser($pdo, $uid, $message, $type);
	}
}

// Number of unread notifications (the bell)
function unreadCount($pdo, $userId)
{
	$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
	$stmt->execute([$userId]);
	return (int) $stmt->fetchColumn();
}

// Warn admin/staff when available gallons are low (only if they have no unread low-stock notice)
function checkLowStock($pdo)
{
	$inv = getInventorySummary($pdo);
	if ($inv['is_low']) {
		notifyStaff($pdo, "Low inventory: only " . $inv['available'] . " gallon(s) available.", 'inventory', 'unread_type');
	}
}

// Tells admin/staff about scheduled deliveries for today and tomorrow.
// Runs at most once every 30 minutes per login session.
function createUpcomingDeliveryNotices($pdo)
{
	if (!empty($_SESSION['notice_check']) && $_SESSION['notice_check'] > time() - 1800) {
		return;
	}
	$_SESSION['notice_check'] = time();

	try {
		$rows = $pdo->query(
			"SELECT d.reservation_id, d.delivery_date, d.time_slot, u.full_name
             FROM deliveries d
             JOIN reservations r ON d.reservation_id = r.reservation_id
             JOIN customers c ON r.customer_id = c.customer_id
             JOIN users u ON c.user_id = u.user_id
             WHERE d.status = 'scheduled'
               AND d.delivery_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 DAY)"
		)->fetchAll();

		foreach ($rows as $r) {
			$message = "Upcoming delivery: " . formatReservationId($r['reservation_id'])
				. " for " . $r['full_name'] . " on "
				. date('M d, Y', strtotime($r['delivery_date']))
				. " (" . ucfirst($r['time_slot']) . ").";
			notifyStaff($pdo, $message, 'delivery', 'message');
		}
	} catch (Exception $ex) {
		// ignore
	}
}

function notificationIcon($type)
{
	$map = [
		'reservation' => 'fa-calendar-check',
		'delivery' => 'fa-truck',
		'payment' => 'fa-money-bill-wave',
		'inventory' => 'fa-bottle-water',
	];
	return $map[$type] ?? 'fa-bell';
}

// "5 minute(s) ago", "2 day(s) ago", or a date
function timeAgo($datetime)
{
	$diff = time() - strtotime($datetime);
	if ($diff < 60) {
		return 'Just now';
	}
	if ($diff < 3600) {
		return floor($diff / 60) . ' minute(s) ago';
	}
	if ($diff < 86400) {
		return floor($diff / 3600) . ' hour(s) ago';
	}
	if ($diff < 604800) {
		return floor($diff / 86400) . ' day(s) ago';
	}
	return date('M d, Y', strtotime($datetime));
}

// ================= ACTIVITY LOG =================

// Records what the logged-in user did. It never stops the main action if it fails.
function logActivity($pdo, $action)
{
	try {
		$stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
		$stmt->execute([$_SESSION['user_id'] ?? null, mb_substr($action, 0, 255)]);
	} catch (Exception $ex) {
		// ignore
	}
}