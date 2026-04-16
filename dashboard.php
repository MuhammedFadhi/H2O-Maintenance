<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Access Control: Admins should use admin.php
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: admin.php");
    exit;
}

$userId = $_SESSION['user_id'];
$phone = $_SESSION['phone'];

// Fetch individual warranties
$stmt = $pdo->prepare("SELECT * FROM warranties WHERE user_id = ? ORDER BY registration_date DESC");
$stmt->execute([$userId]);
$warranties = $stmt->fetchAll();

// Fetch UNIQUE product details for the "My Equipment" section
$stmt = $pdo->prepare("
    SELECT DISTINCT p.* 
    FROM products p 
    JOIN warranties w ON p.product_type = w.product_type 
    WHERE w.user_id = ?
");
$stmt->execute([$userId]);
$productSpecs = $stmt->fetchAll();

function get_milestone_stats($expiry_date, $days_total)
{
    $expiry = strtotime($expiry_date);
    $remaining = (int) ceil(($expiry - time()) / 86400);
    $remaining = max(0, $remaining);
    
    // Percent of the total period remaining
    $pct = round($remaining / $days_total, 4);
    $pct = max(0, min(1, $pct));
    return [
        'expiry' => date('Y-m-d', $expiry),
        'remaining' => $remaining,
        'total' => $days_total,
        'pct' => $pct,
        'active' => $remaining > 0
    ];
}

// Stats for header
$totalCount = count($warranties);
$activeCount = 0;
foreach ($warranties as $w) {
    if (strtotime($w['warranty_expiry']) > time()) $activeCount++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard – H₂O System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --card-bg: #0f111a;
            --accent-green: #10b981;
            --accent-amber: #f59e0b;
            --accent-red: #ef4444;
            --text-dim: #64748b;
        }

        .dashboard-container {
            max-width: 480px;
            margin: 0 auto;
            padding: 2rem 1.25rem 8rem;
        }

        /* ── Header Area ── */
        .dash-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem; }
        .logo-wrap img { height: 70px; width: auto; object-fit: contain; }
        
        .welcome-msg { margin-bottom: 2rem; }
        .welcome-msg h1 { font-size: 1.75rem; font-weight: 900; color: #fff; letter-spacing: -0.03em; }

        /* ── Vertical Warranty Card ── */
        .v-card {
            background: var(--card-bg);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 28px;
            padding: 2rem;
            margin-bottom: 2.5rem;
            box-shadow: 0 40px 80px -20px rgba(0,0,0,0.5);
            position: relative;
            overflow: hidden;
        }

        .v-card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .v-card-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: #10b981;
            letter-spacing: -0.01em;
        }
        .status-pill {
            font-size: 0.6rem;
            font-weight: 800;
            padding: 0.4rem 0.8rem;
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: var(--accent-green);
            border-radius: 100px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .status-pill.expired {
            color: var(--accent-red);
            background: rgba(239, 68, 68, 0.08);
            border-color: rgba(239, 68, 68, 0.2);
        }

        /* ── Gauge ── */
        .gauge-area {
            width: 100%;
            height: 160px;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 0.5rem;
            position: relative;
        }
        .gauge-svg { width: 100%; max-width: 320px; overflow: visible; }

        .separator {
            height: 1px;
            background: rgba(255,255,255,0.06);
            margin: 1rem 0;
        }

        /* ── Technical Info ── */
        .tech-info { display: flex; flex-direction: column; gap: 0.45rem; }
        .tech-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .tech-label {
            font-size: 0.8rem;
            font-weight: 800;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .tech-val {
            font-size: 1rem;
            font-weight: 900;
            color: #fff;fv
            text-align: right;
            letter-spacing: -0.01em;
        }

        /* ── Unit Identifier Overlay ── */
        .unit-indicator {
            text-align: center;
            margin-top: 1rem;
            margin-bottom: 2.5rem;
            background: linear-gradient(180deg, rgba(255,255,255,0.04) 0%, rgba(255,255,255,0.01) 100%);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 28px;
            padding: 2.5rem 1.5rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.05);
            position: relative;
            overflow: hidden;
        }
        .unit-indicator::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, rgba(16, 185, 129, 0.5), transparent);
        }
        .unit-badge {
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.2);
            display: inline-block;
            padding: 0.5rem 1.25rem;
            border-radius: 100px;
            font-size: 0.65rem;
            font-weight: 800;
            color: #10b981;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            margin-bottom: 1rem;
        }
        .unit-id-large {
            font-size: 1.4rem;
            font-weight: 900;
            letter-spacing: 0.08em;
            color: #fff;
            margin-bottom: 1rem;
            text-shadow: 0 10px 30px rgba(16, 185, 129, 0.15);
        }

        /* ── Specs Card ── */
        .specs-header { font-size: 0.8rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.2em; margin: 4rem 0 1.5rem; padding-left: 0.5rem; border-left: 3px solid var(--accent-green); }
        .spec-card {
            display: block;
            background: #0f111a;
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 28px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .spec-img { 
            width: 100%; 
            height: 240px; 
            background: #000; 
            border-radius: 20px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            overflow: hidden; 
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255,255,255,0.03);
        }
        .spec-img img { max-width: 90%; max-height: 90%; object-fit: contain; filter: drop-shadow(0 15px 30px rgba(0,0,0,0.6)); }
        .spec-card h4 { font-size: 1.4rem; font-weight: 850; color: #fff; margin-bottom: 1rem; letter-spacing: -0.02em; }
        .spec-card p { font-size: 1rem; color: #cbd5e1; line-height: 1.6; margin: 0; opacity: 0.95; }

        .footer-action { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 3rem; }
    </style>
</head>
<body class="bg-black text-white">
    <div class="bg-blobs">
        <div class="blob"></div>
        <div class="blob"></div>
    </div>

    <div class="dashboard-container">
        
        <!-- ── Header ── -->
        <header class="dash-header" data-animate="fade-in">
            <div class="logo-wrap">
                <img src="assets/img/h2o_purifier_w.png" alt="H2O">
            </div>
            <div style="background: rgba(255,255,255,0.05); padding: 0.5rem; border-radius: 50%; border: 1px solid rgba(255,255,255,0.1);">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
        </header>

        <section class="welcome-msg" data-animate="fade-in">
            <h1>My Equipment Status</h1>
        </section>

        <?php if (empty($warranties)): ?>
            <div style="text-align: center; opacity: 0.5; padding: 4rem 1rem;">No registered products found.</div>
        <?php else: ?>
            <?php foreach ($warranties as $idx => $item): 
                $regDate = $item['registration_date'];
                $filter = get_milestone_stats($item['filter_expiry'], 90);
                $service = get_milestone_stats($item['service_expiry'], 365);
                $warranty = get_milestone_stats($item['warranty_expiry'], 730);

                $milestones = [
                    ['title' => 'Filter Replacement', 'key' => 'FILTER', 'data' => $filter],
                    ['title' => 'Annual RO Service', 'key' => 'SERVICE', 'data' => $service]
                ];
            ?>
                <!-- Unit Identity -->
                <div class="unit-indicator" data-animate="slide-up">
                    <span class="unit-badge">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="display:inline-block; vertical-align:-2px; margin-right:4px;"><path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5"></path><path d="M2 12l10 5 10-5"></path></svg>
                        Serial Number
                    </span>
                    <div class="unit-id-large"><?php echo htmlspecialchars($item['product_id']); ?></div>
                    <div style="font-size: 0.75rem; color: #94a3b8; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;">
                        2-Year Warranty <span style="color:var(--accent-green); margin:0 4px;">•</span> Exp <?php echo date('M d, Y', strtotime($warranty['expiry'])); ?>
                    </div>
                </div>

                <?php foreach ($milestones as $mIdx => $m): ?>
                    <article class="v-card" data-animate="slide-up">
                        <div class="v-card-top">
                            <h3 class="v-card-title"><?php echo $m['title']; ?></h3>
                            <span class="status-pill <?php echo $m['data']['active'] ? '' : 'expired'; ?>">
                                <?php echo $m['key']; ?> STATUS: <?php echo $m['data']['active'] ? 'ACTIVE' : 'EXPIRED'; ?>
                            </span>
                        </div>

                        <div class="gauge-area">
                            <div class="gauge-wrap" 
                                 data-pct="<?php echo $m['data']['pct']; ?>" 
                                 data-days="<?php echo $m['data']['remaining']; ?>" 
                                 data-total="<?php echo $m['data']['total']; ?>"
                                 data-active="<?php echo $m['data']['active'] ? '1' : '0'; ?>"
                                 data-id="g-<?php echo $idx . '-' . $mIdx; ?>">
                                <svg class="gauge-svg" viewBox="0 0 200 108"></svg>
                            </div>
                        </div>

                        <div class="tech-info">
                            <div class="separator"></div>
                            <div class="tech-row">
                                <span class="tech-label">Model Type:</span>
                                <span class="tech-val"><?php echo strtoupper(htmlspecialchars($item['product_type'])); ?></span>
                            </div>
                            <div class="tech-row">
                                <span class="tech-label">Activation:</span>
                                <span class="tech-val"><?php echo date('M d, Y', strtotime($regDate)); ?></span>
                            </div>
                            <div class="tech-row">
                                <span class="tech-label"><?php echo $m['key']; ?> EXPIRY:</span>
                                <span class="tech-val"><?php echo date('M d, Y', strtotime($m['data']['expiry'])); ?></span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- ── Specs Section ── -->
        <h3 class="specs-header" data-animate="fade-in">Technical Specifications</h3>
        <?php foreach ($productSpecs as $spec): ?>
            <div class="spec-card" data-animate="reveal">
                <?php if ($spec['product_image']): ?>
                    <div class="spec-img"><img src="<?php echo htmlspecialchars($spec['product_image']); ?>" alt="Product"></div>
                <?php endif; ?>
                <h4>Model: <?php echo htmlspecialchars($spec['product_type']); ?></h4>
                <p><?php echo nl2br(htmlspecialchars($spec['description'])); ?></p>
            </div>
        <?php endforeach; ?>

        <!-- ── Footer ── -->
        <div class="footer-action">
            <a href="https://wa.me/966547989055" class="btn btn-primary" style="background:#10b981; border:none; padding:1.1rem; font-weight:800; border-radius:18px;">SYSTEM SUPPORT</a>
            <button id="btn-logout" class="btn btn-danger" style="padding:1.1rem; font-weight:800; border-radius:18px;">SIGN OUT</button>
        </div>

    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script>
        /* ── Arc Gauge Renderer ── */
        document.querySelectorAll('.gauge-wrap').forEach(wrap => {
            const id = wrap.dataset.id;
            const pct = parseFloat(wrap.dataset.pct);
            const days = parseInt(wrap.dataset.days);
            const active = wrap.dataset.active === '1';
            const svg = wrap.querySelector('.gauge-svg');

            const cx = 100, cy = 95, r = 80;
            const sx = cx - r, ex = cx + r;
            const midX = cx, midY = cy - r;
            const fullPath = `M ${sx} ${sy=cy} A ${r} ${r} 0 0 1 ${midX} ${midY} A ${r} ${r} 0 0 1 ${ex} ${ey=cy}`;

            // Colors
            let color = active ? (days > 45 ? '#10b981' : (days > 15 ? '#f59e0b' : '#ef4444')) : '#ef4444';

            // Calculate tip tip position
            const angle = Math.PI * pct;
            const tx = (cx - r * Math.cos(angle)).toFixed(2);
            const ty = (cy - r * Math.sin(angle)).toFixed(2);

            svg.innerHTML = `
                <defs>
                    <filter id="glow-${id}" x="-50%" y="-50%" width="200%" height="200%">
                        <feGaussianBlur stdDeviation="6" result="blur"/>
                        <feComposite in="SourceGraphic" in2="blur" operator="over"/>
                    </filter>
                    <linearGradient id="grad-${id}" x1="0%" y1="100%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="${color}" stop-opacity="0.3"/>
                        <stop offset="100%" stop-color="${color}"/>
                    </linearGradient>
                </defs>

                <!-- Track Background (Segmented Look) -->
                <path d="${fullPath}" stroke="#1e293b" stroke-width="32" fill="none" stroke-linecap="round" opacity="0.4"/>
                <path d="${fullPath}" stroke="#0f172a" stroke-width="32" fill="none" stroke-dasharray="2, 6" opacity="0.6"/>

                <!-- Glowing Progress -->
                <g filter="url(#glow-${id})">
                    <path class="arc-progress" d="${fullPath}" stroke="url(#grad-${id})" stroke-width="12" fill="none" stroke-linecap="round"/>
                </g>

                <!-- Tip Dot -->
                <g class="arc-tip" opacity="0">
                    <circle cx="${tx}" cy="${ty}" r="8" fill="${color}" opacity="0.3"/>
                    <circle cx="${tx}" cy="${ty}" r="4" fill="#fff"/>
                </g>

                <!-- Value Display -->
                <text x="${cx}" y="${cy - 28}" text-anchor="middle" fill="#fff" font-size="36" font-weight="100" letter-spacing="-3" id="count-${id}">0</text>
                <text x="${cx}" y="${cy - 8}" text-anchor="middle" fill="#64748b" font-size="10" font-weight="800" letter-spacing="2">DAYS LEFT</text>
            `;

            // Animate
            const progress = svg.querySelector('.arc-progress');
            const tip = svg.querySelector('.arc-tip');
            const count = document.getElementById(`count-${id}`);
            
            const len = progress.getTotalLength();
            progress.style.strokeDasharray = `${len} ${len}`;
            progress.style.strokeDashoffset = len;

            gsap.to(progress, {
                strokeDashoffset: len - (len * pct),
                duration: 2,
                ease: "power3.out",
                delay: 0.2
            });

            gsap.to(tip, { opacity: 1, duration: 0.5, delay: 1.5 });

            let counter = { val: 0 };
            gsap.to(counter, {
                val: days,
                duration: 1.8,
                ease: "power3.out",
                delay: 0.2,
                onUpdate: () => { count.textContent = Math.floor(counter.val); }
            });
        });

        // Logout
        document.getElementById('btn-logout').onclick = async () => {
            const fd = new FormData(); fd.append('action', 'logout');
            await fetch('auth.php', { method: 'POST', body: fd });
            window.location.href = 'index.php';
        };

        // Entrance
        window.onload = () => {
            gsap.to('[data-animate="fade-in"]', { opacity: 1, duration: 1, stagger: 0.2 });
            gsap.to('[data-animate="slide-up"]', { opacity: 1, y: 0, duration: 1, stagger: 0.15, startAt: { y: 40 } });
            gsap.to('[data-animate="reveal"]', { opacity: 1, y: 0, duration: 1, startAt: { y: 20 }, scrollTrigger: { trigger: '[data-animate="reveal"]' } });
        };
    </script>
</body>
</html>