<?php
require_once 'config.php';

try {
    // 1. Alter table structure
    $queries = [
        "ALTER TABLE warranties CHANGE registration_date registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
        "ALTER TABLE warranties CHANGE expiry_date filter_expiry TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
        "ALTER TABLE warranties ADD COLUMN service_expiry TIMESTAMP NULL AFTER filter_expiry",
        "ALTER TABLE warranties ADD COLUMN warranty_expiry TIMESTAMP NULL AFTER service_expiry"
    ];

    foreach ($queries as $q) {
        try {
            $pdo->exec($q);
            echo "Executed: $q\n";
        } catch (Exception $e) {
            echo "Skipped/Failed: $q (" . $e->getMessage() . ")\n";
        }
    }

    // 2. Populate existing records
    $stmt = $pdo->query("SELECT id, registration_date FROM warranties WHERE service_expiry IS NULL");
    $items = $stmt->fetchAll();

    foreach ($items as $item) {
        $reg = strtotime($item['registration_date']);
        $filter = date('Y-m-d H:i:s', $reg + (90 * 86400));
        $service = date('Y-m-d H:i:s', $reg + (365 * 86400));
        $warranty = date('Y-m-d H:i:s', $reg + (730 * 86400));

        $update = $pdo->prepare("UPDATE warranties SET filter_expiry = ?, service_expiry = ?, warranty_expiry = ? WHERE id = ?");
        $update->execute([$filter, $service, $warranty, $item['id']]);
        echo "Updated record ID: {$item['id']}\n";
    }

    echo "Migration completed successfully.\n";
} catch (Exception $e) {
    die("Global Error: " . $e->getMessage());
}
