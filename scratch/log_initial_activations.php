<?php
require_once dirname(__DIR__) . '/config.php';

echo "Starting history migration...\n";

try {
    // 1. Ensure table exists (just in case)
    $pdo->exec("CREATE TABLE IF NOT EXISTS service_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        warranty_id INT NOT NULL,
        service_type ENUM('activation', 'filter', 'service', 'other') NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (warranty_id) REFERENCES warranties(id) ON DELETE CASCADE
    )");

    // 2. Fetch all existing warranties
    $stmt = $pdo->query("SELECT id, registration_date FROM warranties");
    $warranties = $stmt->fetchAll();

    $count = 0;
    foreach ($warranties as $w) {
        // Check if history already exists
        $check = $pdo->prepare("SELECT id FROM service_history WHERE warranty_id = ? AND service_type = 'activation'");
        $check->execute([$w['id']]);
        
        if (!$check->fetch()) {
            // Insert activation log using original registration date
            $ins = $pdo->prepare("INSERT INTO service_history (warranty_id, service_type, notes, created_at) VALUES (?, 'activation', 'Initial product activation and registration', ?)");
            $ins->execute([$w['id'], $w['registration_date']]);
            $count++;
        }
    }

    echo "Migration completed! Logged $count existing activations.\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
