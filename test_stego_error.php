<?php

require __DIR__.'/vendor/autoload.php';

use App\Services\Stego\StegoService;

// Create a test payload
$data = str_repeat('a', 100000); // Large payload to ensure we test the command line limit

// Create a temporary test image
$carrierPath = tempnam(sys_get_temp_dir(), 'test_carrier');
$image = imagecreatetruecolor(2000, 2000);
$white = imagecolorallocate($image, 255, 255, 255);
imagefill($image, 0, 0, $white);
imagepng($image, $carrierPath);
imagedestroy($image);

// Test the embedLSBPython method
try {
    $stegoService = new StegoService();
    $outputPath = tempnam(sys_get_temp_dir(), 'test_output');
    
    $stegoService->embed($carrierPath, $data, $outputPath);
    echo "Success! Output file created: $outputPath\n";
    
    // Verify the embedded data can be extracted
    $extractedData = $stegoService->extract($outputPath);
    if ($extractedData === $data) {
        echo "Success! Extracted data matches original data\n";
    } else {
        echo "Failed! Extracted data does not match original data\n";
    }
    
    // Cleanup
    unlink($carrierPath);
    unlink($outputPath);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
