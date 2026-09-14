<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Leave extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'leave_type', 'start_date', 'end_date', 'reason',
        'medical_certificate_url', 'hospital_name', 'certificate_date',
        'patient_name', 'rest_duration_days', 'verification_notes', 'status',
    ];

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    protected static function booted()
    {
        static::updated(function ($leave) {
            
            if ($leave->isDirty('status') && $leave->status === 'approved' && $leave->getOriginal('status') === 'pending') {
                
                $startDate = Carbon::parse($leave->start_date);
                $endDate = Carbon::parse($leave->end_date);
                $leave->start_date = $startDate->toDateString();
                $daysTaken = $startDate->diffInDays($endDate) + 1;

                $user = $leave->user;

                if ($user) {
                    $user->leave_balance = $user->leave_balance - $daysTaken;
                    $user->save();
                }
            }
        });
    }
}
