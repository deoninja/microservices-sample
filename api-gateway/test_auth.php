<?php
require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Boot Laravel app to use config()
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Http\Kernel::class);

$result = \App\Helpers\ProxyHelper::forward('POST',
    \App\Helpers\ProxyHelper::serviceUrl('user_service') . '/api/users/login',
    ['username' => 'john', 'password' => 'password']
);

echo "success: " . var_export($result['success'], true) . "\n";
echo "status:  " . $result['status'] . "\n";
echo "body:    " . json_encode($result['body']) . "\n";

// Simulate what AuthController does
if (!$result['success']) {
    echo "\nAUTH RESULT: Invalid credentials (success=false)\n";
} else {
    $user = $result['body'];
    echo "\nAUTH RESULT: Got user: " . json_encode($user) . "\n";
    echo "user[id] = " . var_export($user['id'] ?? 'MISSING', true) . "\n";
    echo "user[role] = " . var_export($user['role'] ?? 'MISSING', true) . "\n";
}
