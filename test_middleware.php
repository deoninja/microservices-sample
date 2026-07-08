<?php
require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

echo "Kernel middleware aliases:\n";
print_r($kernel->getMiddlewareAliases());

echo "\nRouter middleware map:\n";
$router = $app['router'];
print_r($router->getMiddleware());

echo "\nRouter === \$app['router']: " . (spl_object_id($app['router']) === spl_object_id($router) ? 'SAME' : 'DIFFERENT') . "\n";

echo "\nRouter class: " . get_class($router) . "\n";
echo "Router hash: " . spl_object_hash($router) . "\n";
echo "Router hash from app: " . spl_object_hash($app['router']) . "\n";
