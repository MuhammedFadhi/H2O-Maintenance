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

            $regDate = date('Y-m-d');
            $expDate = getExpiryDate();

            $stmt = $pdo->prepare("INSERT INTO warranties (user_id, product_id, product_type, registration_date, expiry_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $pid, $type, $regDate, $expDate]);

            // Clear session pending data
            unset($_SESSION['pending_pid']);
            unset($_SESSION['pending_type']);

            echo json_encode(['success' => true, 'message' => 'Warranty registered successfully!']);
        }

        else if ($action === 'logout') {
            session_destroy();
            echo json_encode(['success' => true]);
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>
