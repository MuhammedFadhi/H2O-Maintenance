<?php
require_once 'config.php';

// Security: Check admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Fetch Stats for the top cards
try {
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
    $totalRegistrations = $pdo->query("SELECT COUNT(*) FROM warranties")->fetchColumn();
    
    // Count Maintenance Required (Filter < 7 days or Service < 14 days)
    $stmtMaint = $pdo->query("SELECT COUNT(*) FROM warranties WHERE filter_expiry < TIMESTAMPADD(DAY, 7, NOW()) OR service_expiry < TIMESTAMPADD(DAY, 14, NOW())");
    $maintCount = $stmtMaint->fetchColumn();

    // Fetch all records with user phone
    $stmt = $pdo->query("SELECT w.*, u.phone FROM warranties w JOIN users u ON w.user_id = u.id ORDER BY w.registration_date DESC");
    $allWarranties = $stmt->fetchAll();

    // Fetch products for Model Manager
    $stmtProd = $pdo->query("SELECT * FROM products ORDER BY id ASC");
    $allProducts = $stmtProd->fetchAll();

} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>H₂O System Admin | Control Center</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --admin-accent: #06b6d4;
            --admin-surface: #0a0c16;
            --admin-border: rgba(255, 255, 255, 0.08);
        }

        .admin-page {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1.5rem 8rem;
        }

        /* ── Header Area ── */
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 3.5rem;
        }
        .admin-title h1 { font-size: 2rem; letter-spacing: -0.02em; margin-bottom: 0.5rem; }
        .admin-title p { color: #64748b; font-size: 0.95rem; font-weight: 500; }

        /* ── Controls Bar ── */
        .controls-bar {
            background: var(--admin-surface);
            border: 1px solid var(--admin-border);
            border-radius: var(--r-xl);
            padding: 1.25rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            align-items: center;
            margin-bottom: 2.5rem;
            box-shadow: 0 30px 60px -15px rgba(0,0,0,0.4);
        }
        .search-field { flex: 1; min-width: 250px; position: relative; }
        .search-field input { padding-left: 2.75rem; background: rgba(255,255,255,0.02); border-color: rgba(255,255,255,0.06); }
        .search-field::before { content: "🔍"; position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); font-size: 0.85rem; opacity: 0.5; }

        .filter-group { display: flex; gap: 0.5rem; background: rgba(255,255,255,0.02); padding: 4px; border-radius: 14px; border: 1px solid rgba(255,255,255,0.04); }
        .filter-btn { background: transparent; border: none; color: #64748b; padding: 0.5rem 1.25rem; border-radius: 10px; font-size: 0.75rem; font-weight: 700; cursor: pointer; transition: 0.2s; text-transform: uppercase; letter-spacing: 0.05em; }
        .filter-btn.active { background: #111827; color: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.3); }

        /* ── Stats Layout ── */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 3.5rem; }
        .stat-tile { background: linear-gradient(165deg, #10121d 0%, #0a0c16 100%); border: 1px solid var(--admin-border); border-radius: var(--r-xl); padding: 2rem; position: relative; overflow: hidden; }
        .stat-tile h3 { font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.15em; margin-bottom: 1rem; }
        .stat-tile .num { font-size: 2.5rem; font-weight: 800; color: #fff; line-height: 1; }
        .stat-tile .trend { font-size: 0.7rem; color: var(--primary); margin-top: 0.75rem; font-weight: 700; }
        .stat-tile.alert .num { color: var(--danger); text-shadow: 0 0 20px rgba(244,63,94,0.3); }

        /* ── Data Table ── */
        .data-table-wrap { background: var(--admin-surface); border: 1px solid var(--admin-border); border-radius: var(--r-xl); overflow: hidden; box-shadow: 0 50px 100px -20px rgba(0,0,0,0.6); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { padding: 1.25rem 1.5rem; font-size: 0.65rem; color: #475569; text-transform: uppercase; letter-spacing: 0.15em; border-bottom: 1px solid var(--admin-border); }
        td { padding: 1.25rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.02); vertical-align: middle; }

        .row-phone { font-weight: 700; color: #fff; font-size: 0.95rem; cursor: pointer; transition: 0.2s; }
        .row-phone:hover { color: var(--primary); text-decoration: underline; }
        .row-id { font-family: monospace; color: var(--primary); font-size: 0.85rem; background: rgba(16,185,129,0.05); padding: 2px 6px; border-radius: 4px; }
        
        .date-maint { font-size: 0.8rem; color: #94a3b8; }
        .date-maint.due { color: var(--danger); font-weight: 700; }
        
        .action-btns { display: flex; gap: 0.5rem; }
        .btn-action { width: 32px; height: 32px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.03); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
        .btn-action:hover { background: var(--primary); border-color: var(--primary); color: #000; transform: translateY(-2px); }

        /* ── Model Manager ── */
        .model-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; margin-top: 2rem; }
        .model-card { background: rgba(255,255,255,0.02); border: 1px solid var(--admin-border); border-radius: var(--r-lg); padding: 1.5rem; display: flex; gap: 1rem; align-items: center; position: relative; transition: 0.3s; }
        .model-card:hover { transform: translateY(-5px); background: rgba(255,255,255,0.04); }
        .model-card img { width: 60px; height: 60px; border-radius: 12px; background: #000; border: 1px solid rgba(255,255,255,0.05); object-fit: contain; }
        .model-card h4 { font-size: 1rem; color: #fff; margin-bottom: 0.25rem; }
        .model-card p { font-size: 0.7rem; color: #64748b; }
        .model-actions { position: absolute; top: 0.5rem; right: 0.5rem; display: flex; gap: 0.25rem; opacity: 0; transition: 0.3s; }
        .model-card:hover .model-actions { opacity: 1; }
        .btn-model-mini { width: 24px; height: 24px; border-radius: 6px; background: #000; border: 1px solid rgba(255,255,255,0.1); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; }
        .btn-model-mini:hover { background: var(--primary); border-color: var(--primary); color: #000; }
        .btn-model-mini.danger:hover { background: var(--danger); border-color: var(--danger); color: #fff; }

        .add-model-card { border: 2px dashed rgba(16, 185, 129, 0.4); cursor: pointer; text-align: center; display: flex; flex-direction: column; justify-content: center; opacity: 1; color: #10b981; transition: 0.3s; }
        .add-model-card:hover { border-color: #10b981; background: rgba(16, 185, 129, 0.05); box-shadow: 0 0 20px rgba(16, 185, 129, 0.1); }
        .add-model-card .plus-icon { font-size: 1.8rem; margin-bottom: 0.25rem; }
        .add-model-card .label { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.15em; }

        /* ── Modals ── */
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.88); backdrop-filter: blur(16px); z-index: 2000; display: none; align-items: center; justify-content: center; opacity: 0; padding: 1rem; }
        .modal-content { background: #0d0f1c; border: 1px solid rgba(255,255,255,0.07); width: 100%; max-width: 500px; border-radius: 24px; padding: 2.5rem; box-shadow: 0 60px 120px rgba(0,0,0,1); max-height: 92vh; overflow-y: auto; }
        .modal-content.large { max-width: 780px; padding: 0; overflow: hidden; }

        /* ── Client Modal Redesign ── */
        .client-modal-hero {
            background: linear-gradient(135deg, #05080f 0%, #0d1629 50%, #0a1020 100%);
            padding: 2rem 2rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            position: relative;
            overflow: hidden;
        }
        .client-modal-hero::before {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 220px; height: 220px;
            background: radial-gradient(circle, rgba(16,185,129,0.12) 0%, transparent 70%);
            border-radius: 50%;
        }
        .client-modal-hero::after {
            content: '';
            position: absolute;
            bottom: -40px; left: 20px;
            width: 150px; height: 150px;
            background: radial-gradient(circle, rgba(6,182,212,0.07) 0%, transparent 70%);
            border-radius: 50%;
        }
        .client-hero-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; position: relative; z-index: 1; }
        .client-hero-identity { display: flex; align-items: center; gap: 1rem; }
        .client-avatar {
            width: 52px; height: 52px;
            background: linear-gradient(135deg, #10b981, #06b6d4);
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
            box-shadow: 0 8px 24px rgba(16,185,129,0.3);
            flex-shrink: 0;
        }
        .client-phone-num {
            font-size: 1.5rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.02em;
            line-height: 1.1;
        }
        .client-label {
            font-size: 0.72rem;
            color: #475569;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-top: 2px;
        }
        .btn-close-modal {
            width: 36px; height: 36px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 10px;
            color: #64748b;
            font-size: 1.1rem;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: 0.2s;
            flex-shrink: 0;
        }
        .btn-close-modal:hover { background: rgba(244,63,94,0.15); border-color: rgba(244,63,94,0.4); color: #f43f5e; }

        /* Unit Tabs */
        .unit-selector { display: flex; gap: 0.5rem; padding: 1.25rem 2rem 0; overflow-x: auto; scrollbar-width: none; }
        .unit-selector::-webkit-scrollbar { display: none; }
        .unit-tab {
            white-space: nowrap;
            padding: 0.55rem 1.1rem;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.72rem;
            font-weight: 700;
            color: #64748b;
            transition: 0.2s;
            letter-spacing: 0.05em;
        }
        .unit-tab.active {
            background: rgba(16,185,129,0.12);
            color: #10b981;
            border-color: rgba(16,185,129,0.4);
        }

        /* Profile Unit Card */
        .profile-unit-card { display: none; padding: 1.5rem 2rem 2rem; }
        .profile-unit-card.active { display: block; }

        /* Unit Info Bar */
        .unit-info-bar {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 16px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }
        .unit-model-img {
            width: 52px; height: 52px;
            background: rgba(0,0,0,0.4);
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.06);
            display: flex; align-items: center; justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }
        .unit-model-img img { width: 44px; height: 44px; object-fit: contain; }
        .unit-model-name {
            font-size: 1rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: 0.03em;
        }
        .unit-model-sub {
            font-size: 0.72rem;
            color: #475569;
            margin-top: 3px;
        }
        .unit-model-badge {
            margin-left: auto;
            background: rgba(16,185,129,0.1);
            color: #10b981;
            border: 1px solid rgba(16,185,129,0.25);
            border-radius: 8px;
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            padding: 4px 10px;
        }

        /* Job Order Box */
        .job-order-box {
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 1.75rem;
        }
        .job-order-label {
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            color: #10b981;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .job-order-label::before {
            content: '';
            width: 3px; height: 12px;
            background: #10b981;
            border-radius: 4px;
            display: inline-block;
        }
        .job-order-grid {
            display: grid;
            grid-template-columns: 1fr 2fr auto;
            gap: 0.75rem;
            align-items: center;
        }
        .job-order-grid select, .job-order-grid input {
            background: rgba(0,0,0,0.5) !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            border-radius: 10px !important;
            color: #fff !important;
            padding: 0.65rem 0.85rem !important;
            font-size: 0.82rem !important;
            transition: border-color 0.2s !important;
            outline: none !important;
        }
        .job-order-grid select:focus, .job-order-grid input:focus {
            border-color: rgba(16,185,129,0.5) !important;
            box-shadow: 0 0 0 3px rgba(16,185,129,0.08) !important;
        }
        .btn-save-job {
            background: linear-gradient(135deg, #10b981, #06b6d4);
            border: none;
            border-radius: 10px;
            color: #000;
            font-weight: 800;
            font-size: 0.8rem;
            padding: 0.65rem 1.1rem;
            cursor: pointer;
            white-space: nowrap;
            letter-spacing: 0.03em;
            transition: 0.2s;
            box-shadow: 0 4px 16px rgba(16,185,129,0.2);
        }
        .btn-save-job:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(16,185,129,0.35); }

        /* Timeline */
        .timeline-section-label {
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            color: #334155;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .timeline-section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255,255,255,0.04);
        }
        .timeline { position: relative; padding-left: 2.25rem; }
        .timeline::before { content: ''; position: absolute; left: 10px; top: 0; bottom: 0; width: 2px; background: linear-gradient(to bottom, rgba(16,185,129,0.3), rgba(255,255,255,0.03)); border-radius: 2px; }
        .timeline-item {
            position: relative;
            margin-bottom: 1.25rem;
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.04);
            border-radius: 14px;
            padding: 1rem 1.1rem;
            transition: 0.2s;
        }
        .timeline-item:hover { background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.08); }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -2.25rem;
            top: 1rem;
            width: 12px; height: 12px;
            background: #1e293b;
            border: 2px solid rgba(255,255,255,0.12);
            border-radius: 50%;
        }
        .timeline-item.activation::before { background: #10b981; border-color: #10b981; box-shadow: 0 0 8px rgba(16,185,129,0.5); }
        .timeline-item.filter::before { background: #06b6d4; border-color: #06b6d4; box-shadow: 0 0 8px rgba(6,182,212,0.4); }
        .timeline-item.service::before { background: #8b5cf6; border-color: #8b5cf6; box-shadow: 0 0 8px rgba(139,92,246,0.4); }
        .timeline-item.other::before { background: #f59e0b; border-color: #f59e0b; box-shadow: 0 0 8px rgba(245,158,11,0.4); }
        .timeline-item-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.4rem; }
        .timeline-item h5 { font-size: 0.8rem; color: #e2e8f0; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; }
        .timeline-item .date { font-size: 0.68rem; color: #475569; font-weight: 500; }
        .timeline-item .notes { font-size: 0.8rem; color: #94a3b8; line-height: 1.55; background: rgba(0,0,0,0.25); padding: 0.65rem 0.85rem; border-radius: 8px; margin-top: 0.6rem; border-left: 2px solid rgba(255,255,255,0.06); }

        @media (max-width: 800px) { .stats-grid { grid-template-columns: 1fr; } .controls-bar { flex-direction: column; align-items: stretch; } .admin-header { flex-direction: column; gap: 2rem; } }
    </style>
</head>
<body class="bg-black text-white">
    <div class="bg-blobs">
        <div class="blob"></div>
        <div class="blob"></div>
    </div>

    <div class="admin-page">
        <!-- ── Top Nav Area ── -->
        <div class="admin-header" data-animate="fade-in">
            <div class="admin-title">
                <h1>Control Center</h1>
                <p>H₂O System Management Intelligence</p>
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <a href="dashboard.php" class="btn btn-support btn-sm">User Dashboard</a>
                <button onclick="handleLogout()" class="btn btn-danger btn-sm">Sign Out</button>
            </div>
        </div>

        <!-- ── Core Stats ── -->
        <div class="stats-grid">
            <div class="stat-tile" data-animate="slide-up">
                <h3>System Users</h3>
                <div class="num"><?php echo $totalUsers; ?></div>
                <div class="trend">Total active consumer accounts</div>
            </div>
            <div class="stat-tile" data-animate="slide-up">
                <h3>Total Registrations</h3>
                <div class="num"><?php echo $totalRegistrations; ?></div>
                <div class="trend">Product units currently linked</div>
            </div>
            <div class="stat-tile stat-tile-alert <?php echo $maintCount > 0 ? 'alert' : ''; ?>" data-animate="slide-up" id="maint-stat-tile">
                <h3>Action Required</h3>
                <div class="num"><?php echo $maintCount; ?></div>
                <div class="trend" style="<?php echo $maintCount > 0 ? 'color: var(--danger);' : ''; ?>">Units requiring maintenance soon</div>
            </div>
        </div>

        <!-- ── Search & Filter Controls ── -->
        <div class="controls-bar" data-animate="fade-in">
            <div class="search-field">
                <input type="text" id="global-search" placeholder="Search by PID, Phone, or Type..." oninput="filterTable()">
            </div>
            <div class="filter-group">
                <button class="filter-btn active" data-filter="all" onclick="setStatusFilter('all')">All</button>
                <button class="filter-btn" data-filter="active" onclick="setStatusFilter('active')">Active</button>
                <button class="filter-btn" data-filter="due" onclick="setStatusFilter('due')">Due Soon</button>
                <button class="filter-btn" data-filter="expired" onclick="setStatusFilter('expired')">Expired</button>
            </div>
        </div>

        <!-- ── Data Registry ── -->
        <div class="data-table-wrap" data-animate="slide-up">
            <table id="warranty-table">
                <thead>
                    <tr>
                        <th>Customer / Contact</th>
                        <th>Product ID / Type</th>
                        <th>Filter Expiry</th>
                        <th>Service Expiry</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($allWarranties)): ?>
                        <tr><td colspan="5" class="text-center py-5 opacity-50">No units registered in the system.</td></tr>
                    <?php else: ?>
                        <?php foreach($allWarranties as $w): 
                            $isFilterDue = strtotime($w['filter_expiry']) < strtotime('+14 days');
                            $isServiceDue = strtotime($w['service_expiry']) < strtotime('+14 days');
                            $isExpired = strtotime($w['warranty_expiry']) < time();
                            $status = $isExpired ? 'expired' : (($isFilterDue || $isServiceDue) ? 'due' : 'active');
                        ?>
                            <tr class="warranty-row" data-status="<?php echo $status; ?>" data-search="<?php echo strtolower($w['phone'] . ' ' . $w['product_id'] . ' ' . $w['product_type']); ?>">
                                <td>
                                    <div class="row-phone" onclick="viewClient(<?php echo $w['user_id']; ?>, '<?php echo $w['phone']; ?>')"><?php echo htmlspecialchars($w['phone']); ?></div>
                                    <div style="font-size: 0.65rem; color: #475569; margin-top: 4px;">Registered: <?php echo date('M d, Y', strtotime($w['registration_date'])); ?></div>
                                </td>
                                <td>
                                    <div class="row-id"><?php echo htmlspecialchars($w['product_id']); ?></div>
                                    <div style="font-size: 0.7rem; color: #64748b; margin-top: 4px;"><?php echo htmlspecialchars($w['product_type']); ?></div>
                                </td>
                                <td>
                                    <div class="date-maint <?php echo $isFilterDue ? 'due' : ''; ?>"><?php echo date('M d, Y', strtotime($w['filter_expiry'])); ?></div>
                                    <div style="font-size: 0.6rem; color: #475569;"><?php echo $isFilterDue ? '⚠️ REPLACEMENT DUE' : 'Status: Regular'; ?></div>
                                </td>
                                <td>
                                    <div class="date-maint <?php echo $isServiceDue ? 'due' : ''; ?>"><?php echo date('M d, Y', strtotime($w['service_expiry'])); ?></div>
                                    <div style="font-size: 0.6rem; color: #475569;"><?php echo $isServiceDue ? '⚠️ ANNUAL SERVICE DUE' : 'Status: Regular'; ?></div>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <button class="btn-action" title="View Profile & Job History" onclick="viewClient(<?php echo $w['user_id']; ?>, '<?php echo $w['phone']; ?>', '<?php echo $w['product_id']; ?>')">👤</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ── Model Manager ── -->
        <h2 class="section-title" data-animate="fade-in">Model Manager</h2>
        <div class="model-grid" data-animate="slide-up">
            <?php foreach($allProducts as $p): ?>
                <div class="model-card">
                    <img src="<?php echo htmlspecialchars($p['product_image']); ?>" alt="">
                    <div style="flex: 1;">
                        <h4><?php echo strtoupper(htmlspecialchars($p['product_type'])); ?></h4>
                        <p><?php echo substr(htmlspecialchars($p['description']), 0, 40); ?>...</p>
                    </div>
                    <div class="model-actions">
                        <button class="btn-model-mini" title="Edit" onclick='openModelModal(<?php echo json_encode($p); ?>)'>✏️</button>
                        <button class="btn-model-mini danger" title="Delete" onclick="deleteModel(<?php echo $p['id']; ?>, '<?php echo $p['product_type']; ?>')">🗑️</button>
                    </div>
                </div>
            <?php endforeach; ?>
            <button class="model-card add-model-card" onclick="openAddModelModal()"><div class="plus-icon">+</div><div class="label">Add New Product</div></button>
        </div>
    </div>

    <!-- ── MODALS ── -->
    
    <!-- Client Profile & History Modal -->
    <div id="modal-client" class="modal-overlay">
        <div class="modal-content large">
            <!-- Hero Header -->
            <div class="client-modal-hero">
                <div class="client-hero-top">
                    <div class="client-hero-identity">
                        <div class="client-avatar">👤</div>
                        <div>
                            <div id="client-phone-display" class="client-phone-num">+966...</div>
                            <div class="client-label">Client Service Profile & History</div>
                        </div>
                    </div>
                    <button class="btn-close-modal" onclick="closeModal('modal-client')" title="Close">✕</button>
                </div>
            </div>

            <!-- Unit Tabs -->
            <div id="unit-tabs" class="unit-selector"></div>

            <!-- Unit Content -->
            <div id="unit-content"></div>
        </div>
    </div>

    <!-- Model Edit Modal -->
    <div id="modal-model" class="modal-overlay">
        <div class="modal-content">
            <h2 id="model-modal-title" style="margin-bottom: 1.5rem;">Edit Model</h2>
            <input type="hidden" id="edit-product-id">
            <div class="form-group"><label>Model Identifier (e.g. a1)</label><input type="text" id="edit-product-type" placeholder="a1"></div>
            <div class="form-group" style="display: flex; gap: 1rem; align-items: start;">
                <div style="flex: 1;"><label>Image Source (URL / Path)</label><input type="text" id="edit-product-image" placeholder="assets/img/..." oninput="updateImagePreview(this.value)"></div>
                <div id="model-image-preview" style="width: 80px; height: 80px; background: #000; border-radius: 12px; border: 1px solid var(--admin-border); display: flex; align-items: center; justify-content: center; overflow: hidden; margin-top: 1.6rem;"><img src="" style="max-width: 100%; max-height: 100%; object-fit: contain;"></div>
            </div>
            <div class="form-group"><label>Public Description</label><textarea id="edit-product-desc" style="width: 100%; height: 100px; background: var(--input-bg); border: 1px solid var(--input-border); border-radius: 12px; color: #fff; padding: 1rem; font-family: inherit; font-size: 0.85rem; resize: none;"></textarea></div>
            <div style="display: flex; gap: 1rem; margin-top: 2rem;"><button class="btn btn-primary" onclick="saveModel()" style="flex: 2;">Save Model</button><button class="btn" onclick="closeModal('modal-model')" style="flex: 1;">Cancel</button></div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script>
        let currentFilter = 'all';

        const handleLogout = async () => {
            const fd = new FormData();
            fd.append('action', 'logout');
            await fetch('auth.php', { method: 'POST', body: fd });
            window.location.href = 'index.php';
        };

        const filterTable = () => {
            const query = document.getElementById('global-search').value.toLowerCase();
            document.querySelectorAll('.warranty-row').forEach(row => {
                const matchesSearch = row.getAttribute('data-search').includes(query);
                const matchesFilter = (currentFilter === 'all' || row.getAttribute('data-status') === currentFilter);
                row.style.display = (matchesSearch && matchesFilter) ? 'table-row' : 'none';
            });
        };

        const setStatusFilter = (status) => {
            currentFilter = status;
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.toggle('active', btn.dataset.filter === status));
            filterTable();
        };

        const openModal = (id) => {
            const modal = document.getElementById(id);
            modal.style.display = 'flex';
            gsap.to(modal, { opacity: 1, duration: 0.3 });
        };

        const closeModal = (id) => {
            const modal = document.getElementById(id);
            gsap.to(modal, { opacity: 0, duration: 0.3, onComplete: () => modal.style.display = 'none' });
        };

        // ── Client Details & History ──
        const viewClient = async (userId, phone, autoSelectPid = null) => {
            document.getElementById('client-phone-display').innerText = phone;
            document.getElementById('unit-tabs').innerHTML = '<p class="opacity-50">Loading units...</p>';
            document.getElementById('unit-content').innerHTML = '';
            openModal('modal-client');

            const fd = new FormData();
            fd.append('action', 'admin_get_client_details');
            fd.append('user_id', userId);

            try {
                const res = await fetch('auth.php', { method: 'POST', body: fd });
                const json = await res.json();
                if (json.success) renderClientProfile(json.data, autoSelectPid);
            } catch (e) { alert("Error loading history."); }
        };

        const renderClientProfile = (units, autoSelectPid) => {
            const tabContainer = document.getElementById('unit-tabs');
            const contentContainer = document.getElementById('unit-content');
            tabContainer.innerHTML = ''; contentContainer.innerHTML = '';

            units.forEach((u, i) => {
                const tab = document.createElement('div');
                tab.className = `unit-tab ${((autoSelectPid === u.product_id) || (!autoSelectPid && i === 0)) ? 'active' : ''}`;
                tab.innerText = u.product_id;
                tab.onclick = () => {
                    document.querySelectorAll('.unit-tab').forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    document.querySelectorAll('.profile-unit-card').forEach(c => c.classList.remove('active'));
                    document.getElementById(`unit-card-${u.id}`).classList.add('active');
                };
                tabContainer.appendChild(tab);

                const card = document.createElement('div');
                card.id = `unit-card-${u.id}`;
                card.className = `profile-unit-card ${((autoSelectPid === u.product_id) || (!autoSelectPid && i === 0)) ? 'active' : ''}`;
                
                const typeLabel = { activation: '🚀 Activation', filter: '💧 Filter Change', service: '⚙️ RO Service', other: '🔧 General Maintenance' };
                let historyHtml = u.history.map(h => `
                    <div class="timeline-item ${h.service_type}">
                        <div class="timeline-item-header">
                            <h5>${typeLabel[h.service_type] || h.service_type}</h5>
                            <div class="date">${new Date(h.created_at).toLocaleString('en-US', {month:'short',day:'numeric',year:'numeric',hour:'2-digit',minute:'2-digit'})}</div>
                        </div>
                        ${h.notes ? `<div class="notes">${h.notes}</div>` : ''}
                    </div>
                `).join('');

                card.innerHTML = `
                    <!-- Unit Info Bar -->
                    <div class="unit-info-bar">
                        <div class="unit-model-img">
                            <img src="${u.product_image}" alt="${u.product_type}">
                        </div>
                        <div>
                            <div class="unit-model-name">MODEL: ${u.product_type.toUpperCase()}</div>
                            <div class="unit-model-sub">Warranty Registered on ${new Date(u.registration_date).toLocaleDateString('en-US', {year:'numeric',month:'long',day:'numeric'})}</div>
                        </div>
                        <div class="unit-model-badge">Active</div>
                    </div>

                    <!-- Job Order Form -->
                    <div class="job-order-box">
                        <div class="job-order-label">Add New Job Order</div>
                        <div class="job-order-grid">
                            <select id="job-type-${u.id}">
                                <option value="filter">💧 Filter Replacement</option>
                                <option value="service" ${u.product_type === 'a1' ? '' : 'selected'}>⚙️ Annual RO Service</option>
                                <option value="other">🔧 General Maintenance</option>
                            </select>
                            <input type="text" id="job-notes-${u.id}" placeholder="Internal notes (optional)...">
                            <button class="btn-save-job" onclick="saveJobOrder(${u.id})">Save Job</button>
                        </div>
                    </div>

                    <!-- Timeline -->
                    <div class="timeline-section-label">Service Timeline</div>
                    <div class="timeline">${historyHtml || '<p style="color:#475569;font-size:0.82rem;text-align:center;padding:1.5rem 0;">No service history recorded yet.</p>'}</div>
                `;
                contentContainer.appendChild(card);
            });
        };

        const saveJobOrder = async (warrantyId) => {
            const type = document.getElementById(`job-type-${warrantyId}`).value;
            const notes = document.getElementById(`job-notes-${warrantyId}`).value;

            const fd = new FormData();
            fd.append('action', 'admin_add_job_order');
            fd.append('warranty_id', warrantyId);
            fd.append('type', type);
            fd.append('notes', notes);

            try {
                const res = await fetch('auth.php', { method: 'POST', body: fd });
                const json = await res.json();
                if (json.success) {
                    alert("Job order saved!");
                    location.reload();
                } else alert(json.message);
            } catch (e) { alert("Error saving job."); }
        };

        // ── Model Manager Logic ──
        const openModelModal = (p) => {
            document.querySelector('#model-modal-title').innerText = "Edit Model: " + p.product_type.toUpperCase();
            document.getElementById('edit-product-id').value = p.id;
            document.getElementById('edit-product-type').value = p.product_type;
            document.getElementById('edit-product-image').value = p.product_image;
            document.getElementById('edit-product-desc').value = p.description;
            updateImagePreview(p.product_image); openModal('modal-model');
        };

        const openAddModelModal = () => {
            document.querySelector('#model-modal-title').innerText = "Add New Model";
            document.getElementById('edit-product-id').value = "";
            document.getElementById('edit-product-type').value = "";
            document.getElementById('edit-product-image').value = "assets/img/";
            document.getElementById('edit-product-desc').value = "";
            updateImagePreview(""); openModal('modal-model');
        };

        const updateImagePreview = (url) => { const img = document.querySelector('#model-image-preview img'); img.src = url || ''; img.style.display = url ? 'block' : 'none'; };

        const saveModel = async () => {
            const id = document.getElementById('edit-product-id').value;
            const fd = new FormData();
            fd.append('action', id ? 'admin_update_product' : 'admin_add_product');
            if (id) fd.append('id', id);
            fd.append('type', document.getElementById('edit-product-type').value);
            fd.append('image', document.getElementById('edit-product-image').value);
            fd.append('description', document.getElementById('edit-product-desc').value);
            const res = await fetch('auth.php', { method: 'POST', body: fd });
            if ((await res.json()).success) location.reload();
        };

        const deleteModel = async (id, type) => {
            if (!confirm(`Delete model ${type}?`)) return;
            const fd = new FormData(); fd.append('action', 'admin_delete_product'); fd.append('id', id);
            if ((await (await fetch('auth.php', { method: 'POST', body: fd })).json()).success) location.reload();
        };

        window.addEventListener('load', () => {
            gsap.to("[data-animate='fade-in']", { opacity: 1, duration: 1, stagger: 0.2 });
            gsap.to("[data-animate='slide-up']", { opacity: 1, y: 0, duration: 1, stagger: 0.15 });
        });
    </script>
</body>
</html>
