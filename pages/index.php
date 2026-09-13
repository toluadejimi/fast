<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
$pageTitle = 'VTU Bills';
require_once ROOT_PATH . '/includes/header.php';

$serviceMeta = [
    'airtime'     => ['name' => 'Airtime',     'icon' => 'fi-rr-mobile-notch', 'href' => 'airtime.php',     'desc' => 'MTN, GLO, 9mobile, Airtel'],
    'data'        => ['name' => 'Data Bundle', 'icon' => 'fi-rr-signal-alt',   'href' => 'data.php',        'desc' => 'Buy data for any network'],
    'cable'       => ['name' => 'Cable TV',    'icon' => 'fi-rr-tv-music',     'href' => 'cable.php',       'desc' => 'GOtv, DStv, StarTimes, Showmax'],
    'cable'       => ['name' => 'Cable TV',    'icon' => 'fi-rr-tv-music',     'href' => 'cable.php',       'desc' => 'GOtv, DStv, StarTimes, Showmax'],
    'cable'       => ['name' => 'Cable TV',    'icon' => 'fi-rr-tv-music',     'href' => 'cable.php',       'desc' => 'GOtv, DStv, StarTimes, Showmax'],
    'cable'       => ['name' => 'Cable TV',    'icon' => 'fi-rr-tv-music',     'href' => 'cable.php',       'desc' => 'GOtv, DStv, StarTimes, Showmax'],
    'electricity' => ['name' => 'Electricity', 'icon' => 'fi-rr-bolt',         'href' => 'electricity.php', 'desc' => 'Pay your prepaid/postpaid bill'],
];

$activeCodes = $pdo->query("SELECT code FROM vtu_services WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
$services = [];
foreach ($activeCodes as $code) {
    if (isset($serviceMeta[$code])) {
        $services[] = array_merge(['code' => $code], $serviceMeta[$code]);
    }
}
?>

<h2 style="font-size:20px;font-weight:900;margin-bottom:14px;">ðŸ’³ VTU Bills</h2>

<?php if (empty($services)): ?>
<div class="empty">
  <i class="fi fi-rr-bolt" style="display:block;font-size:48px;margin-bottom:14px;opacity:.3;"></i>
  <p>VTU services are temporarily unavailable. Please check back shortly.</p>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
  <?php foreach ($services as $s): ?>
  <a href="<?= SITE_URL ?>/pages/<?= $s['href'] ?>" class="glass" style="padding:18px 14px;border-radius:var(--radius);text-align:center;text-decoration:none;color:var(--text);">
    <i class="fi <?= $s['icon'] ?>" style="font-size:28px;color:var(--primary);display:block;margin-bottom:8px;"></i>
    <div style="font-weight:800;font-size:14px;"><?= clean($s['name']) ?></div>
    <div style="font-size:11px;color:var(--text3);margin-top:2px;"><?= clean($s['desc']) ?></div>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<p style="text-align:center;margin-top:18px;"><a href="<?= SITE_URL ?>/pages/vtu-history.php">View My VTU History â†’</a></p>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
