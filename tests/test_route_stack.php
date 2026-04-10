ys<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;

echo "=== Checking Laravel Application ===" . PHP_EOL;
echo "Application Name: " . config('app.name') . PHP_EOL;
echo "Environment: " . config('app.env') . PHP_EOL;
echo "Debug: " . (config('app.debug') ? 'true' : 'false') . PHP_EOL;
echo PHP_EOL;

echo "=== Testing Route Collection ===" . PHP_EOL;
$routeCollection = Route::getRoutes();

foreach ($routeCollection as $route) {
    if (strpos($route->uri(), 'stego') !== false) {
        echo "Route: " . $route->uri() . " (" . implode('|', $route->methods()) . ")" . PHP_EOL;
        
        // Check if route has auth middleware
        if (in_array('auth:sanctum', $route->middleware())) {
            echo "  - Protected: auth:sanctum" . PHP_EOL;
        } else {
            echo "  - Not protected" . PHP_EOL;
        }
        
        if (in_array('throttle', $route->middleware())) {
            echo "  - Throttled" . PHP_EOL;
        }
        
        echo PHP_EOL;
    }
}

echo PHP_EOL . "=== Testing Through HTTP Kernel ===" . PHP_EOL;
try {
    $request = Request::create('/api/stego/documents/21', 'GET');
    $request->headers->set('Authorization', 'Bearer test-token');
    
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle($request);
    
    echo "Status: " . $response->getStatusCode() . PHP_EOL;
    echo "Body: " . $response->getContent() . PHP_EOL;
    
    $kernel->terminate($request, $response);
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    echo "Stack Trace: " . $e->getTraceAsString() . PHP_EOL;
}
