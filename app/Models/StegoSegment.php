<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StegoSegment extends Model
{
    use HasFactory;

    protected $fillable = [
        'stego_document_id',
        'stego_carrier_id',
        'segment_index',
        'encrypted_chunk',
        's3_key',
        'chunk_hash',
    ];

    protected function casts(): array
    {
        return [
            'segment_index' => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function stegoDocument()
    {
        return $this->belongsTo(StegoDocument::class);
    }

    public function carrier()
    {
        return $this->belongsTo(StegoCarrier::class, 'stego_carrier_id');
    }
}
