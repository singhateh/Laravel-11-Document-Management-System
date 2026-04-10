<?php

namespace App\Services\Stego;

use Exception;
use Illuminate\Support\Facades\Log;
use GdImage;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * StegoService
 *
 * Handles steganographic embedding and extraction of data within carrier files.
 *
 * Supported carrier types:
 *  - Images (PNG, BMP) : LSB via Python stegano library (driver = 'python', default)
 *                        or via PHP GD extension          (driver = 'php')
 *  - Other files       : Append-with-marker approach (safe for binary carrier types
 *                        that tolerate appended data, e.g. text, generic binary)
 *
 * Driver selection is controlled by the STEGO_DRIVER .env variable:
 *   STEGO_DRIVER=python  — Uses Python stegano library (requires pip install stegano Pillow)
 *   STEGO_DRIVER=php     — Uses built-in PHP GD extension (no extra dependencies)
 *
 * Replaces the gRPC stego-service microservice described in the .md guide.
 */
class StegoService
{
    // Unique binary marker that separates carrier content from hidden payload.
    // Chosen to be unlikely to appear naturally in image/binary data.
    private const MARKER = "\x53\x54\x45\x47\x4F\x4C\x4F\x43\x4B"; // "STEGOLOCK"

    // Maximum bytes that can be hidden per pixel channel using LSB.
    // 1 bit per channel × 3 channels (R, G, B) = 3 bits per pixel = ~0.375 bytes/pixel.
    private const BITS_PER_CHANNEL = 1;

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Embed binary data into a carrier file.
     *
     * @param  string $carrierPath  Absolute path to the carrier file
     * @param  string $data         Raw binary data to hide (already encrypted)
     * @param  string $outputPath   Path to write the modified carrier file
     * @return string               The output path
     * @throws Exception
     */
    public function embed(string $carrierPath, string $data, string $outputPath): string
    {
        $this->assertFileExists($carrierPath);

        $mime = mime_content_type($carrierPath);

        if ($this->isLsbCapable($mime)) {
            return $this->isPythonDriver()
                ? $this->embedLSBPython($carrierPath, $data, $outputPath)
                : $this->embedLSB($carrierPath, $data, $outputPath, $mime);
        }

        return $this->embedAppend($carrierPath, $data, $outputPath);
    }

    /**
     * Extract hidden binary data from a carrier file.
     *
     * @param  string $carrierPath Absolute path to the (modified) carrier file
     * @return string              Raw binary data that was hidden
     * @throws Exception
     */
    public function extract(string $carrierPath): string
    {
        $this->assertFileExists($carrierPath);

        $mime = mime_content_type($carrierPath);

        if ($this->isLsbCapable($mime)) {
            return $this->isPythonDriver()
                ? $this->extractLSBPython($carrierPath)
                : $this->extractLSB($carrierPath);
        }

        return $this->extractAppend($carrierPath);
    }

    /**
     * Calculate the PSNR (Peak Signal-to-Noise Ratio) between the original carrier
     * and the stego image to quantify the visual quality impact of LSB embedding.
     *
     * PSNR >= 40 dB is the accepted threshold for imperceptible modifications.
     * Requires the Python driver (uses opencv-python cv2.PSNR()).
     *
     * @param  string $originalPath Absolute path to the original (unmodified) carrier image
     * @param  string $stegoPath    Absolute path to the stego image
     * @return array{ psnr: float, threshold_40db: bool, quality: string }
     * @throws Exception
     */
    public function psnr(string $originalPath, string $stegoPath): array
    {
        $this->assertFileExists($originalPath);
        $this->assertFileExists($stegoPath);

        $result = $this->runPythonScript('psnr', [$originalPath, $stegoPath]);

        return [
            'psnr'           => (float)  $result['data']['psnr'],
            'threshold_40db' => (bool)   $result['data']['threshold_40db'],
            'quality'        => (string) $result['data']['quality'],
        ];
    }

    /**
     * Calculate the maximum payload capacity (in bytes) of a carrier file.
     *
     * @param  string $carrierPath
     * @return int Bytes
     * @throws Exception
     */
    public function capacity(string $carrierPath): int
    {
        $this->assertFileExists($carrierPath);

        $mime = mime_content_type($carrierPath);

        if ($this->isLsbCapable($mime)) {
            return $this->isPythonDriver()
                ? $this->capacityPython($carrierPath)
                : $this->capacityPhp($carrierPath, $mime);
        }

        // For append mode there is no hard limit (filesystem permitting).
        return PHP_INT_MAX;
    }

    // -------------------------------------------------------------------------
    // Python Stegano Driver
    // -------------------------------------------------------------------------

    /**
     * Returns true when the Python stegano library should be used for LSB operations.
     */
    private function isPythonDriver(): bool
    {
        return config('stegolock.driver', 'python') === 'python';
    }

    /**
     * Embed binary data into a PNG/BMP carrier using Python stegano (LSB).
     *
     * Binary data is base64-encoded before passing to Python because
     * stegano.lsb.hide() only accepts string payloads.
     *
     * @throws Exception
     */
    private function embedLSBPython(string $carrierPath, string $data, string $outputPath): string
    {
        $b64Payload = base64_encode($data);
        
        // Write payload to temporary file to avoid command line length limits
        $payloadFile = tempnam(sys_get_temp_dir(), 'stego_payload_');
        file_put_contents($payloadFile, $b64Payload);
        
        try {
            $result = $this->runPythonScript('embed', [$carrierPath, $payloadFile, $outputPath]);
            return $result['data']; // returns the output path
        } finally {
            // Clean up temporary file
            if (file_exists($payloadFile)) {
                @unlink($payloadFile);
            }
        }
    }

    /**
     * Extract LSB-hidden data from a carrier image using Python stegano.
     *
     * The Python script returns the base64-encoded payload; we decode it
     * back to raw binary before returning.
     *
     * @throws Exception
     */
    private function extractLSBPython(string $carrierPath): string
    {
        $result = $this->runPythonScript('extract', [$carrierPath]);

        $decoded = base64_decode($result['data'], strict: true);

        if ($decoded === false) {
            throw new Exception('Python stegano returned an invalid base64 payload. Image may be corrupt.');
        }

        return $decoded;
    }

    /**
     * Calculate carrier capacity (bytes) via Python / Pillow.
     *
     * The Python script already accounts for base64 overhead.
     *
     * @throws Exception
     */
    private function capacityPython(string $carrierPath): int
    {
        $result = $this->runPythonScript('capacity', [$carrierPath]);

        // Apply 10% safety buffer to match PHP driver and prevent edge case failures
        return (int) ($result['data'] * 0.9);
    }

    /**
     * Run the stego_lsb.py script and return the decoded JSON response.
     *
     * @param  string   $command  embed | extract | capacity
     * @param  string[] $args     Additional arguments
     * @return array              The decoded JSON response
     * @throws Exception          If the process fails or returns an error
     */
    private function runPythonScript(string $command, array $args = []): array
    {
        $pythonPath  = config('stegolock.python_path', 'python');
        $scriptPath  = config('stegolock.python_script', base_path('python/stego_lsb.py'));
        $timeout     = (int) config('stegolock.python_timeout', 60);

        if (!file_exists($scriptPath)) {
            throw new Exception("Python stego script not found at: {$scriptPath}");
        }

        $process = new Process(
            array_merge([$pythonPath, $scriptPath, $command], $args),
            timeout: $timeout
        );

        $process->run();

        $output = trim($process->getOutput());

        if (empty($output)) {
            $stderr = trim($process->getErrorOutput());
            
            // Hide raw Python stack traces from users and provide friendly errors
            if (!empty($stderr)) {
                // Detect common error types for friendly messages
                if (str_contains($stderr, 'ModuleNotFoundError: No module named')) {
                    $module = trim(preg_replace('/^.*ModuleNotFoundError: No module named \'([^\']+)\'.*$/s', '$1', $stderr));
                    throw new \RuntimeException("Missing required dependency: Python module '{$module}' not installed. Install using: pip install {$module}");
                }
                
                if (str_contains($stderr, 'ImportError') || str_contains($stderr, 'SyntaxError') || str_contains($stderr, 'Traceback')) {
                    throw new \RuntimeException("Steganography engine encountered an internal error. Please check your Python environment and dependencies.");
                }
                
                // Pass through other clear error messages
                if (preg_match('/^[A-Za-z0-9\s,.!?]+$/', $stderr) && strlen($stderr) < 200) {
                    throw new \RuntimeException($stderr);
                }
            }
            
            throw new \RuntimeException("Steganography process failed. Please try again with a different carrier image.");
        }

        $decoded = json_decode($output, associative: true);

        // Some third-party Python libs may print informational prompts before
        // JSON output. If full output is not valid JSON, parse the last JSON-like line.
        if (!is_array($decoded)) {
            $lines = preg_split('/\R+/', $output) ?: [];

            for ($i = count($lines) - 1; $i >= 0; $i--) {
                $candidate = trim($lines[$i]);

                if ($candidate === '') {
                    continue;
                }

                $decoded = json_decode($candidate, associative: true);

                if (is_array($decoded)) {
                    break;
                }
            }
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException("Failed to process carrier image. Please try a different image format (PNG or BMP recommended).");
        }

        if (empty($decoded['success'])) {
            // Log technical error for debugging while hiding from users
            Log::error("Steganography operation failed", [
                'error' => $decoded['error'] ?? 'unknown error',
                'code' => $decoded['code'] ?? null,
                'command' => $command,
                'args' => $args,
                'exit_code' => $process->getExitCode()
            ]);
            
            $userError = $decoded['user_message'] ?? $decoded['error'] ?? null;
            
            // Hide internal errors, show user-friendly messages
            if ($userError && preg_match('/^[A-Za-z0-9\s,.!?]+$/', $userError)) {
                throw new \RuntimeException($userError);
            }
            
            // Catch known error codes
            if (isset($decoded['code'])) {
                match ($decoded['code']) {
                    'PAYLOAD_TOO_LARGE' => throw new \RuntimeException("File is too large for this carrier image. Use a larger image or reduce file size."),
                    'INVALID_IMAGE_FORMAT' => throw new \RuntimeException("Unsupported image format. Please use PNG or BMP images. JPEG is not supported for LSB encoding."),
                    'CORRUPTED_IMAGE' => throw new \RuntimeException("Carrier image appears corrupted or is not a valid bitmap file. Try a different image."),
                    'PASSWORD_INCORRECT' => throw new \RuntimeException("Incorrect password for this document."),
                    'PSNR_THRESHOLD_FAILED' => throw new \RuntimeException("Image quality dropped too low after encoding. Use a larger carrier image."),
                    default => throw new \RuntimeException("Encoding failed. Please try again with a different carrier image.")
                };
            }
            
            // Generic fallback error with helpful instructions
            throw new \RuntimeException("Encoding failed. Please try using a clean unedited PNG image that has not been compressed or re-saved.");
        }

        return $decoded;
    }

    // -------------------------------------------------------------------------
    // LSB Steganography (PNG / BMP images via PHP GD — fallback driver)
    // -------------------------------------------------------------------------

    /**
     * Embed data into image pixels using LSB substitution (PHP GD).
     * The payload length (4 bytes, big-endian) is stored first, followed by data bits.
     */
    private function embedLSB(string $carrierPath, string $data, string $outputPath, string $mime): string
    {
        $image = $this->loadImage($carrierPath, $mime);

        $width  = imagesx($image);
        $height = imagesy($image);
        $pixels = $width * $height;

        // Prepend 4-byte big-endian length header to the payload.
        $payload = pack('N', strlen($data)) . $data;
        $bits    = $this->bytesToBits($payload);
        $bitCount = count($bits);

        // Apply safety buffer: 90% of actual capacity to prevent edge case failures
        $maxBits = (int) ($pixels * 3 * 0.9);

        if ($bitCount > $maxBits) {
            imagedestroy($image);
            throw new Exception(
                "Payload too large for carrier. Need {$bitCount} bits, capacity is " . ($pixels * 3) . " bits."
            );
        }

        $bitIndex = 0;

        outer:
        for ($y = 0; $y < $height && $bitIndex < $bitCount; $y++) {
            for ($x = 0; $x < $width && $bitIndex < $bitCount; $x++) {
                $pixel = imagecolorat($image, $x, $y);

                $r = ($pixel >> 16) & 0xFF;
                $g = ($pixel >> 8)  & 0xFF;
                $b = $pixel         & 0xFF;

                if ($bitIndex < $bitCount) {
                    $r = ($r & 0xFE) | $bits[$bitIndex++];
                }
                if ($bitIndex < $bitCount) {
                    $g = ($g & 0xFE) | $bits[$bitIndex++];
                }
                if ($bitIndex < $bitCount) {
                    $b = ($b & 0xFE) | $bits[$bitIndex++];
                }

                imagesetpixel($image, $x, $y, imagecolorallocate($image, $r, $g, $b));
            }
        }

        $this->saveImage($image, $outputPath, $mime);
        imagedestroy($image);

        return $outputPath;
    }

    /**
     * Extract LSB-embedded data from an image.
     */
    private function extractLSB(string $carrierPath): string
    {
        $mime  = mime_content_type($carrierPath);
        $image = $this->loadImage($carrierPath, $mime);

        $width  = imagesx($image);
        $height = imagesy($image);

        $bits = [];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $pixel = imagecolorat($image, $x, $y);

                // Read in R→G→B order to match embedLSB write order
                $bits[] = ($pixel >> 16) & 1;        // R LSB
                $bits[] = ($pixel >> 8)  & 1;        // G LSB
                $bits[] = $pixel         & 1;        // B LSB
            }
        }

        imagedestroy($image);

        // Read the 4-byte length header (32 bits).
        if (count($bits) < 32) {
            throw new Exception('Carrier image too small to contain a valid payload.');
        }

        $lengthBits  = array_slice($bits, 0, 32);
        $payloadLength = $this->bitsToInt($lengthBits);

        $payloadBits = array_slice($bits, 32, $payloadLength * 8);

        if (count($payloadBits) < $payloadLength * 8) {
            throw new Exception('Carrier image does not contain enough data for the declared payload length.');
        }

        return $this->bitsToBytes($payloadBits);
    }

    // -------------------------------------------------------------------------
    // Append-with-Marker Approach (text, binary files)
    // -------------------------------------------------------------------------

    /**
     * Append data to the end of a carrier file with a recognisable delimiter.
     * The appended section is: MARKER + pack('N', length) + data
     */
    private function embedAppend(string $carrierPath, string $data, string $outputPath): string
    {
        $original = file_get_contents($carrierPath);

        if ($original === false) {
            throw new Exception("Could not read carrier file: {$carrierPath}");
        }

        $payload  = self::MARKER . pack('N', strlen($data)) . $data;
        $result   = file_put_contents($outputPath, $original . $payload);

        if ($result === false) {
            throw new Exception("Could not write output file: {$outputPath}");
        }

        return $outputPath;
    }

    /**
     * Extract append-mode payload from a carrier file.
     */
    private function extractAppend(string $carrierPath): string
    {
        $content    = file_get_contents($carrierPath);
        $markerLen  = strlen(self::MARKER);
        $pos        = strrpos($content, self::MARKER);

        if ($pos === false) {
            throw new Exception('No StegoLock marker found in carrier file. File may not contain embedded data.');
        }

        $lengthBytes = substr($content, $pos + $markerLen, 4);
        $length      = unpack('N', $lengthBytes)[1];
        $data        = substr($content, $pos + $markerLen + 4, $length);

        if (strlen($data) !== $length) {
            throw new Exception('Payload length mismatch. Carrier file may be corrupt.');
        }

        return $data;
    }

    // -------------------------------------------------------------------------
    // GD Helpers
    // -------------------------------------------------------------------------

    /**
     * Calculate LSB capacity of an image using PHP GD.
     * Formula: 3 colour channels × 1 bit/channel per pixel → bytes, minus 4-byte length prefix.
     *
     * Extracted from an inline closure that previously lived inside capacity().
     */
    private function capacityPhp(string $carrierPath, string $mime): int
    {
        $image  = $this->loadImage($carrierPath, $mime);
        $pixels = imagesx($image) * imagesy($image);
        imagedestroy($image);

        return (int) (($pixels * 3 * self::BITS_PER_CHANNEL) / 8) - 4;
    }

    private function isLsbCapable(string $mime): bool
    {
        // JPEG is accepted for embedding only — the PHP GD driver always saves
        // the stego output as PNG (JPEG compression is lossy and destroys LSBs).
        return in_array($mime, ['image/png', 'image/bmp', 'image/x-bmp', 'image/jpeg', 'image/jpg'], true);
    }

    private function loadImage(string $path, string $mime): GdImage
    {
        $image = match ($mime) {
            'image/png'                     => imagecreatefrompng($path),
            'image/bmp',
            'image/x-bmp'                   => imagecreatefrombmp($path),
            'image/jpeg',
            'image/jpg'                     => function_exists('imagecreatefromjpeg')
                ? imagecreatefromjpeg($path)
                : throw new Exception(
                    'PHP GD was built without JPEG support. ' .
                    'Set STEGO_DRIVER=python in .env to use JPEG carriers.'
                ),
            default                         => throw new Exception("Unsupported image MIME type for LSB: {$mime}"),
        };

        if ($image === false) {
            throw new Exception("GD could not load image: {$path}");
        }

        return $image;
    }

    private function saveImage(GdImage $image, string $outputPath, string $mime): void
    {
        // JPEG is lossy — re-encoding as JPEG destroys LSB data.
        // Stego output is always written as lossless PNG when the carrier was JPEG.
        $saveMime = in_array($mime, ['image/jpeg', 'image/jpg'], true) ? 'image/png' : $mime;

        $ok = match ($saveMime) {
            'image/png'           => imagepng($image, $outputPath, 9),
            'image/bmp',
            'image/x-bmp'         => imagebmp($image, $outputPath),
            default               => throw new Exception("Unsupported image MIME type for save: {$saveMime}"),
        };

        if (!$ok) {
            throw new Exception("GD could not save image to: {$outputPath}");
        }
    }

    // -------------------------------------------------------------------------
    // Bit Manipulation Helpers
    // -------------------------------------------------------------------------

    /** Convert a byte string to an array of individual bits (MSB first). */
    private function bytesToBits(string $bytes): array
    {
        $bits = [];
        for ($i = 0; $i < strlen($bytes); $i++) {
            $byte = ord($bytes[$i]);
            for ($b = 7; $b >= 0; $b--) {
                $bits[] = ($byte >> $b) & 1;
            }
        }
        return $bits;
    }

    /** Convert an array of 8 × N bits (MSB first) back to a byte string. */
    private function bitsToBytes(array $bits): string
    {
        $out = '';
        $chunks = array_chunk($bits, 8);
        foreach ($chunks as $chunk) {
            if (count($chunk) < 8) {
                break;
            }
            $byte = 0;
            foreach ($chunk as $i => $bit) {
                $byte |= ($bit << (7 - $i));
            }
            $out .= chr($byte);
        }
        return $out;
    }

    /** Convert 32 bits (MSB first) to an unsigned 32-bit integer. */
    private function bitsToInt(array $bits): int
    {
        $value = 0;
        foreach ($bits as $i => $bit) {
            $value |= ($bit << (31 - $i));
        }
        return $value;
    }

    private function assertFileExists(string $path): void
    {
        if (!file_exists($path)) {
            throw new Exception("File not found: {$path}");
        }
    }
}
