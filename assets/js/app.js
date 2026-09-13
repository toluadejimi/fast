// ============================================================
//  DonnieSMS OTP – assets/js/app.js
// ============================================================

// ── Dark / Light Mode ─────────────────────────────────────────
function initTheme() {
  const saved = localStorage.getItem('theme') || 'light';
  document.documentElement.setAttribute('data-theme', saved);
  updateThemeIcon(saved);
}
function toggleTheme() {
  const curr = document.documentElement.getAttribute('data-theme') || 'light';
  const next = curr === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', next);
  localStorage.setItem('theme', next);
  updateThemeIcon(next);
}
function updateThemeIcon(theme) {
  const btn = document.getElementById('theme-toggle');
  if (!btn) return;
  btn.innerHTML = theme === 'dark'
    ? '<i class="fi fi-rr-sun"></i>'
    : '<i class="fi fi-rr-moon"></i>';
}

// ── Bottom Nav active state ────────────────────────────────────
function initBottomNav() {
  const path = window.location.pathname;
  document.querySelectorAll('.bottom-nav a').forEach(a => {
    const href = a.getAttribute('href') || '';
    const page = href.split('/').pop().replace('.php','');
    if (page && path.includes(page)) a.classList.add('active');
    else if (page === 'dashboard' && (path.endsWith('/') || path.includes('dashboard'))) a.classList.add('active');
  });
}

// ── Modals ────────────────────────────────────────────────────
function openModal(id) {
  const m = document.getElementById(id);
  if (m) { m.classList.add('open'); document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
  const m = document.getElementById(id);
  if (m) { m.classList.remove('open'); document.body.style.overflow = ''; }
}
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('open');
    document.body.style.overflow = '';
  }
});

// ── Copy to clipboard ─────────────────────────────────────────
function copyText(text, el) {
  navigator.clipboard.writeText(text).then(() => {
    const orig = el.innerHTML;
    el.innerHTML = '<i class="fi fi-rr-check"></i>';
    el.style.color = 'var(--success)';
    setTimeout(() => { el.innerHTML = orig; el.style.color = ''; }, 2000);
  });
}

// ── OTP Live Polling ──────────────────────────────────────────
let pollTimer = null;
function startOtpPoll(orderId, statusEl, codeEl) {
  if (pollTimer) clearInterval(pollTimer);
  let tries = 0;
  pollTimer = setInterval(async () => {
    if (++tries > 60) {
      clearInterval(pollTimer);
      if (statusEl) statusEl.innerHTML = '<span class="pill pill-gray">Timed out — please refresh</span>';
      return;
    }
    try {
      const r = await fetch('/api/check-otp.php?id=' + orderId);
      const d = await r.json();
      if (d.otp) {
        clearInterval(pollTimer);
        // FIX: reveal div uses inline style="display:none" not a CSS class
        var revealDiv = document.getElementById('otp-reveal');
        if (revealDiv) revealDiv.style.display = 'block';
        if (codeEl) codeEl.textContent = d.otp;
        if (statusEl) statusEl.style.display = 'none';
      } else if (['CANCELED','TIMEOUT','BANNED','EXPIRED'].includes(d.status)) {
        clearInterval(pollTimer);
        if (statusEl) statusEl.innerHTML = '<span class="pill pill-danger">' + d.status + '</span>';
      }
    } catch(e) {}
  }, 3000);
}

// ── Countdown Timer ───────────────────────────────────────────
function startCountdown(seconds, el) {
  let secs = seconds;
  function tick() {
    if (!el) return;
    if (secs <= 0) { el.textContent = 'Expired'; el.style.color = 'var(--danger)'; return; }
    const m = String(Math.floor(secs / 60)).padStart(2,'0');
    const s = String(secs % 60).padStart(2,'0');
    el.textContent = m + ':' + s;
    if (secs <= 60) el.style.color = 'var(--danger)';
    secs--;
    setTimeout(tick, 1000);
  }
  tick();
}

// ── Generate Virtual Account ──────────────────────────────────
function generateVA(provider, bank) {
  const btnId = 'gen-btn-' + provider + (bank ? '-' + bank : '');
  const btn = document.getElementById(btnId);
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fi fi-rr-spinner" style="animation:spin .8s linear infinite;"></i> Generating...'; }

  let body = 'provider=' + encodeURIComponent(provider);
  if (bank) body += '&bank=' + encodeURIComponent(bank);

  fetch('/api/generate-va.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
    body: body
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      document.getElementById('va-num').textContent  = d.account_number;
      document.getElementById('va-bank').textContent = d.bank_name;
      document.getElementById('va-name').textContent = d.account_name;
      document.getElementById('step-gen').style.display  = 'none';
      document.getElementById('step-done').style.display = 'block';
    } else {
      alert(d.message || 'Failed. Please try again.');
      if (btn) { btn.disabled = false; btn.innerHTML = 'Generate Account'; }
    }
  })
  .catch(() => {
    alert('Network error. Please try again.');
    if (btn) { btn.disabled = false; btn.innerHTML = 'Generate Account'; }
  });
}

// ── PIN input – digits only ───────────────────────────────────
function initPinInputs() {
  document.querySelectorAll('[data-pin]').forEach(el => {
    el.addEventListener('input', () => {
      el.value = el.value.replace(/\D/g, '').slice(0, 4);
    });
  });
}

// ── Country / service search ──────────────────────────────────
function initSearch(inputId, listSelector) {
  const input = document.getElementById(inputId);
  if (!input) return;
  input.addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll(listSelector).forEach(el => {
      const name = (el.dataset.name || el.textContent).toLowerCase();
      el.style.display = name.includes(q) ? '' : 'none';
    });
    // Show "no results" if all hidden
    const visible = document.querySelectorAll(listSelector + ':not([style*="display: none"])').length;
    const noRes = document.getElementById('no-results');
    if (noRes) noRes.style.display = visible === 0 ? 'block' : 'none';
  });
}

// ── Admin color picker live preview ──────────────────────────
function initColorPicker() {
  document.querySelectorAll('.color-picker').forEach(input => {
    input.addEventListener('input', function() {
      const varName = this.dataset.var;
      if (varName) document.documentElement.style.setProperty(varName, this.value);
    });
  });
}

// ── Flash auto-dismiss ────────────────────────────────────────
function initFlash() {
  const flash = document.querySelector('.flash-msg');
  if (flash) setTimeout(() => { flash.style.opacity = '0'; setTimeout(() => flash.remove(), 400); }, 4000);
}

// ── Spin animation ────────────────────────────────────────────
const spinStyle = document.createElement('style');
spinStyle.textContent = '@keyframes spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}';
document.head.appendChild(spinStyle);

// selectService and submitBuy are defined per-page (numbers.php)

// ── Init ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  initTheme();
  initBottomNav();
  initPinInputs();
  initColorPicker();
  initFlash();
  initSearch('svc-search', '.svc-item');
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('/pwa/sw.js').catch(() => {});
});
