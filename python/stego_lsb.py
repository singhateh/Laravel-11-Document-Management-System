"""
StegoLock — Python LSB Steganography Driver
============================================
Called by Laravel's StegoService via symfony/process.

Usage:
  python stego_lsb.py embed    <carrier_path> <payload_file> <output_path>
  python stego_lsb.py extract  <stego_path>
  python stego_lsb.py capacity <image_path>

All output is a single JSON line on stdout:
  {"success": true,  "data": "<value>"}
  {"success": false, "error": "<message>"}

Binary payloads are base64-encoded before embedding and
base64-decoded after extraction to bridge stegano's string-only API.

Dependencies:
  pip install stegano Pillow
"""

import sys
import json
import base64
import os
import tempfile


def _ok(data) -> None:
    print(json.dumps({"success": True, "data": data}), flush=True)


def _err(message: str) -> None:
    print(json.dumps({"success": False, "error": message}), flush=True)


def _prepare_rgb_carrier(carrier_path: str):
    """
    Ensure the carrier is RGB to avoid interactive conversion prompts in stegano.

    Returns a tuple of:
      (path_to_use_for_embedding, temp_file_to_cleanup_or_none)
    """
    from PIL import Image

    with Image.open(carrier_path) as img:
        if img.mode == "RGB":
            return carrier_path, None

        rgb_image = img.convert("RGB")
        with tempfile.NamedTemporaryFile(suffix=".png", delete=False) as tmp:
            rgb_image.save(tmp.name, format="PNG")
            return tmp.name, tmp.name


# ---------------------------------------------------------------------------
# EMBED
# ---------------------------------------------------------------------------

def cmd_embed(carrier_path: str, payload_path: str, output_path: str) -> None:
    """
    Hide base64-encoded binary data into a carrier image using LSB.

    The stegano library only accepts string payloads, so the binary
    encrypted chunk from PHP is pre-encoded as base64 before being passed
    to this script and post-decoded on extraction.
    
    Payload is read from a temporary file instead of command-line argument
    to avoid Windows command line length limits.
    """
    try:
        from stegano import lsb
        temp_carrier_path = None

        if not os.path.isfile(carrier_path):
            _err(f"Carrier file not found: {carrier_path}")
            return

        if not os.path.isfile(payload_path):
            _err(f"Payload file not found: {payload_path}")
            return

        # Read payload from file
        with open(payload_path, 'r', encoding='utf-8') as f:
            b64_payload = f.read().strip()

        # Validate base64 input
        try:
            base64.b64decode(b64_payload, validate=True)
        except Exception:
            _err("Payload is not valid base64")
            return

        # Force RGB before embedding to prevent stegano from asking interactive
        # conversion questions on palette/other non-RGB modes.
        prepared_carrier_path, temp_carrier_path = _prepare_rgb_carrier(carrier_path)

        # stegano.lsb.hide() expects a string message
        secret_image = lsb.hide(prepared_carrier_path, b64_payload)

        # Ensure the output directory exists
        out_dir = os.path.dirname(output_path)
        if out_dir:
            os.makedirs(out_dir, exist_ok=True)

        secret_image.save(output_path)

        _ok(output_path)

    except Exception as exc:
        _err(str(exc))
    finally:
        if temp_carrier_path and os.path.exists(temp_carrier_path):
            try:
                os.remove(temp_carrier_path)
            except OSError:
                pass


# ---------------------------------------------------------------------------
# EXTRACT
# ---------------------------------------------------------------------------

def cmd_extract(stego_path: str) -> None:
    """
    Reveal the hidden base64-encoded payload from a stego image.
    Returns the raw base64 string — PHP decodes it back to binary.
    """
    try:
        from stegano import lsb

        if not os.path.isfile(stego_path):
            _err(f"Stego image not found: {stego_path}")
            return

        message = lsb.reveal(stego_path)

        if message is None:
            _err("No hidden data found in image. File may not have been encoded with StegoLock.")
            return

        # Validate we got valid base64 back (sanity check)
        try:
            base64.b64decode(message, validate=True)
        except Exception:
            _err("Extracted payload is not valid base64. Image may be corrupt or encoded with a different tool.")
            return

        _ok(message)

    except Exception as exc:
        _err(str(exc))


# ---------------------------------------------------------------------------
# CAPACITY
# ---------------------------------------------------------------------------

def cmd_capacity(image_path: str) -> None:
    """
    Calculate the maximum payload capacity (in bytes) of an image.

    Formula mirrors the PHP StegoService:
      capacity = (width * height * 3 channels * 1 bit/channel) / 8 bits - 4 bytes header
    Then divided by 4/3 to account for base64 overhead (binary → base64 inflates by ~33%).
    """
    try:
        from PIL import Image

        if not os.path.isfile(image_path):
            _err(f"Image not found: {image_path}")
            return

        with Image.open(image_path) as img:
            width, height = img.size

        # Raw LSB capacity in bytes
        raw_capacity = (width * height * 3) // 8 - 4

        # Adjust for base64 overhead: base64 encoding inflates size by 4/3
        # So usable binary bytes = raw_capacity * 3 / 4
        usable_capacity = int(raw_capacity * 3 / 4)

        _ok(max(0, usable_capacity))

    except Exception as exc:
        _err(str(exc))


# ---------------------------------------------------------------------------
# PSNR  (W2-T07 / W2-T12)
# ---------------------------------------------------------------------------

def cmd_psnr(original_path: str, stego_path: str) -> None:
    """
    Calculate the Peak Signal-to-Noise Ratio between the original carrier and
    the stego image to quantify the visual quality impact of LSB embedding.

    PSNR >= 40 dB is the accepted threshold for imperceptible modifications.
    Uses OpenCV's cv2.PSNR() which computes 10 * log10(MAX_I^2 / MSE).

    Returns JSON:
      { "psnr": <float>, "threshold_40db": <bool>, "quality": "good"|"poor" }
    """
    try:
        import cv2

        for label, path in [("Original", original_path), ("Stego", stego_path)]:
            if not os.path.isfile(path):
                _err(f"{label} image not found: {path}")
                return

        original = cv2.imread(original_path)
        stego    = cv2.imread(stego_path)

        if original is None:
            _err(f"Could not decode original image: {original_path}")
            return
        if stego is None:
            _err(f"Could not decode stego image: {stego_path}")
            return

        # Images may differ in size when a JPEG carrier was embedded and
        # saved as PNG (format conversion can alter reported dimensions).
        if original.shape != stego.shape:
            stego = cv2.resize(stego, (original.shape[1], original.shape[0]))

        psnr_value = cv2.PSNR(original, stego)

        _ok({
            "psnr":           round(psnr_value, 4),
            "threshold_40db": psnr_value >= 40.0,
            "quality":        "good" if psnr_value >= 40.0 else "poor",
        })

    except Exception as exc:
        _err(str(exc))


# ---------------------------------------------------------------------------
# Entry Point
# ---------------------------------------------------------------------------

if __name__ == "__main__":
    if len(sys.argv) < 2:
        _err("Usage: stego_lsb.py <embed|extract|capacity> [args...]")
        sys.exit(1)

    command = sys.argv[1].lower()

    if command == "embed":
        if len(sys.argv) != 5:
            _err("Usage: stego_lsb.py embed <carrier_path> <payload_file> <output_path>")
            sys.exit(1)
        cmd_embed(sys.argv[2], sys.argv[3], sys.argv[4])

    elif command == "extract":
        if len(sys.argv) != 3:
            _err("Usage: stego_lsb.py extract <stego_path>")
            sys.exit(1)
        cmd_extract(sys.argv[2])

    elif command == "capacity":
        if len(sys.argv) != 3:
            _err("Usage: stego_lsb.py capacity <image_path>")
            sys.exit(1)
        cmd_capacity(sys.argv[2])

    elif command == "psnr":
        if len(sys.argv) != 4:
            _err("Usage: stego_lsb.py psnr <original_path> <stego_path>")
            sys.exit(1)
        cmd_psnr(sys.argv[2], sys.argv[3])

    else:
        _err(f"Unknown command: {command}. Use embed, extract, capacity, or psnr.")
        sys.exit(1)
