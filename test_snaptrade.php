<?php
declare(strict_types=1);

// CLI verification script -- run: php test_snaptrade.php

require_once __DIR__ . '/bootstrap.php';

// Check required environment variables
if (empty($_ENV['SNAPTRADE_CLIENT_ID']) || empty($_ENV['SNAPTRADE_CONSUMER_KEY'])) {
    echo "\nERROR: Missing SnapTrade credentials\n";
    echo "---------------------------------------\n";
    echo "You need to set the following in your .env file:\n";
    echo "  - SNAPTRADE_CLIENT_ID\n";
    echo "  - SNAPTRADE_CONSUMER_KEY\n";
    echo "\nGet your credentials from: https://snaptrade.com/dashboard\n";
    echo "Then add them to .env:\n";
    echo "  SNAPTRADE_CLIENT_ID=your_client_id_here\n";
    echo "  SNAPTRADE_CONSUMER_KEY=your_consumer_key_here\n\n";
    exit(1);
}

// Initialize SnapTrade client
try {
    $snaptrade = new \SnapTrade\Client(
        clientId: $_ENV['SNAPTRADE_CLIENT_ID'],
        consumerKey: $_ENV['SNAPTRADE_CONSUMER_KEY']
    );

    // Test API connection with registerSnapTradeUser call
    $testUserId = 'stockd-test-' . time();
    $result = $snaptrade->authentication->registerSnapTradeUser([
        'userId' => $testUserId
    ]);

    // Success!
    echo "\n✓ SnapTrade API connection verified\n";
    echo "-----------------------------------\n";
    echo "Client ID: " . $_ENV['SNAPTRADE_CLIENT_ID'] . "\n";
    echo "Test User ID: " . $testUserId . "\n";
    echo "\nSDK is ready for Phase 2 integration.\n\n";
    exit(0);

} catch (\Exception $e) {
    echo "\n✗ SnapTrade API connection FAILED\n";
    echo "-----------------------------------\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "\nTroubleshooting:\n";
    echo "  1. Check your credentials are correct in .env\n";
    echo "  2. Verify you have network connectivity\n";
    echo "  3. Confirm your SnapTrade account is active\n\n";
    exit(1);
}
