<?php
@ini_set('display_errors', 1);
@error_reporting(E_ALL);

if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
$pageTitle = 'Dashboard Ads';
require_once __DIR__ . '/includes/admin_header.php';

$error = $success = '';

// ── Auto-create table if missing ──────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `dashboard_ads` (
      `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `title`      VARCHAR(120) NOT NULL DEFAULT '',
      `image_file` VARCHAR(255) NOT NULL,
      `link_url`   VARCHAR(500) NOT NULL DEFAULT '',
      `is_active`  TINYINT(1)  NOT NULL DEFAULT 1,
      `sort_order` TINYINT     NOT NULL DEFAULT 0,
      `created_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    $error = 'DB error: ' . $e->getMessage();
}

// ── Upload folder ──────────────────────────────────────────────
$uploadDir = ROOT_PATH . '/assets/uploads/ads/';
$uploadWeb = SITE_URL  . '/assets/uploads/ads/';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
$uploadOk  = is_dir($uploadDir) && is_writable($uploadDir);

// ── POST handlers ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        // ADD
        if ($action === 'add') {
            $title = trim($_POST['title'] ?? '');
            $link  = trim($_POST['link_url'] ?? '');
            $sort  = max(0, (int)($_POST['sort_order'] ?? 0));
            if ($link === '') $link = '#';

            $upErr = $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE;

            if ($upErr === UPLOAD_ERR_NO_FILE || empty($_FILES['image']['tmp_name'])) {
                $error = 'Please choose an image file.';
            } elseif ($upErr !== UPLOAD_ERR_OK) {
                $errMap = [1=>'File exceeds server limit',2=>'File too large',3=>'Partial upload',6=>'No temp folder',7=>'Disk write failed'];
                $error  = 'Upload failed: ' . ($errMap[$upErr] ?? 'Error code ' . $upErr);
            } elseif (!$uploadOk) {
                $error = 'Upload folder not writable: ' . $uploadDir;
            } else {
                $tmp  = $_FILES['image']['tmp_name'];

                // Detect mime type — 3 fallbacks
                $mime = '';
                if (function_exists('finfo_open')) {
                    $fi   = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($fi, $tmp);
                    finfo_close($fi);
                } elseif (function_exists('mime_content_type')) {
                    $mime = mime_content_type($tmp);
                } else {
                    $bytes = file_get_contents($tmp, false, null, 0, 12);
                    if (substr($bytes,0,3) === "\xFF\xD8\xFF")           $mime = 'image/jpeg';
                    elseif (substr($bytes,0,8) === "\x89PNG\r\n\x1a\n")  $mime = 'image/png';
                    elseif (substr($bytes,0,6) === 'GIF89a' || substr($bytes,0,6) === 'GIF87a') $mime = 'image/gif';
                    elseif (substr($bytes,0,4) === 'RIFF')               $mime = 'image/webp';
                }

                $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];

                if (!isset($allowed[$mime])) {
                    $error = 'Only JPG, PNG, GIF or WEBP allowed. Detected: ' . ($mime ?: 'unknown');
                } elseif ($_FILES['image']['size'] > 2097152) {
                    $error = 'Image must be under 2 MB.';
                } else {
                    $ext      = $allowed[$mime];
                    $filename = 'ad_' . time() . '_' . substr(md5(uniqid('', true)), 0, 8) . '.' . $ext;
                    if (move_uploaded_file($tmp, $uploadDir . $filename)) {
                        $pdo->prepare("INSERT INTO dashboard_ads (title, image_file, link_url, sort_order) VALUES (?,?,?,?)")
                            ->execute([$title ?: 'Ad', $filename, $link, $sort]);
                        $success = 'Ad added successfully.';
                    } else {
                        $error = 'Could not save file to: ' . $uploadDir . ' — check folder permissions (set to 755).';
                    }
                }
            }

        // TOGGLE
        } elseif ($action === 'toggle') {
            $id = (int)($_POST['ad_id'] ?? 0);
            if ($id > 0) {
                $s = $pdo->prepare("SELECT is_active FROM dashboard_ads WHERE id=?");
                $s->execute([$id]);
                $cur = (int)$s->fetchColumn();
                $pdo->prepare("UPDATE dashboard_ads SET is_active=? WHERE id=?")->execute([($cur ? 0 : 1), $id]);
                $success = 'Ad status updated.';
            }

        // DELETE
        } elseif ($action === 'delete') {
            $id = (int)($_POST['ad_id'] ?? 0);
            if ($id > 0) {
                $s = $pdo->prepare("SELECT image_file FROM dashboard_ads WHERE id=?");
                $s->execute([$id]);
                $file = $s->fetchColumn();
                if ($file && file_exists($uploadDir . $file)) @unlink($uploadDir . $file);
                $pdo->prepare("DELETE FROM dashboard_ads WHERE id=?")->execute([$id]);
                $success = 'Ad deleted.';
            }

        // UPDATE
        } elseif ($action === 'update') {
            $id   = (int)($_POST['ad_id'] ?? 0);
            $link = trim($_POST['link_url'] ?? '');
            $sort = max(0, (int)($_POST['sort_order'] ?? 0));
            $ttl  = trim($_POST['title'] ?? '');
            if ($link === '') $link = '#';
            if ($id > 0) {
                $pdo->prepare("UPDATE dashboard_ads SET title=?, link_url=?, sort_order=? WHERE id=?")
                    ->execute([$ttl, $link, $sort, $id]);
                $success = 'Ad updated.';
            }
        }
    }
}

$ads = [];
try {
    $ads = $pdo->query("SELECT * FROM dashboard_ads ORDER BY sort_order ASC, id ASC")->fetchAll();
} catch (Exception $e) {}
?>

<?php if (!$uploadOk): ?>
<div class="alert alert-danger" style="margin-bottom:16px;">
  <i class="fi fi-rr-triangle-warning"></i>
  <span>
    Upload folder missing or not writable: <code><?= htmlspecialchars($uploadDir) ?></code><br>
    Go to <strong>cPanel → File Manager → assets/uploads/</strong> → create folder <strong>ads</strong> → set permissions to <strong>755</strong>.
  </span>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger"><i class="fi fi-rr-cross-circle"></i><span><?= htmlspecialchars($error) ?></span></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success"><i class="fi fi-rr-check"></i><span><?= htmlspecialchars($success) ?></span></div>
<?php endif; ?>

<!-- Add New Ad -->
<div style="background:var(--bg2);border-radius:var(--radius);padding:22px;box-shadow:var(--shadow);margin-bottom:24px;">
  <h3 style="font-weight:800;font-size:15px;margin-bottom:16px;"><i class="fi fi-rr-picture"></i> Add New Ad</h3>
  <form method="POST" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
      <div>
        <label style="font-size:12px;font-weight:700;color:var(--text2);display:block;margin-bottom:4px;">Title (optional)</label>
        <input type="text" name="title" class="form-control" placeholder="e.g. Weekend Promo">
      </div>
      <div>
        <label style="font-size:12px;font-weight:700;color:var(--text2);display:block;margin-bottom:4px;">Link URL (optional)</label>
        <input type="text" name="link_url" class="form-control" placeholder="https://example.com">
      </div>
      <div>
        <label style="font-size:12px;font-weight:700;color:var(--text2);display:block;margin-bottom:4px;">Sort Order (0 = first)</label>
        <input type="number" name="sort_order" class="form-control" value="0" min="0" max="99">
      </div>
      <div>
        <label style="font-size:12px;font-weight:700;color:var(--text2);display:block;margin-bottom:4px;">Image * (JPG/PNG/WEBP, max 2MB)</label>
        <input type="file" name="image" class="form-control" accept="image/*" required>
      </div>
    </div>
    <button type="submit" class="btn btn-primary"><i class="fi fi-rr-add"></i> Upload & Add Ad</button>
  </form>
</div>

<!-- Current Ads -->
<div style="background:var(--bg2);border-radius:var(--radius);padding:22px;box-shadow:var(--shadow);">
  <h3 style="font-weight:800;font-size:15px;margin-bottom:4px;">Current Ads</h3>
  <p style="font-size:13px;color:var(--text2);margin-bottom:16px;">Active ads slide on the user dashboard (max 5 shown).</p>

  <?php if (empty($ads)): ?>
  <p style="color:var(--text3);text-align:center;padding:30px 0;">No ads yet. Upload one above.</p>
  <?php else: ?>
  <div class="table-wrap">
  <table class="admin-table">
    <thead>
      <tr><th>Preview</th><th>Title</th><th>Link</th><th>Order</th><th>Status</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($ads as $ad):
        $adId    = (int)$ad['id'];
        $adTitle = htmlspecialchars($ad['title'], ENT_QUOTES, 'UTF-8');
        $adLink  = ($ad['link_url'] === '#' || $ad['link_url'] === '') ? '' : htmlspecialchars($ad['link_url'], ENT_QUOTES, 'UTF-8');
        $adSort  = (int)$ad['sort_order'];
        $adImg   = htmlspecialchars($ad['image_file'], ENT_QUOTES, 'UTF-8');
        $adActive = (int)$ad['is_active'];
    ?>
    <tr>
      <td>
        <img src="<?= $uploadWeb . $adImg ?>"
             style="width:100px;height:52px;object-fit:cover;border-radius:6px;display:block;background:#eee;"
             onerror="this.style.background='#fce4e4';">
      </td>
      <td style="font-weight:700;"><?= $adTitle ?: '—' ?></td>
      <td style="font-size:12px;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
        <?php if ($adLink): ?>
          <a href="<?= $adLink ?>" target="_blank" style="color:var(--primary);"><?= $adLink ?></a>
        <?php else: ?>
          <span style="color:var(--text3);">—</span>
        <?php endif; ?>
      </td>
      <td><?= $adSort ?></td>
      <td>
        <span class="pill pill-<?= $adActive ? 'success' : 'gray' ?>">
          <?= $adActive ? 'Active' : 'Hidden' ?>
        </span>
      </td>
      <td>
        <div style="display:flex;gap:5px;">
          <!-- Edit -->
          <button onclick="openAdModal(<?= $adId ?>,'<?= addslashes($adTitle) ?>','<?= addslashes($adLink) ?>',<?= $adSort ?>)"
                  class="btn btn-primary btn-sm" title="Edit"><i class="fi fi-rr-pencil"></i></button>
          <!-- Toggle -->
          <form method="POST" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="ad_id"  value="<?= $adId ?>">
            <button type="submit" class="btn btn-sm <?= $adActive ? 'btn-danger' : 'btn-success' ?>"
                    title="<?= $adActive ? 'Hide' : 'Show' ?>">
              <i class="fi fi-rr-<?= $adActive ? 'eye-crossed' : 'eye' ?>"></i>
            </button>
          </form>
          <!-- Delete -->
          <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this ad?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="ad_id"  value="<?= $adId ?>">
            <button type="submit" class="btn btn-danger btn-sm" title="Delete"><i class="fi fi-rr-trash"></i></button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="ad-modal" style="align-items:center;">
  <div class="modal-sheet" style="border-radius:var(--radius);max-width:440px;padding:24px;">
    <h3 class="modal-title" style="margin-bottom:16px;">Edit Ad</h3>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="ad_id"  id="edit-id">
      <div class="form-group">
        <label class="form-label">Title</label>
        <input type="text" name="title" id="edit-title" class="form-control" placeholder="Ad title">
      </div>
      <div class="form-group">
        <label class="form-label">Link URL</label>
        <input type="text" name="link_url" id="edit-link" class="form-control" placeholder="https://...">
      </div>
      <div class="form-group">
        <label class="form-label">Sort Order</label>
        <input type="number" name="sort_order" id="edit-sort" class="form-control" min="0" max="99">
      </div>
      <button type="submit" class="btn btn-primary btn-block">Save Changes</button>
    </form>
    <button onclick="closeModal('ad-modal')" class="btn btn-ghost btn-block mt-1">Cancel</button>
  </div>
</div>

<script>
function openAdModal(id, title, link, sort) {
  document.getElementById('edit-id').value    = id;
  document.getElementById('edit-title').value = title;
  document.getElementById('edit-link').value  = link;
  document.getElementById('edit-sort').value  = sort;
  openModal('ad-modal');
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
