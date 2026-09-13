<?php
// ============================================================
//  bigboss/broadcast.php — send email notifications to
//  segments of users (inactive, no balance, no VA, no purchase)
//
//  Sending happens via bigboss/ajax-broadcast-send.php in small
//  batches (5 at a time) called repeatedly by the JS below, so
//  a large recipient list can never time out a single request.
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
$pageTitle = 'Broadcast Emails';
require_once __DIR__ . '/includes/admin_header.php';
$csrfToken = csrf_token(); // used by the JS batches below
?>

<div class="glass" style="padding:20px;border-radius:var(--radius);margin-bottom:20px;">
  <h3 style="margin-top:0;">Send Email Notification</h3>
  <p style="color:var(--text3);font-size:13px;">
    Choose who should receive this email, write your message, and confirm the count before sending.
    Use <code>{name}</code> in your message to insert each user's username automatically.
    Emails send in small batches in the background, so this works fine even for large lists.
  </p>

  <div id="broadcastFormWrap">
    <form id="broadcastForm" onsubmit="return false;">
      <label class="form-label">Send To</label>
      <div id="segmentOptions" style="display:flex;flex-direction:column;gap:8px;margin-bottom:14px;">
        <label class="s-row" style="cursor:pointer;">
          <input type="radio" name="segment" value="all" checked> &nbsp;All Users
        </label>
        <label class="s-row" style="cursor:pointer;">
          <input type="radio" name="segment" value="no_balance"> &nbsp;Users With No Wallet Balance
        </label>
        <label class="s-row" style="cursor:pointer;display:flex;align-items:center;gap:8px;">
          <input type="radio" name="segment" value="inactive"> Inactive for
          <input type="number" name="days" value="3" min="1" class="form-control" style="width:70px;display:inline-block;" onclick="document.querySelector('[value=inactive]').checked=true;">
          + days
        </label>
        <label class="s-row" style="cursor:pointer;">
          <input type="radio" name="segment" value="no_va"> &nbsp;Never Generated an Account Number
        </label>
        <label class="s-row" style="cursor:pointer;">
          <input type="radio" name="segment" value="no_purchase"> &nbsp;Never Made a Purchase
        </label>
      </div>

      <div class="glass" style="padding:12px 16px;margin-bottom:16px;background:rgba(124,58,237,0.06);">
        <i class="fi fi-rr-users"></i>
        This will email <strong id="recipientCount">…</strong> user(s) — <span id="segmentLabel"></span>
      </div>

      <div class="form-group">
        <label class="form-label">Subject</label>
        <input type="text" id="subjectInput" class="form-control" placeholder="e.g. We miss you!" required>
      </div>

      <div class="form-group">
        <label class="form-label">Message</label>
        <textarea id="messageInput" class="form-control" rows="6" required placeholder="Hi {name}, we noticed..."></textarea>
      </div>

      <label style="display:flex;align-items:center;gap:8px;font-size:13px;margin-bottom:16px;">
        <input type="checkbox" id="confirmCheck" required>
        I understand this will email everyone in the selected group.
      </label>

      <button type="button" id="sendBtn" class="btn btn-primary" onclick="startBroadcast()">
        <i class="fi fi-rr-paper-plane"></i> Send Broadcast
      </button>
    </form>
  </div>

  <div id="progressWrap" style="display:none;">
    <div class="alert alert-success" id="progressMsg" style="margin-bottom:10px;">Sending…</div>
    <div style="background:var(--bg2);border-radius:8px;height:10px;overflow:hidden;margin-bottom:10px;">
      <div id="progressBar" style="background:var(--primary);height:100%;width:0%;transition:width .3s;"></div>
    </div>
    <div id="progressText" style="font-size:13px;color:var(--text3);"></div>
  </div>
</div>

<script>
const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;

async function refreshCount() {
    const segment = document.querySelector('input[name="segment"]:checked').value;
    const days = document.querySelector('input[name="days"]').value || 3;
    try {
        const res = await fetch('ajax-segment-count.php?segment=' + encodeURIComponent(segment) + '&days=' + encodeURIComponent(days));
        const json = await res.json();
        if (json.success) {
            document.getElementById('recipientCount').textContent = json.count;
            document.getElementById('segmentLabel').textContent = json.label;
        }
    } catch (e) {
        document.getElementById('recipientCount').textContent = '?';
    }
}
document.querySelectorAll('input[name="segment"], input[name="days"]').forEach(el => {
    el.addEventListener('change', refreshCount);
});
document.querySelector('input[name="days"]').addEventListener('input', refreshCount);
refreshCount();

async function startBroadcast() {
    const subject = document.getElementById('subjectInput').value.trim();
    const message = document.getElementById('messageInput').value.trim();
    const confirmed = document.getElementById('confirmCheck').checked;

    if (!subject || !message) { alert('Subject and message are both required.'); return; }
    if (!confirmed) { alert('Please check the confirmation box first.'); return; }
    if (!confirm('Send this email now?')) return;

    const segment = document.querySelector('input[name="segment"]:checked').value;
    const days = document.querySelector('input[name="days"]').value || 3;

    document.getElementById('broadcastFormWrap').style.display = 'none';
    document.getElementById('progressWrap').style.display = 'block';

    let offset = 0, totalSent = 0, totalFailed = 0, total = 0, done = false;

    while (!done) {
        try {
            const res = await fetch('ajax-broadcast-send.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    csrf_token: CSRF_TOKEN, segment, days, subject, message, offset
                })
            });
            const json = await res.json();

            if (!json.success) {
                document.getElementById('progressMsg').className = 'alert alert-danger';
                document.getElementById('progressMsg').textContent = json.error || 'Something went wrong.';
                return;
            }

            totalSent += json.sent;
            totalFailed += json.failed;
            total = json.total;
            offset = json.next_offset;
            done = json.done;

            const pct = total > 0 ? Math.min(100, Math.round((offset / total) * 100)) : 100;
            document.getElementById('progressBar').style.width = pct + '%';
            document.getElementById('progressText').textContent =
                `Sent ${totalSent}${totalFailed > 0 ? ', ' + totalFailed + ' failed' : ''} of ${total}...`;

            // Small pause between batches so this doesn't look like a burst
            // to your mail server — doesn't touch PHP, so it can't time out.
            if (!done) {
                await new Promise(resolve => setTimeout(resolve, 2500));
            }

        } catch (e) {
            document.getElementById('progressMsg').className = 'alert alert-danger';
            document.getElementById('progressMsg').textContent = 'Network error — some emails may not have sent. Refresh and check before retrying.';
            return;
        }
    }

    document.getElementById('progressMsg').textContent =
        `Done! Sent to ${totalSent} user(s)` + (totalFailed > 0 ? `, ${totalFailed} failed.` : '.');
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
