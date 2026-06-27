<?php
/**
 * GET -> aggregate financial/overview KPIs for the admin dashboard's
 * overview tab: user/order/ticket counts by status, revenue, wallet
 * totals, and the most recent wallet transactions across all users.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../admin_lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

uploadgram_require_admin();
$db = uploadgram_db();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    uploadgram_admin_send_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$totalUsers = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalWalletBalance = (int) $db->query('SELECT COALESCE(SUM(balance), 0) FROM users')->fetchColumn();
$suspendedUsers = (int) $db->query('SELECT COUNT(*) FROM users WHERE is_suspended = 1')->fetchColumn();

$orderStatusRows = $db->query("SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status")->fetchAll();
$ordersByStatus = [];
foreach ($orderStatusRows as $row) {
    $ordersByStatus[$row['status']] = (int) $row['cnt'];
}

$totalRevenue = (int) $db->query("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE status IN ('paid', 'processing', 'completed')")->fetchColumn();
$totalTopups = (int) $db->query("SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions WHERE type = 'topup' AND status = 'success'")->fetchColumn();

$upstreamErrors = (int) $db->query("SELECT COUNT(*) FROM orders WHERE upstream_status = 'upstream_error'")->fetchColumn();
$manualRequired = (int) $db->query("SELECT COUNT(*) FROM orders WHERE upstream_status = 'manual_required'")->fetchColumn();

$openTickets = (int) $db->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'")->fetchColumn();
$totalTickets = (int) $db->query('SELECT COUNT(*) FROM tickets')->fetchColumn();

$totalProducts = (int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn();
$activeProducts = (int) $db->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn();

$recentTransactions = $db->query('SELECT wt.id, wt.uid, wt.type, wt.amount, wt.status, wt.note, wt.created_at, u.email, u.display_name
    FROM wallet_transactions wt LEFT JOIN users u ON u.uid = wt.uid
    ORDER BY wt.id DESC LIMIT 20')->fetchAll();

uploadgram_admin_send_json([
    'ok' => true,
    'stats' => [
        'total_users' => $totalUsers,
        'suspended_users' => $suspendedUsers,
        'total_wallet_balance' => $totalWalletBalance,
        'total_revenue' => $totalRevenue,
        'total_topups' => $totalTopups,
        'orders_by_status' => $ordersByStatus,
        'upstream_errors' => $upstreamErrors,
        'manual_required' => $manualRequired,
        'open_tickets' => $openTickets,
        'total_tickets' => $totalTickets,
        'total_products' => $totalProducts,
        'active_products' => $activeProducts,
    ],
    'recent_transactions' => $recentTransactions,
]);
