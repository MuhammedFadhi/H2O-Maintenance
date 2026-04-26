<?php
require_once 'config.php';

// Redirect if already logged in as admin
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: admin.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login – H₂O System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --primary: #0ea5e9;
            --g-primary: linear-gradient(135deg, #0ea5e9, #0284c7);
        }
        .glass-card {
            padding: 3.5rem 2.5rem;
            text-align: center;
        }
        .admin-badge {
            background: rgba(14, 165, 233, 0.08);
            border: 1px solid rgba(14, 165, 233, 0.2);
            color: var(--primary);
            padding: 0.5rem 1.2rem;
            border-radius: 100px;
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 2rem;
        }
        .admin-badge::before {
            content: '';
            width: 5px; height: 5px;
            background: var(--primary);
            border-radius: 50%;
            box-shadow: 0 0 10px var(--primary);
        }
        .back-link {
            display: inline-block;
            margin-top: 2.5rem;
            color: var(--txt-3);
            font-size: 0.85rem;
            text-decoration: none;
            font-weight: 600;
            transition: 0.2s;
        }
        .back-link:hover {
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="bg-blobs">
        <div class="blob"></div>
        <div class="blob"></div>
    </div>

    <div class="container">
        <header data-animate="fade-in">
            <div style="height: 100px; display: flex; align-items: center; justify-content: center;">
                <img src="assets/img/h2o_purifier_w.png" alt="H2O Logo" style="height: 100%; width: auto; object-fit: contain;">
            </div>
            <h1>Control Center</h1>
            <p class="subtitle">Secure administrative access</p>
        </header>

        <div class="glass-card" data-animate="slide-up">
            <div class="admin-badge">Admin System</div>
            
            <form id="admin-form" style="text-align: left;">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="login_type" value="admin">
                
                <div class="form-group">
                    <label>Admin Phone Number</label>
                    <input type="tel" name="phone" required placeholder="Enter your number">
                </div>
                
                <div class="form-group">
                    <label>Master Password</label>
                    <input type="password" name="password" required placeholder="••••••••">
                </div>
                
                <button type="submit" class="btn btn-primary">
                    Unlock Dashboard
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </button>
            </form>

            <a href="index.php" class="back-link">Back to Customer Portal</a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script>
        document.getElementById('admin-form').onsubmit = async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            const originalContent = btn.innerHTML;
            btn.innerHTML = 'Authenticating...';
            btn.disabled = true;

            try {
                const res = await fetch('auth.php', { method: 'POST', body: new FormData(e.target) });
                const data = await res.json();
                if (data.success) {
                    window.location.href = 'admin.php';
                } else {
                    alert(data.message);
                }
            } catch {
                alert('Network error. Please try again.');
            } finally {
                btn.innerHTML = originalContent;
                btn.disabled = false;
            }
        };

        window.onload = () => {
            gsap.to("[data-animate='fade-in']", { opacity: 1, duration: 1 });
            gsap.to("[data-animate='slide-up']", { opacity: 1, y: 0, duration: 1, startAt: { y: 20 } });
        };
    </script>
</body>
</html>
