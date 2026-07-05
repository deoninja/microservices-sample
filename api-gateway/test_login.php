<?php
require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$url = (getenv('USER_SERVICE_URL') ?: 'http://localhost:3001') . '/api/users/login';
echo "Calling: $url\n";

$client = new GuzzleHttp\Client([
    'timeout'         => 10,
    'connect_timeout' => 5,
    'http_errors'     => false,
]);

try {
    $response = $client->request('POST', $url, [
        'headers' => [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ],
        'json' => ['username' => 'john', 'password' => 'password'],
    ]);

    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Body: "   . $response->getBody()->getContents() . "\n";
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
