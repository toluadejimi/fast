<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
$pageTitle = 'Theme & Logo';
require_once __DIR__ . '/includes/admin_header.php';

$error = $success = '';
$uploadDir = ROOT_PATH . '/assets/uploads/logo/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Save colors
    if (isset($_POST['save_theme'])) {
        $fields = ['primary_color','primary_dark','primary_light','accent_color','bg_dark_color'];
        foreach ($fields as $f) {
            if (!empty($_POST[$f])) set_setting($f, clean($_POST[$f]));
        }
        $success = 'Theme colors saved successfully.';
    }

    // Upload logo
    elseif (isset($_POST['upload_logo'])) {
        if (!empty($_FILES['logo']['name'])) {
            $ext     = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $allowed = ['png','jpg','jpeg','svg','webp'];
            if (!in_array($ext, $allowed)) {
                $error = 'Only PNG, JPG, SVG, WebP allowed.';
            } elseif ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
                $error = 'Logo must be under 2MB.';
            } else {
                // Delete old logo
                $old = get_setting('logo_path','');
                if ($old && file_exists($uploadDir.$old)) @unlink($uploadDir.$old);
                $filename = 'logo_'.time().'.'.$ext;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir.$filename)) {
                    set_setting('logo_path', $filename);
                    $success = 'Logo uploaded successfully.';
                } else {
                    $error = 'Upload failed. Check folder permissions on assets/uploads/logo/';
                }
            }
        } else { $error = 'Please select a logo file.'; }
    }

    // Remove logo
    elseif (isset($_POST['remove_logo'])) {
        $old = get_setting('logo_path','');
        if ($old && file_exists($uploadDir.$old)) @unlink($uploadDir.$old);
        set_setting('logo_path', '');
        $success = 'Logo removed. Site name text will be used instead.';
    }

    // Reset to purple default
    elseif (isset($_POST['reset_theme'])) {
        set_setting('primary_color',  '#7C3AED');
        set_setting('primary_dark',   '#5B21B6');
        set_setting('primary_light',  '#EDE9FE');
        set_setting('accent_color',   '#A78BFA');
        set_setting('bg_dark_color',  '#0F0A1E');
        $success = 'Theme reset to default purple.';
    }
}

$logoPath = get_setting('logo_path','');
?>

<?php if ($error): ?><div class="alert alert-danger"><i class="fi fi-rr-cross-circle"></i><span><?= clean($error) ?></span></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><i class="fi fi-rr-check"></i><span><?= clean($success) ?></span></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

<!-- Logo Upload -->
<div style="background:var(--bg2);border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <h3 style="font-weight:800;font-size:15px;margin-bottom:16px;">Site Logo</h3>

  <!-- Current logo preview -->
  <div style="background:var(--bg3);border-radius:var(--radius-sm);padding:20px;text-align:center;margin-bottom:16px;border:2px dashed var(--border);">
    <?php if ($logoPath): ?>
    <img src="<?= SITE_URL ?>/assets/uploads/logo/<?= clean($logoPath) ?>"
         alt="Current Logo" style="max-height:60px;max-width:200px;object-fit:contain;">
    <div style="font-size:12px;color:var(--text3);margin-top:8px;">Current logo</div>
    <?php else: ?>
    <div style="font-size:20px;font-weight:900;color:var(--primary);"><?= clean(get_setting('site_name',SITE_NAME)) ?></div>
    <div style="font-size:12px;color:var(--text3);margin-top:6px;">No logo — using site name text</div>
    <?php endif; ?>
  </div>

  <form method="POST" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="form-group">
      <label class="form-label">Upload New Logo (PNG recommended)</label>
      <input type="file" name="logo" class="form-control" accept=".png,.jpg,.jpeg,.svg,.webp" required>
      <div style="font-size:12px;color:var(--text3);margin-top:4px;">PNG with transparent background recommended. Max 2MB.</div>
    </div>
    <button type="submit" name="upload_logo" class="btn btn-primary btn-block">
      <i class="fi fi-rr-upload"></i> Upload Logo
    </button>
  </form>

  <?php if ($logoPath): ?>
  <form method="POST" style="margin-top:10px;" onsubmit="return confirm('Remove logo and use site name text?')">
    <button type="submit" name="remove_logo" class="btn btn-danger btn-block">
      <i class="fi fi-rr-trash"></i> Remove Logo
    </button>
  </form>
  <?php endif; ?>
</div>

<!-- Color Picker -->
<div style="background:var(--bg2);border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-weight:800;font-size:15px;">Theme Colors</h3>
    <form method="POST">
    <?= csrf_field() ?>
      <button type="submit" name="reset_theme" class="btn btn-ghost btn-sm"
              onclick="return confirm('Reset to default purple theme?')">
        <i class="fi fi-rr-refresh"></i> Reset
      </button>
    </form>
  </div>

  <!-- Live preview -->
  <div id="theme-preview" style="background:linear-gradient(135deg,var(--primary),var(--primary-dark));
       border-radius:var(--radius-sm);padding:16px;margin-bottom:16px;color:#fff;text-align:center;">
    <div style="font-size:15px;font-weight:900;">Preview</div>
    <div style="font-size:12px;opacity:.8;margin-top:3px;">Colors update live as you pick</div>
    <div style="margin-top:10px;display:flex;gap:8px;justify-content:center;">
      <span style="background:rgba(255,255,255,.2);padding:5px 12px;border-radius:6px;font-size:12px;">Button</span>
      <span style="background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.4);padding:5px 12px;border-radius:6px;font-size:12px;">Outline</span>
    </div>
  </div>

  <form method="POST">
    <?= csrf_field() ?>
    <?php
    $colorFields = [
        'primary_color' => ['Primary Color',       'Main brand color (buttons, links, accents)'],
        'primary_dark'  => ['Primary Dark',         'Darker shade for gradients and hover states'],
        'primary_light' => ['Primary Light',        'Light tint for backgrounds and badges'],
        'accent_color'  => ['Accent Color',         'Used for highlights and secondary elements'],
        'bg_dark_color' => ['Dark Mode Background', 'Background color in dark mode'],
    ];
    foreach ($colorFields as $key => [$label, $desc]):
        $val = get_setting($key, '#7C3AED');
    ?>
    <div class="form-group">
      <label class="form-label"><?= $label ?></label>
      <div style="display:flex;align-items:center;gap:10px;">
        <input type="color" name="<?= $key ?>" value="<?= clean($val) ?>"
               class="color-picker" data-var="--<?= str_replace('_color','',$key) ?>"
               style="flex-shrink:0;">
        <div>
          <div style="font-size:13px;font-weight:600;"><?= clean($val) ?></div>
          <div style="font-size:11px;color:var(--text3);"><?= $desc ?></div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>

    <button type="submit" name="save_theme" class="btn btn-primary btn-block">
      <i class="fi fi-rr-disk"></i> Save Theme Colors
    </button>
  </form>

  <div class="alert alert-info" style="margin-top:14px;margin-bottom:0;font-size:12px;">
    <i class="fi fi-rr-info"></i>
    <span>Colors apply to both user side and admin side instantly after saving.</span>
  </div>
</div>

</div>

<script>
// Update preview gradient live
document.querySelectorAll('.color-picker').forEach(input => {
  input.addEventListener('input', function() {
    const primary = document.querySelector('[name="primary_color"]')?.value || '#7C3AED';
    const dark    = document.querySelector('[name="primary_dark"]')?.value  || '#5B21B6';
    document.getElementById('theme-preview').style.background =
      `linear-gradient(135deg, ${primary}, ${dark})`;
  });
});
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
