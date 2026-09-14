<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; 

class Reimbursement extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'amount', 'transaction_date', 'merchant_name', 'description',
        'receipt_image_url', 'receipt_number', 'verification_notes', 'status',
    ];

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}
