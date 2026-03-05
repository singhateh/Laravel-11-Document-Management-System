<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Widens stego_segments.segment_index from TINYINT UNSIGNED (max 255) to
 * SMALLINT UNSIGNED (max 65 535).
 *
 * A TINYINT caps at 255 segments per document. Large files (or low-capacity
 * carriers) could easily exceed this. SMALLINT UNSIGNED (65 535) is sufficient
 * for any realistic segmentation count while adding minimal overhead.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE stego_segments MODIFY segment_index SMALLINT UNSIGNED NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE stego_segments MODIFY segment_index TINYINT UNSIGNED NOT NULL');
    }
};
