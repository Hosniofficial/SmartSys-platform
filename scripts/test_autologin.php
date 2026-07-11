<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'vendor/autoload.php';

try {
    $containerFn = require 'config/container.php';
    $container = $containerFn();
    
    // Check if AuthHandler exists
    if ($container->has(\App\Handlers\AuthHandler::class)) {
        echo "✓ AuthHandler registered in container\n";
        $authHandler = $container->get(\App\Handlers\AuthHandler::class);
        echo "✓ AuthHandler instantiated\n";
        
        // Test token generation
        $testUser = [
            'id' => 1,
            'username' => 'test',
            'role_id' => 3,
            'tenant_id' => 1,
            'branch_id' => 1,
            'is_owner' => 0
        ];
        
        $token = $authHandler->generateAccessTokenPublic($testUser);
        if ($token) {
            echo "✓ Token generated successfully\n";
            echo "Token length: " . strlen($token) . " chars\n";
            echo "Token preview: " . substr($token, 0, 50) . "...\n";
        } else {
            echo "✗ Token generation returned empty\n";
        }
    } else {
        echo "✗ AuthHandler NOT registered in container\n";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
