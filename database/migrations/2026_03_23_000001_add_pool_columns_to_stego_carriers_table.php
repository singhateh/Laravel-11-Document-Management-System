<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add carrier pool management columns to stego_carriers table.
     * 
     * These columns enable:
     * - validation_status: Track whether carrier has been validated (pending/valid/invalid)
     * - validation_error: Store validation failure reason
     * - capacity_bytes: Store measured carrier capacity in bytes
     * - is_in_use: Lock carrier during active encode operations
     * - validated_at: Timestamp of validation completion
     */
    public function up(): void
    {
        Schema::table('stego_carriers', function (Blueprint $table) {
            $table->enum('validation_status', ['pending', 'valid', 'invalid'])
                  ->default('pending')
                  ->after('psnr')
                  ->comment('Carrier validation status: pending, valid, or invalid');
            
            $table->string('validation_error')->nullable()->after('validation_status')
                  ->comment('Validation failure reason if status is invalid');
            
            $table->unsignedBigInteger('capacity_bytes')->nullable()->after('validation_error')
                  ->comment('Measured carrier capacity in bytes');
            
            $table->boolean('is_in_use')->default(false)->after('capacity_bytes')
                  ->comment('Whether carrier is locked by an active encode operation');
            
            $table->timestamp('validated_at')->nullable()->after('is_in_use')
                  ->comment('Timestamp when validation completed');

            // Index for efficient pool queries
            $table->index(['uploaded_by', 'validation_status', 'is_in_use']);
        });
    }

    public function down(): void
    {
        Schema::table('stego_carriers', function (Blueprint $table) {
            $table->dropIndex(['uploaded_by', 'validation_status', 'is_in_use']);
            $table->dropColumn([
                'validation_status',
                'validation_error',
                'capacity_bytes',
                'is_in_use',
                'validated_at'
            ]);
        });
    }
};
