<?php

namespace App\Services\Stego;

use Exception;
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
                : (function () use ($carrierPath, $mime) {
                    $image  = $this->loadImage($carrierPath, $mime);
                    $pixels = imagesx($image) * imagesy($image);
                    imagedestroy($image);
                    // 3 channels × 1 bit each = 3 bits/pixel; divide by 8 for bytes,
                    // minus 4 bytes for the length prefix stored in LSBs.
                    return (int) (($pixels * 3 * self::BITS_PER_CHANNEL) / 8) - 4;
                })();
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

        $result = $this->runPythonScript('embed', [$carrierPath, $b64Payload, $outputPath]);

        return $result['data']; // returns the output path
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

        return (int) $result['data'];
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
            throw new Exception(
                "Python stego process produced no output. Exit code: {$process->getExitCode()}." .
                ($stderr ? " STDERR: {$stderr}" : '')
            );
        }

        $decoded = json_decode($output, associative: true);

        if (!is_array($decoded)) {
            throw new Exception("Python stego script returned non-JSON output: {$output}");
        }

        if (empty($decoded['success'])) {
            throw new Exception('Python stego error: ' . ($decoded['error'] ?? 'unknown error'));
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

        if ($bitCount > $pixels * 3) {
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

    private function isLsbCapable(string $mime): bool
    {
        return in_array($mime, ['image/png', 'image/bmp', 'image/x-bmp'], true);
    }

    private function loadImage(string $path, string $mime): GdImage
    {
        $image = match ($mime) {
            'image/png'           => imagecreatefrompng($path),
            'image/bmp',
            'image/x-bmp'         => imagecreatefrombmp($path),
            default               => throw new Exception("Unsupported image MIME type for LSB: {$mime}"),
        };

        if ($image === false) {
            throw new Exception("GD could not load image: {$path}");
        }

        return $image;
    }

    private function saveImage(GdImage $image, string $outputPath, string $mime): void
    {
        $ok = match ($mime) {
            'image/png'           => imagepng($image, $outputPath, 9),
            'image/bmp',
            'image/x-bmp'         => imagebmp($image, $outputPath),
            default               => throw new Exception("Unsupported image MIME type for save: {$mime}"),
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
