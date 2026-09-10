<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; 

class Reimbursement extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'amount',
        'transaction_date',
        'merchant_name',
        'receipt_image_url',
        'status',
    ];

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}
