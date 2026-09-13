<?php http_response_code(404); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Page Not Found — DonnieSMS</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --purple: #7C3AED;
      --purple-light: #EDE9FE;
      --purple-dark: #5B21B6;
      --purple-glow: #A78BFA;
      --bg: #0D0A1A;
      --surface: #160F2B;
      --surface2: #1E1538;
      --text: #F5F3FF;
      --muted: #A89FC4;
      --border: rgba(124, 58, 237, 0.2);
    }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      position: relative;
    }

    /* Background grid */
    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background-image:
        linear-gradient(rgba(124,58,237,0.06) 1px, transparent 1px),
        linear-gradient(90deg, rgba(124,58,237,0.06) 1px, transparent 1px);
      background-size: 48px 48px;
      pointer-events: none;
      z-index: 0;
    }

    /* Glowing orb */
    body::after {
      content: '';
      position: fixed;
      top: -120px;
      left: 50%;
      transform: translateX(-50%);
      width: 600px;
      height: 600px;
      background: radial-gradient(circle, rgba(124,58,237,0.18) 0%, transparent 70%);
      pointer-events: none;
      z-index: 0;
    }

    nav {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      padding: 1.2rem 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid var(--border);
      background: rgba(13, 10, 26, 0.85);
      backdrop-filter: blur(12px);
      z-index: 10;
    }

    .logo {
      font-family: 'Syne', sans-serif;
      font-weight: 800;
      font-size: 1.3rem;
      color: var(--text);
      text-decoration: none;
      letter-spacing: -0.02em;
    }

    .logo span {
      color: var(--purple-glow);
    }

    .nav-btn {
      font-family: 'DM Sans', sans-serif;
      font-size: 0.85rem;
      font-weight: 500;
      color: var(--muted);
      text-decoration: none;
      border: 1px solid var(--border);
      padding: 0.45rem 1rem;
      border-radius: 8px;
      transition: all 0.2s;
    }

    .nav-btn:hover {
      color: var(--text);
      border-color: var(--purple);
      background: rgba(124,58,237,0.1);
    }

    main {
      position: relative;
      z-index: 1;
      text-align: center;
      padding: 2rem;
      max-width: 580px;
      width: 100%;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 0.75rem;
      font-weight: 500;
      color: var(--purple-glow);
      background: rgba(124,58,237,0.12);
      border: 1px solid rgba(124,58,237,0.3);
      padding: 0.35rem 0.85rem;
      border-radius: 100px;
      margin-bottom: 2rem;
      letter-spacing: 0.05em;
      text-transform: uppercase;
    }

    .badge::before {
      content: '';
      width: 6px;
      height: 6px;
      background: var(--purple-glow);
      border-radius: 50%;
      animation: pulse 2s infinite;
    }

    @keyframes pulse {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: 0.4; transform: scale(0.7); }
    }

    .big-num {
      font-family: 'Syne', sans-serif;
      font-weight: 800;
      font-size: clamp(7rem, 22vw, 11rem);
      line-height: 1;
      letter-spacing: -0.05em;
      background: linear-gradient(135deg, #A78BFA 0%, #7C3AED 50%, #4C1D95 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      margin-bottom: 0.5rem;
      position: relative;
      display: inline-block;
    }

    .big-num::after {
      content: '404';
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, #A78BFA 0%, #7C3AED 50%, #4C1D95 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      filter: blur(20px);
      opacity: 0.4;
      z-index: -1;
    }

    h1 {
      font-family: 'Syne', sans-serif;
      font-weight: 700;
      font-size: clamp(1.4rem, 4vw, 1.9rem);
      letter-spacing: -0.02em;
      color: var(--text);
      margin-bottom: 0.85rem;
    }

    p {
      font-size: 1rem;
      color: var(--muted);
      line-height: 1.7;
      margin-bottom: 2.5rem;
    }

    .actions {
      display: flex;
      gap: 0.85rem;
      justify-content: center;
      flex-wrap: wrap;
    }

    .btn-primary {
      font-family: 'DM Sans', sans-serif;
      font-size: 0.95rem;
      font-weight: 500;
      color: #fff;
      background: var(--purple);
      border: none;
      padding: 0.75rem 1.75rem;
      border-radius: 10px;
      text-decoration: none;
      cursor: pointer;
      transition: all 0.2s;
      box-shadow: 0 0 20px rgba(124,58,237,0.35);
    }

    .btn-primary:hover {
      background: var(--purple-dark);
      transform: translateY(-1px);
      box-shadow: 0 0 30px rgba(124,58,237,0.5);
    }

    .btn-secondary {
      font-family: 'DM Sans', sans-serif;
      font-size: 0.95rem;
      font-weight: 500;
      color: var(--muted);
      background: transparent;
      border: 1px solid var(--border);
      padding: 0.75rem 1.75rem;
      border-radius: 10px;
      text-decoration: none;
      cursor: pointer;
      transition: all 0.2s;
    }

    .btn-secondary:hover {
      color: var(--text);
      border-color: var(--purple);
      background: rgba(124,58,237,0.08);
    }

    .divider {
      width: 48px;
      height: 2px;
      background: linear-gradient(90deg, var(--purple), transparent);
      margin: 2rem auto;
      border-radius: 2px;
    }

    .links {
      display: flex;
      gap: 1.5rem;
      justify-content: center;
      flex-wrap: wrap;
    }

    .links a {
      font-size: 0.85rem;
      color: var(--muted);
      text-decoration: none;
      transition: color 0.2s;
    }

    .links a:hover { color: var(--purple-glow); }

    footer {
      position: fixed;
      bottom: 1.2rem;
      left: 0;
      right: 0;
      text-align: center;
      font-size: 0.78rem;
      color: rgba(168,159,196,0.4);
      z-index: 1;
    }

    @media (max-width: 480px) {
      .actions { flex-direction: column; align-items: center; }
      .btn-primary, .btn-secondary { width: 100%; max-width: 280px; text-align: center; }
    }
  </style>
</head>
<body>

  <nav>
    <a href="/" class="logo">Donnie<span>SMS</span></a>
    <a href="/login.php" class="nav-btn">Login</a>
  </nav>

  <main>
    <div class="badge">Error 404</div>

    <div class="big-num">404</div>

    <h1>Page not found</h1>
    <p>Oops! The page you're looking for doesn't exist or has been moved.<br>Let's get you back on track.</p>

    <div class="actions">
      <a href="/" class="btn-primary">← Back to Home</a>
      <a href="/register.php" class="btn-secondary">Create Account</a>
    </div>

    <div class="divider"></div>

    <div class="links">
      <a href="/">Home</a>
      <a href="/login.php">Login</a>
      <a href="/register.php">Register</a>
      <a href="/pages/support.php">Support</a>
    </div>
  </main>

  <footer>© 2026 DonnieSMS · All rights reserved</footer>

</body>
</html>