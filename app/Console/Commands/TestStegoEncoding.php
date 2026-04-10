<?php

namespace App\Console\Commands;

use App\Services\Stego\StegoService;
use Illuminate\Console\Command;

class TestStegoEncoding extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:stego-encoding';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test stego encoding with large payload to verify we fixed the command line length issue';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting stego encoding test with large payload...');
        
        // Create a test payload (100KB) to ensure we test the command line limit
        $data = str_repeat('a', 100000); 
        
        // Create a temporary test image with proper extension
        $carrierPath = tempnam(sys_get_temp_dir(), 'test_carrier') . '.png';
        $image = imagecreatetruecolor(2000, 2000);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $white);
        imagepng($image, $carrierPath);
        imagedestroy($image);
        
        // Test the embedLSBPython method
        try {
            $stegoService = app(StegoService::class);
            $outputPath = tempnam(sys_get_temp_dir(), 'test_output') . '.png';
            
            $this->info('Encoding data into carrier image...');
            $stegoService->embed($carrierPath, $data, $outputPath);
            $this->info("Success! Output file created: $outputPath");
            
            // Verify the embedded data can be extracted
            $this->info('Extracting data from stego image...');
            $extractedData = $stegoService->extract($outputPath);
            if ($extractedData === $data) {
                $this->info('✅ Success! Extracted data matches original data');
            } else {
                $this->error('❌ Failed! Extracted data does not match original data');
            }
            
            // Cleanup
            unlink($carrierPath);
            unlink($outputPath);
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        }
        
        return 0;
    }
}
