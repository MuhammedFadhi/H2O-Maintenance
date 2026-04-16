<?php
require_once 'config.php';

/**
 * Handle POST requests for Auth and Warranty registration
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        if ($action === 'register') {
            $phone = $_POST['phone'];
            $password = $_POST['password'];

            // Validation
            if (empty($phone) || empty($password)) {
                throw new Exception("Please fill all fields.");
            }

            // Hash password
            $hash = password_hash($password, PASSWORD_DEFAULT);

            // Insert user
            $stmt = $pdo->prepare("INSERT INTO users (phone, password) VALUES (?, ?)");
            $stmt->execute([$phone, $hash]);

            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['phone'] = $phone;

            echo json_encode(['success' => true, 'message' => 'Registration successful!']);
        } 
        
        else if ($action === 'login') {
            $phone = $_POST['phone'];
            $password = $_POST['password'];

            $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['phone'] = $user['phone'];
                $_SESSION['role'] = $user['role'];
                echo json_encode(['success' => true]);
            } else {
                throw new Exception("Invalid phone or password.");
            }
        }

        else if ($action === 'register_warranty') {
            if (!isset($_SESSION['user_id'])) {
                throw new Exception("Please log in first.");
            }

            $userId = $_SESSION['user_id'];
            $pid = $_POST['pid'];
            $type = $_POST['type'];

            if (empty($pid) || empty($type)) {
                throw new Exception("Missing product details.");
            }

            // Check for existing registration
            $checkStmt = $pdo->prepare("SELECT id FROM warranties WHERE product_id = ?");
            $checkStmt->execute([$pid]);
            if ($checkStmt->fetch()) {
                throw new Exception("This product is already registered.");
            }

            $regNow = time();
            $regDate = date('Y-m-d H:i:s', $regNow);
            $expiries = calculateExpiries($regNow);

            $stmt = $pdo->prepare("INSERT INTO warranties (user_id, product_id, product_type, registration_date, filter_expiry, service_expiry, warranty_expiry) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $pid, $type, $regDate, $expiries['filter'], $expiries['service'], $expiries['warranty']]);
            $warrantyId = $pdo->lastInsertId();

            // Log Initial Activation in History
            $histStmt = $pdo->prepare("INSERT INTO service_history (warranty_id, service_type, notes) VALUES (?, 'activation', 'Initial product activation and registration')");
            $histStmt->execute([$warrantyId]);

            // Clear session pending data
            unset($_SESSION['pending_pid']);
            unset($_SESSION['pending_type']);

            echo json_encode(['success' => true, 'message' => 'Warranty registered successfully!']);
        }

        else if ($action === 'logout') {
            session_destroy();
            echo json_encode(['success' => true]);
        }

        /**
         * ADMIN ACTIONS
         */
        else if (strpos($action, 'admin_') === 0) {
            if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
                throw new Exception("Unauthorized access.");
            }

            if ($action === 'admin_add_job_order') {
                $warrantyId = $_POST['warranty_id'];
                $type = $_POST['type']; // 'filter', 'service', 'other'
                $notes = $_POST['notes'];

                // 1. Log the History
                $stmt = $pdo->prepare("INSERT INTO service_history (warranty_id, service_type, notes) VALUES (?, ?, ?)");
                $stmt->execute([$warrantyId, $type, $notes]);

                // 2. Update Expiry (if applicable)
                if ($type === 'filter') {
                    $upd = $pdo->prepare("UPDATE warranties SET filter_expiry = TIMESTAMPADD(DAY, 90, NOW()) WHERE id = ?");
                    $upd->execute([$warrantyId]);
                } else if ($type === 'service') {
                    $upd = $pdo->prepare("UPDATE warranties SET service_expiry = TIMESTAMPADD(DAY, 365, NOW()) WHERE id = ?");
                    $upd->execute([$warrantyId]);
                }

                echo json_encode(['success' => true, 'message' => 'Job order saved successfully!']);
            }

            else if ($action === 'admin_get_client_details') {
                $userId = $_POST['user_id'];

                // Fetch all units for this user
                $stmt = $pdo->prepare("
                    SELECT w.*, p.product_image 
                    FROM warranties w 
                    LEFT JOIN products p ON w.product_type = p.product_type 
                    WHERE w.user_id = ?
                    ORDER BY w.registration_date DESC
                ");
                $stmt->execute([$userId]);
                $units = $stmt->fetchAll();

                // For each unit, fetch history
                foreach ($units as &$unit) {
                    $hStmt = $pdo->prepare("SELECT * FROM service_history WHERE warranty_id = ? ORDER BY created_at DESC");
                    $hStmt->execute([$unit['id']]);
                    $unit['history'] = $hStmt->fetchAll();
                }

                echo json_encode(['success' => true, 'data' => $units]);
            }

            else if ($action === 'admin_update_product') {
                $pid = $_POST['id'];
                $img = $_POST['image'];
                $desc = $_POST['description'];

                $stmt = $pdo->prepare("UPDATE products SET product_image = ?, description = ? WHERE id = ?");
                $stmt->execute([$img, $desc, $pid]);
                echo json_encode(['success' => true, 'message' => 'Model updated successfully!']);
            }
            
            else if ($action === 'admin_add_product') {
                $type = $_POST['type'];
                $img = $_POST['image'];
                $desc = $_POST['description'];

                $stmt = $pdo->prepare("INSERT INTO products (product_type, product_image, description) VALUES (?, ?, ?)");
                $stmt->execute([$type, $img, $desc]);
                echo json_encode(['success' => true, 'message' => 'New model added successfully!']);
            }

            else if ($action === 'admin_delete_product') {
                $id = $_POST['id'];
                $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'Model deleted successfully!']);
            }
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>
