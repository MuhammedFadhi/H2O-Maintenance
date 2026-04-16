<?php
require_once 'config.php';

$phoneToPromote = '0547989055'; // The number from the system support link

try {
    $stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE phone = ?");
    $stmt->execute([$phoneToPromote]);
    
    if ($stmt->rowCount() > 0) {
        echo "Successfully promoted $phoneToPromote to Admin.\n";
        echo "You can now log in with this number to access the admin panel.\n";
    } else {
        echo "No user found with phone number $phoneToPromote.\n";
        echo "Please make sure you have registered this number first.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
