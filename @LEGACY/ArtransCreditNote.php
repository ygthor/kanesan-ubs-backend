<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArtransCreditNote extends BaseModel
{
    use HasFactory;

    protected $table = 'artrans_credit_note';

    protected $fillable = [
        'invoice_id',
        'credit_note_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Get the invoice (INV) that this credit note is linked to.
     */
    public function invoice()
    {
        return $this->belongsTo(Artran::class, 'invoice_id', 'artrans_id');
    }

    /**
     * Get the credit note (CN) linked to the invoice.
     */
    public function creditNote()
    {
        return $this->belongsTo(Artran::class, 'credit_note_id', 'artrans_id');
    }
}
