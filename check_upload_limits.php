<?php
// Check current PHP upload limits
echo "<h1>PHP Upload Configuration</h1>";
echo "<p><strong>upload_max_filesize:</strong> " . ini_get('upload_max_filesize') . "</p>";
echo "<p><strong>post_max_size:</strong> " . ini_get('post_max_size') . "</p>";
echo "<p><strong>memory_limit:</strong> " . ini_get('memory_limit') . "</p>";
echo "<p><strong>max_execution_time:</strong> " . ini_get('max_execution_time') . " seconds</p>";

// Test function to convert ini sizes to bytes for comparison
function ini_size_to_bytes($size) {
    $size = trim($size);
    $unit = strtolower(substr($size, -1));
    $number = (float) substr($size, 0, -1);
    
    switch ($unit) {
        case 'g':
            return $number * 1024 * 1024 * 1024;
        case 'm':
            return $number * 1024 * 1024;
        case 'k':
            return $number * 1024;
        default:
            return (int) $number;
    }
}

$uploadLimit = ini_size_to_bytes(ini_get('upload_max_filesize'));
$postLimit = ini_size_to_bytes(ini_get('post_max_size'));
$memoryLimit = ini_size_to_bytes(ini_get('memory_limit'));

echo "<h2>Limits in Bytes</h2>";
echo "<p><strong>upload_max_filesize:</strong> " . $uploadLimit . " bytes</p>";
echo "<p><strong>post_max_size:</strong> " . $postLimit . " bytes</p>";
echo "<p><strong>memory_limit:</strong> " . $memoryLimit . " bytes</p>";

// Check if there's any potential issue
if ($uploadLimit < 100 * 1024 * 1024) {
    echo "<p style='color: red;'><strong>Warning:</strong> upload_max_filesize is less than 100MB. Current limit: " . ini_get('upload_max_filesize') . "</p>";
}

if ($postLimit < 100 * 1024 * 1024) {
    echo "<p style='color: red;'><strong>Warning:</strong> post_max_size is less than 100MB. Current limit: " . ini_get('post_max_size') . "</p>";
}

// Also check the Laravel configuration
echo "<h2>Laravel Configuration</h2>";
if (function_exists('config')) {
    echo "<p><strong>max_upload_size (from config):</strong> " . config('stegolock.max_carrier_size_mb') . " MB</p>";
}
?>