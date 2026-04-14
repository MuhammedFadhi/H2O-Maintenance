<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
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

function is_active($expiry)
{
    return strtotime($expiry) > time();
}
function days_remaining($expiry)
{
    return min(90, max(0, (int) ceil((strtotime($expiry) - time()) / 86400)));
}
function days_total($reg, $expiry)
{
    return 90;
} // Fixed 3-month warranty = 90 days

// Calculate stats for tiles
$totalCount = count($warranties);
$activeCount = 0;
$expiringSoon = 0;
foreach ($warranties as $w) {
    if (is_active($w['expiry_date'])) {
        $activeCount++;
        if (days_remaining($w['expiry_date']) < 30)
            $expiringSoon++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no">
    <title>Dashboard – H₂O Warranty</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* ── Warranty Card ─────────────────────────── */
        .warranty-card {
            display: grid;
            grid-template-columns: 160px 1fr;
            gap: 1.1rem;
            align-items: center;
            padding: 1.25rem 1.1rem;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.07);
            margin-bottom: .875rem;
            transition: background .2s, transform .2s, box-shadow .2s;
        }

        .warranty-card:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.11);
            transform: translateY(-1px);
            box-shadow: 0 10px 36px rgba(0, 0, 0, .22);
        }

        /* ── Gauge ─────────────────────────────────── */
        .gauge-wrap {
            text-align: center;
        }

        .gauge-svg {
            display: block;
            margin: 0 auto;
            width: 100%;
            max-width: 158px;
            overflow: visible;
        }

        .gauge-sub {
            font-size: .62rem;
            font-weight: 700;
            color: #344155;
            letter-spacing: .09em;
            text-transform: uppercase;
            margin-top: 2px;
        }

        /* ── Card Info ─────────────────────────────── */
        .card-info {
            min-width: 0;
        }

        .prod-id {
            font-size: 1rem;
            font-weight: 800;
            color: #f1f5f9;
            letter-spacing: .03em;
            margin-bottom: .65rem;
            padding-bottom: .55rem;
            border-bottom: 1px solid rgba(255, 255, 255, .06);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .info-rows {
            display: flex;
            flex-direction: column;
            gap: .35rem;
            margin-bottom: .75rem;
        }

        .info-row {
            display: flex;
            align-items: center;
            gap: .55rem;
        }

        .info-icon {
            width: 26px;
            height: 26px;
            flex-shrink: 0;
            background: rgba(255, 255, 255, .05);
            border: 1px solid rgba(255, 255, 255, .06);
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .info-body {
            display: flex;
            flex-direction: column;
            gap: 1px;
        }

        .info-label {
            font-size: .62rem;
            font-weight: 700;
            color: #3d4f65;
            text-transform: uppercase;
            letter-spacing: .06em;
            line-height: 1;
        }

        .info-val {
            font-size: .82rem;
            font-weight: 500;
            color: #c8d5e8;
            line-height: 1.2;
        }

        .prod-desc {
            font-size: 0.78rem;
            line-height: 1.5;
            color: #64748b;
            margin-top: 1rem;
            padding-top: 0.8rem;
            border-top: 1px solid rgba(255, 255, 255, 0.04);
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* ── Product Photo ───────────────────────── */
        .prod-photo-wrap {
            width: 100%;
            height: 140px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.03);
        }

        .prod-photo-wrap img {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
            filter: drop-shadow(0 10px 20px rgba(0, 0, 0, 0.4));
        }

        /* ── Product Specs Card ──────────────────── */
        .section-title {
            font-size: 0.9rem;
            font-weight: 800;
            color: #3d4f6a;
            text-transform: uppercase;
            letter-spacing: .15em;
            margin: 2.5rem 0 1.2rem;
            padding-left: 0.5rem;
            border-left: 3px solid var(--accent);
        }

        .spec-card {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 1.5rem;
            padding: 1.5rem;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            margin-bottom: 1rem;
        }

        .spec-photo {
            width: 140px;
            height: 140px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .spec-photo img {
            max-width: 85%;
            max-height: 85%;
            object-fit: contain;
            filter: drop-shadow(0 8px 16px rgba(0, 0, 0, 0.5));
        }

        .spec-info h4 {
            color: #f1f5f9;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        .spec-desc {
            color: #64748b;
            font-size: 0.85rem;
            line-height: 1.6;
        }

        /* ── Responsive ──────────────────────────── */
        @media (max-width: 580px) {
            .spec-card {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .spec-photo {
                width: 100%;
                height: 180px;
                margin: 0 auto;
            }

            .warranty-card {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .info-row {
                justify-content: center;
            }
        }

        /* ── Footer Actions ──────────────────────── */
        .footer-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
        }

        .footer-actions .btn {
            flex: 1;
            justify-content: center;
        }

        .btn-support {
            background: rgba(34, 211, 238, 0.08);
            color: #22d3ee;
            border: 1px solid rgba(34, 211, 238, 0.15);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.82rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-support:hover {
            background: rgba(34, 211, 238, 0.12);
            border-color: rgba(34, 211, 238, 0.4);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(34, 211, 238, 0.15);
        }

        .btn-support svg {
            transition: transform 0.25s ease;
        }

        .btn-support:hover svg {
            transform: scale(1.1) rotate(-5deg);
        }
    </style>
</head>

<body>

    <div class="bg-blobs">
        <div class="blob"></div>
        <div class="blob"></div>
    </div>

    <div class="container">

        <!-- ── Top Header ── -->
        <div class="dash-header" data-animate="fade-down">
            <div style="height: 48px; flex: 1; display: flex; align-items: center;">
                <img src="assets/img/h2o_purifier_w.png" alt="H2O Logo"
                    style="height: 70px; width: auto; object-fit: contain;">
            </div>
            <div class="profile-mock">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                    <circle cx="12" cy="7" r="4" />
                </svg>
            </div>
        </div>

        <!-- ── Dashboard Title ── -->
        <div style="margin-bottom: 2rem;" data-animate="fade-down">
            <h2 style="font-size: 1.6rem; font-weight: 900; color: #fff; line-height: 1.2; letter-spacing: -0.02em;">
                My Equipment Status
            </h2>
        </div>



        <!-- ── Warranty Cards ── -->
        <?php if (empty($warranties)): ?>
            <div class="empty-state">
                <p>No products registered yet.</p>
            </div>
        <?php else: ?>
            <div style="margin: 2rem 0 1rem; padding-left: 0.5rem; color:#fff; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em;"
                data-animate="fade-in">
                Registered Products
            </div>
            <?php foreach ($warranties as $idx => $item):
                $active = is_active($item['expiry_date']);
                $dLeft = days_remaining($item['expiry_date']);
                $dTotal = 90;
                $pct = round($dLeft / $dTotal, 4);
                ?>
                <div class="warranty-card" data-animate="slide-up">
                    <div class="gauge-wrap" data-idx="<?php echo $idx; ?>" data-pct="<?php echo $pct; ?>"
                        data-days="<?php echo $dLeft; ?>" data-active="<?php echo $active ? '1' : '0'; ?>">
                        <svg class="gauge-svg" viewBox="0 0 200 108" xmlns="http://www.w3.org/2000/svg"></svg>
                    </div>
                    <div class="card-info">
                        <div class="prod-id" style="border: none; margin-bottom: 0.5rem; font-size: 1.1rem;">
                            <?php echo htmlspecialchars($item['product_id']); ?></div>

                        <div class="info-rows"
                            style="display: flex; flex-direction: column; gap: 0.6rem; margin: 1.5rem auto; width: fit-content; text-align: left;">
                            <!-- Type -->
                            <div class="info-row"
                                style="display: grid; grid-template-columns: 55px 1fr; font-size: 0.75rem; align-items: baseline;">
                                <span
                                    style="font-weight: 800; color: #fff; text-transform: uppercase; letter-spacing: 0.05em;">Type:</span>
                                <span
                                    style="color: #cbd5e1; font-weight: 500;"><?php echo htmlspecialchars($item['product_type']); ?></span>
                            </div>
                            <!-- Registered -->
                            <div class="info-row"
                                style="display: grid; grid-template-columns: 55px 1fr; font-size: 0.75rem; align-items: baseline;">
                                <span
                                    style="font-weight: 800; color: #fff; text-transform: uppercase; letter-spacing: 0.05em;">Reg:</span>
                                <span
                                    style="color: #cbd5e1; font-weight: 500;"><?php echo date('M d, Y', strtotime($item['registration_date'])); ?></span>
                            </div>
                            <!-- Expires -->
                            <div class="info-row"
                                style="display: grid; grid-template-columns: 55px 1fr; font-size: 0.75rem; align-items: baseline;">
                                <span
                                    style="font-weight: 800; color: #fff; text-transform: uppercase; letter-spacing: 0.05em;">Exp:</span>
                                <span
                                    style="color: #cbd5e1; font-weight: 500;"><?php echo date('M d, Y', strtotime($item['expiry_date'])); ?></span>
                            </div>
                        </div>

                        <span class="pill <?php echo $active ? 'pill-active' : 'pill-expired'; ?>" style="font-size: 0.65rem;">
                            <?php echo $active ? 'ACTIVE' : 'EXPIRED'; ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- ── Light Mode Section ── -->
        <div class="">
            <div class="light-title"
                style="color: #ffffffff; font-size: 1.1rem; font-weight: 900; margin-bottom: 1.5rem;">
                Technical Specifications
            </div>

            <?php foreach ($productSpecs as $spec): ?>
                <div class="light-card" data-animate="reveal">
                    <?php if ($spec['product_image']): ?>
                        <div class="prod-photo-wrap" style="background: #000000ff; margin-bottom: 1.5rem; height: 180px;">
                            <img src="<?php echo htmlspecialchars($spec['product_image']); ?>" alt="Product">
                        </div>
                    <?php endif; ?>
                    <h4>Model: <?php echo htmlspecialchars($spec['product_type']); ?></h4>
                    <p><?php echo nl2br(htmlspecialchars($spec['description'])); ?></p>
                </div>
            <?php endforeach; ?>

            <!-- Footer Actions -->
            <div class="footer-actions" style="margin-top: 3rem; display: flex; gap: 1rem;">
                <a href="https://wa.me/966547989055" class="btn btn-support"
                    style="flex: 1; background: #22d3ee; color: #fff; border: none; padding: 1.1rem; border-radius: 14px; font-weight: 800;">
                    SYSTEM SUPPORT
                </a>
                <button id="btn-logout" class="btn btn-danger"
                    style="flex: 1; border-radius: 14px; padding: 1.1rem; font-weight: 800;">
                    SIGN OUT
                </button>
            </div>
        </div>

    </div><!-- /.container -->



    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
    <script>
        /* ════════════════════════════════════════════
           ARC GAUGE RENDERER
           Geometry:  cx=100  cy=95  r=80
           Arc from (20,95) → top (100,15) → (180,95)
           ViewBox: 0 0 200 108
        ════════════════════════════════════════════ */
        document.querySelectorAll('.gauge-wrap').forEach(wrap => {
            const idx = parseInt(wrap.dataset.idx);
            const pct = parseFloat(wrap.dataset.pct);
            const days = parseInt(wrap.dataset.days);
            const total = parseInt(wrap.dataset.total);
            const active = wrap.dataset.active === '1';
            const svg = wrap.querySelector('.gauge-svg');

            const cx = 100, cy = 95, r = 80;
            const sx = cx - r;   // 20
            const ex = cx + r;   // 180
            const sy = cy, ey = cy;

            /* ── Status Colors ── */
            let mainColor, glowColor;
            if (!active) {
                mainColor = '#ef4444'; // Solid Red
                glowColor = 'rgba(239, 68, 68, 0.4)';
            } else if (days > 54) {
                mainColor = '#10b981'; // Emerald Green
                glowColor = 'rgba(16, 185, 129, 0.4)';
            } else if (days >= 27) {
                mainColor = '#f59e0b'; // Amber Orange
                glowColor = 'rgba(245, 158, 11, 0.4)';
            } else {
                mainColor = '#ef4444'; // Bright Red
                glowColor = 'rgba(239, 68, 68, 0.4)';
            }

            /* ── Paths ─────────────────────────────── */
            const midX = cx, midY = cy - r;
            const fullPath = `M ${sx} ${sy} A ${r} ${r} 0 0 1 ${midX} ${midY} A ${r} ${r} 0 0 1 ${ex} ${ey}`;

            /* ── Tip position ─────────────────────── */
            const a = Math.PI * pct;
            const tx = (cx - r * Math.cos(a)).toFixed(2);
            const ty = (cy - r * Math.sin(a)).toFixed(2);

            /* ── Render ───────────────────────────── */
            svg.innerHTML = `
      <defs>
        <filter id="neon${idx}" x="-50%" y="-50%" width="200%" height="200%">
          <feGaussianBlur stdDeviation="5" result="blur"/>
          <feComposite in="SourceGraphic" in2="blur" operator="over"/>
        </filter>
        <linearGradient id="lg${idx}" x1="0%" y1="100%" x2="100%" y2="0%">
          <stop offset="0%"   stop-color="${mainColor}" stop-opacity=".4"/>
          <stop offset="100%" stop-color="${mainColor}"/>
        </linearGradient>
      </defs>

      <!-- 1. Background Track (Heavy Base) -->
      <path d="${fullPath}" stroke="#0f172a" stroke-width="32" fill="none" stroke-linecap="round"/>
      
      <!-- 2. Segmented Notches (Instrumentation) -->
      <path d="${fullPath}" stroke="#1e293b" stroke-width="32" fill="none" stroke-linecap="butt" 
            stroke-dasharray="2, 6.2"/>

      <!-- 3. Nested Progress Arc (Thin Neon) -->
      <g filter="url(#neon${idx})">
        <path class="progress-arc" d="${fullPath}"
              stroke="url(#lg${idx})" stroke-width="12"
              fill="none" stroke-linecap="round" opacity="0.9"/>
      </g>
      
      <!-- 4. End Point Halo -->
      ${pct > 0.05 ? `
        <g class="tip-dot">
          <circle cx="${tx}" cy="${ty}" r="10" fill="${mainColor}" opacity=".2"/>
          <circle cx="${tx}" cy="${ty}" r="4" fill="white"/>
        </g>
      ` : ''}

      <!-- 5. Center Content -->
      <text class="count-text" x="${cx}" y="${cy - 35}"
            text-anchor="middle" dominant-baseline="middle"
            fill="#fff" font-size="52" font-weight="900"
            font-family="Inter, system-ui" letter-spacing="-3">0</text>

      <text x="${cx}" y="${cy - 5}"
            text-anchor="middle" dominant-baseline="middle"
            fill="#64748b" font-size="10" font-weight="800"
            style="text-transform:uppercase; letter-spacing:2px;">DAYS LEFT</text>
    `;

            /* ── Animation ────────────────────────── */
            const arcEl = svg.querySelector('.progress-arc');
            const tipEl = svg.querySelector('.tip-dot');
            const numEl = svg.querySelector('.count-text');

            if (arcEl) {
                const totalLen = arcEl.getTotalLength();
                const targetLen = totalLen * pct;

                arcEl.style.strokeDasharray = `${totalLen} ${totalLen}`;
                arcEl.style.strokeDashoffset = totalLen;

                if (tipEl) tipEl.style.opacity = '0';

                requestAnimationFrame(() => {
                    arcEl.style.transition = 'stroke-dashoffset 1.8s cubic-bezier(0.19, 1, 0.22, 1)';
                    arcEl.style.strokeDashoffset = totalLen - targetLen;

                    // Numbers counter
                    if (numEl && days > 0) {
                        let start = 0;
                        const duration = 1800;
                        const startTime = performance.now();

                        function updateCount(currentTime) {
                            const elapsed = currentTime - startTime;
                            const progress = Math.min(elapsed / duration, 1);
                            const easeProgress = 1 - Math.pow(1 - progress, 4);
                            numEl.textContent = Math.floor(easeProgress * days);
                            if (progress < 1) requestAnimationFrame(updateCount);
                            else numEl.textContent = days;
                        }
                        requestAnimationFrame(updateCount);
                    } else if (numEl) {
                        numEl.textContent = days;
                    }

                    if (tipEl) {
                        tipEl.style.transition = 'opacity 0.4s ease 1.2s';
                        tipEl.style.opacity = '1';
                    }
                });
            }
        });

        // Handle Logout
        document.getElementById('btn-logout')?.addEventListener('click', async (e) => {
            e.preventDefault();
            const fd = new FormData();
            fd.append('action', 'logout');
            try { await fetch('auth.php', { method: 'POST', body: fd }); } catch { }
            window.location.href = 'index.php';
        });

        /* ── GSAP Animations ─────────────────────── */
        gsap.registerPlugin(ScrollTrigger);

        // Entrance Timeline
        const tl = gsap.timeline({ defaults: { ease: "expo.out", duration: 1.2 } });

        tl.to('[data-animate="fade-down"]', { opacity: 1, y: 0, startAt: { y: -30 } })
            .to('[data-animate="slide-up"]', {
                opacity: 1,
                y: 0,
                startAt: { y: 40 },
                stagger: 0.15
            }, "-=0.8")
            .to('[data-animate="fade-in"]', { opacity: 1 }, "-=0.5");

        // Staggered Rows inside cards
        gsap.utils.toArray('.info-rows').forEach(container => {
            gsap.to(container.querySelectorAll('[data-animate="fade-row"]'), {
                scrollTrigger: {
                    trigger: container,
                    start: "top 95%",
                    toggleActions: "play none none none"
                },
                opacity: 1,
                x: 0,
                startAt: { x: -20, opacity: 0 },
                stagger: 0.1,
                duration: 0.8,
                ease: "power2.out"
            });
        });

        // Scroll Reveals
        gsap.utils.toArray('[data-animate="reveal"]').forEach(card => {
            gsap.to(card, {
                scrollTrigger: {
                    trigger: card,
                    start: "top 85%",
                    toggleActions: "play none none none"
                },
                opacity: 1,
                y: 0,
                startAt: { y: 30 },
                duration: 1
            });
        });

        // Hover Effects Enhancements
        document.querySelectorAll('.warranty-card, .spec-card').forEach(card => {
            card.addEventListener('mouseenter', () => {
                gsap.to(card, { scale: 1.02, duration: 0.4, ease: "power2.out" });
            });
            card.addEventListener('mouseleave', () => {
                gsap.to(card, { scale: 1, duration: 0.4, ease: "power2.out" });
            });
        });
    </script>
</body>

</html>