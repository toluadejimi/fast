<?php
/**
 * backfill_badges.php
 * ONE-TIME USE. Run after migration_v3.sql to calculate every existing
 * user's lifetime spend from their order history, then award and notify
 * anyone who already qualifies for a badge.
 *
 * Visit once in your browser: yourdomain.com/backfill_badges.php
 * Then DELETE this file.
 */

if (!defined('ROOT_PATH')) define('ROOT_PATH', __DIR__);
require_once ROOT_PATH . '/includes/functions.php';

$users = $pdo->query("SELECT id FROM users")->fetchAll();
$count = 0;

foreach ($users as $row) {
    $uid = (int) $row['id'];

    $otpStmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid),0) FROM otp_orders WHERE user_id = ? AND status = 'RECEIVED'");
    $otpStmt->execute([$uid]);
    $otpSpent = (float) $otpStmt->fetchColumn();

    $logStmt = $pdo->prepare("SELECT COALESCE(SUM(total_usd),0) FROM log_orders WHERE user_id = ?");
    $logStmt->execute([$uid]);
    $logSpent = (float) $logStmt->fetchColumn();

    $spent = $otpSpent + $logSpent;

    $pdo->prepare("UPDATE users SET total_spent = ? WHERE id = ?")->execute([$spent, $uid]);
    check_badge_upgrade($uid); // upgrades tier + notifies if they now qualify
    $count++;
}

echo "Backfilled total_spent and checked badges for {$count} user(s).<br><strong>Now delete this file.</strong>";
