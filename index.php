<?php
require_once 'config.php';

// Capture from URL and store in Session if present (from QR scan)
if (isset($_GET['pid'])) {
  $_SESSION['pending_pid'] = $_GET['pid'];
  $_SESSION['pending_type'] = substr($_GET['pid'], 0, 4);
}

$pid = $_SESSION['pending_pid'] ?? '';
$type = $_SESSION['pending_type'] ?? '';

// Redirect to dashboard if logged in and NO pending product to claim
if (isset($_SESSION['user_id']) && !$pid) {
  header("Location: dashboard.php");
  exit;
}

// Check if product is already registered
$isTaken = false;
if ($pid) {
  $stmt = $pdo->prepare("SELECT id FROM warranties WHERE product_id = ?");
  $stmt->execute([$pid]);
  if ($stmt->fetch()) {
    $isTaken = true;
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="format-detection" content="telephone=no">
  <meta name="description" content="H₂O Warranty – Register and manage your product warranty in seconds.">
  <title>H₂O Warranty – Register</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

  <div class="bg-blobs">
    <div class="blob"></div>
    <div class="blob"></div>
  </div>

  <div class="container">

    <!-- ── Header ─────────────────────────────────── -->
    <header>
      <div style="height: 100px; display: flex; align-items: center; justify-content: center;">
        <img src="assets/img/h2o_purifier_w.png" alt="H2O Logo" style="height: 100%; width: auto; object-fit: contain;">
      </div>
      <h1>H₂O Warranty</h1>
      <p class="subtitle">Secure your product's future in seconds</p>
    </header>

    <!-- ── Glass Card ─────────────────────────────── -->
    <div class="glass-card">

      <?php if ($pid && $isTaken): ?>
        <!-- ── Warning: Product already registered ── -->
        <div class="warning-box" style="text-align: center; padding: 1.5rem 0;">
          <div class="error-icon"
            style="width: 54px; height: 54px; background: rgba(244, 63, 94, 0.08); border: 1px solid rgba(244, 63, 94, 0.2); border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 2rem; color: var(--danger); box-shadow: 0 0 20px rgba(244,63,94,0.1);">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
              stroke-linecap="round">
              <circle cx="12" cy="12" r="10" />
              <line x1="15" y1="9" x2="9" y2="15" />
              <line x1="9" y1="9" x2="15" y2="15" />
            </svg>
          </div>
          <h2 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem; letter-spacing: -0.02em;">Already
            Registered</h2>
          <div class="claim-pid-wrap"
            style="display: inline-block; background: #010206; border: 1px solid rgba(255,255,255,0.05); padding: 0.4rem 0.8rem; border-radius: 8px; margin-bottom: 1.5rem;">
            <code
              style="font-family: inherit; font-weight: 700; color: var(--danger);"><?php echo htmlspecialchars($pid); ?></code>
          </div>
          <p style="color: var(--txt-2); font-size: 0.9rem; margin-bottom: 2rem; line-height: 1.6; padding: 0 1rem;">
            This product is already protected by an active warranty. If you believe this is an error, please contact our
            support team.
          </p>

          <?php if (isset($_SESSION['user_id'])): ?>
            <a href="dashboard.php" class="btn btn-support" style="text-decoration: none;">
              View My Dashboard
            </a>
          <?php else: ?>
            <button class="btn btn-primary" onclick="location.href='index.php?pid=';">
              Login to My Account
            </button>
            <p style="margin-top: 1.5rem; font-size: 0.8rem; color: var(--txt-dim);">
              Need to check another product? <a href="index.php?pid=" style="color: var(--primary);">Clear Scan</a>
            </p>
          <?php endif; ?>
        </div>

      <?php elseif (isset($_SESSION['user_id']) && $pid): ?>
        <!-- ── Claim: logged-in user with scanned product ── -->
        <div class="claim-box" style="text-align: center; padding: 1rem 0;">
          <div class="activation-icon"
            style="width: 54px; height: 54px; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 2rem; color: #10b981; box-shadow: 0 0 20px rgba(16,185,129,0.15);">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
              stroke-linecap="round">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
              <polyline points="22 4 12 14.01 9 11.01" />
            </svg>
          </div>

          <h2 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.25rem; letter-spacing: -0.02em;">Activate
            Warranty</h2>

          <!-- Reference ID Badge -->
          <div class="claim-pid-wrap"
            style="display: inline-flex; flex-direction: column; align-items: center; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.08); padding: 0.6rem 1.25rem; border-radius: 12px; margin-bottom: 1.25rem; backdrop-filter: blur(4px);">
            <span
              style="font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.15em; color: var(--txt-dim); margin-bottom: 0.2rem; font-weight: 700;">Reference
              ID</span>
            <code
              style="font-family: 'JetBrains Mono', monospace; font-size: 1rem; font-weight: 800; color: var(--primary); letter-spacing: 0.05em;"><?php echo htmlspecialchars($pid); ?></code>
          </div>

          <!-- Requirements Card -->
          <div class="requirements-box"
            style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 20px; padding: 1.25rem; margin-bottom: 1.5rem; text-align: left; max-width: 420px; margin-left: auto; margin-right: auto;">
            <p style="color: #fff; font-size: 0.95rem; font-weight: 600; margin-bottom: 1rem; text-align: center;">
              Activate your <span style="color: var(--primary);">2-year warranty</span> coverage for this product.</p>

            <div style="display: flex; flex-direction: column; gap: 0.8rem;">
              <p
                style="color: var(--txt-2); font-size: 0.9rem; font-weight: 600; margin-bottom: 0.5rem; text-align: center;">
                To ensure optimal performance and maintain warranty validity:</p>

              <div style="display: flex; gap: 12px; align-items: flex-start;">
                <div
                  style="width: 20px; height: 20px; background: rgba(16, 185, 129, 0.1); border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: #10b981; margin-top: 2px;">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
                    stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                  </svg>
                </div>
                <p style="color: var(--txt-2); font-size: 0.85rem; line-height: 1.4; margin: 0;"><b>Filter replacement</b>
                  is required every 90 days</p>
              </div>

              <div style="display: flex; gap: 12px; align-items: flex-start;">
                <div
                  style="width: 20px; height: 20px; background: rgba(16, 185, 129, 0.1); border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: #10b981; margin-top: 2px;">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
                    stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                  </svg>
                </div>
                <p style="color: var(--txt-2); font-size: 0.85rem; line-height: 1.4; margin: 0;"><b>RO system service</b>
                  is required every 12 months</p>
              </div>
            </div>

            <div
              style="margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid rgba(255,255,255,0.05); text-align: center;">
              <p style="color: var(--txt-3); font-size: 0.8rem; margin: 0;">Please confirm to proceed with warranty
                activation.</p>
            </div>
          </div>

          <button class="btn btn-primary" id="btn-claim" style="margin-bottom: 1.5rem;">
            Confirm & Activate
          </button>

          <div style="margin-top: 1rem;">
            <a href="dashboard.php" class="link-btn"
              style="text-decoration: none; font-size: 0.82rem; color: var(--txt-3); display: inline-flex; align-items: center; gap: 0.5rem; transition: color 0.2s;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M19 12H5M12 19l-7-7 7-7" />
              </svg>
              Back to Dashboard
            </a>
          </div>
        </div>

      <?php else: ?>
        <!-- ── Auth: Login / Register ──────────────────── -->

        <!-- Tabs -->
        <div class="tabs">
          <div class="tab <?php echo $pid ? '' : 'active'; ?>" id="tab-login">Login</div>
          <div class="tab <?php echo $pid ? 'active' : ''; ?>" id="tab-register">Register</div>
        </div>

        <!-- Login Form -->
        <form id="form-login" <?php echo $pid ? 'class="hidden"' : ''; ?>>
          <input type="hidden" name="action" value="login">

          <?php if ($pid): ?>
            <div class="form-group">
              <label>Scanned Product ID</label>
              <input type="text" name="pid" value="<?php echo htmlspecialchars($pid); ?>" readonly
                style="opacity: 0.6; cursor: not-allowed; border-color: rgba(255,255,255,0.05);">
            </div>
          <?php endif; ?>

          <div class="form-group">
            <label for="login-phone">Phone Number</label>
            <input id="login-phone" type="tel" name="phone" placeholder="" required>
          </div>
          <div class="form-group">
            <label for="login-pass">Password</label>
            <input id="login-pass" type="password" name="password" placeholder="" required>
          </div>

          <button type="submit" class="btn btn-primary">
            Sign In
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M5 12h14M12 5l7 7-7 7" />
            </svg>
          </button>
        </form>

        <!-- Register Form -->
        <form id="form-register" <?php echo $pid ? '' : 'class="hidden"'; ?>>
          <input type="hidden" name="action" value="register">

          <?php if ($pid): ?>
            <div class="form-group">
              <label>Scanned Product ID</label>
              <input type="text" name="pid" value="<?php echo htmlspecialchars($pid); ?>" readonly
                style="opacity: 0.6; cursor: not-allowed; border-color: rgba(255,255,255,0.05);">
            </div>
          <?php endif; ?>

          <div class="form-group">
            <label for="reg-phone">Phone Number</label>
            <input id="reg-phone" type="tel" name="phone" placeholder="" required>
          </div>
          <div class="form-group">
            <label for="reg-pass">Create Password</label>
            <input id="reg-pass" type="password" name="password" placeholder="" required>
          </div>

          <button type="submit" class="btn btn-primary">
            Create Account
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M5 12h14M12 5l7 7-7 7" />
            </svg>
          </button>
        </form>

      <?php endif; ?>
    </div><!-- /.glass-card -->
  </div><!-- /.container -->

  <script>
    /* ── Tab Switching ─────────────────────────── */
    const tabLogin = document.getElementById('tab-login');
    const tabRegister = document.getElementById('tab-register');
    const formLogin = document.getElementById('form-login');
    const formRegister = document.getElementById('form-register');

    function activate(showTab, hideTab, showForm, hideForm) {
      showTab.classList.add('active');
      hideTab.classList.remove('active');
      showForm.classList.remove('hidden');
      hideForm.classList.add('hidden');
    }

    if (tabLogin) {
      tabLogin.addEventListener('click', () => activate(tabLogin, tabRegister, formLogin, formRegister));
      tabRegister.addEventListener('click', () => activate(tabRegister, tabLogin, formRegister, formLogin));
    }

    /* ── Auth Handler ──────────────────────────── */
    async function handleAuth(e, form) {
      e.preventDefault();
      const btn = form.querySelector('button[type="submit"]');
      const orig = btn.innerHTML;
      btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin .7s linear infinite"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg> Processing…';
      btn.disabled = true;

      try {
        const res = await fetch('auth.php', { method: 'POST', body: new FormData(form) });
        const data = await res.json();
        if (data.success) { location.reload(); }
        else { alert(data.message); }
      } catch { alert('Network error. Please try again.'); }
      finally { btn.innerHTML = orig; btn.disabled = false; }
    }

    if (formLogin) formLogin.addEventListener('submit', e => handleAuth(e, formLogin));
    if (formRegister) formRegister.addEventListener('submit', e => handleAuth(e, formRegister));

    /* ── Warranty Claim ────────────────────────── */
    const btnClaim = document.getElementById('btn-claim');
    if (btnClaim) {
      btnClaim.addEventListener('click', async () => {
        const fd = new FormData();
        fd.append('action', 'register_warranty');
        fd.append('pid', '<?php echo addslashes($pid); ?>');
        fd.append('type', '<?php echo addslashes($type); ?>');

        btnClaim.textContent = 'Activating…';
        btnClaim.disabled = true;

        try {
          const res = await fetch('auth.php', { method: 'POST', body: fd });
          const data = await res.json();
          if (data.success) { window.location.href = 'dashboard.php'; }
          else { alert(data.message); }
        } catch { alert('An error occurred.'); }
        finally { btnClaim.textContent = 'Confirm & Activate'; btnClaim.disabled = false; }
      });
    }

    /* Spinner keyframe */
    const ss = document.createElement('style');
    ss.textContent = '@keyframes spin{to{transform:rotate(360deg)}}';
    document.head.appendChild(ss);
  </script>
</body>

</html>