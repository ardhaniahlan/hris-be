<?php

namespace App\Http\Controllers;

use App\Http\Services\Concerns\AnalyzesWithGemini;
use App\Http\Services\LeaveService;
use App\Models\Leave;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Storage;

class LeaveController extends Controller
{
    //
    protected $leaveService;

    public function __construct(LeaveService $leaveService)
    {
        $this->leaveService = $leaveService;
    }

    public function index(Request $request)
    {
        $data = $this->leaveService->getLeaves($request->user());
        return response()->json(['data' => $data]);
    }

    public function analyze(Request $request)
{
    $request->validate([
        'medical_certificate' => 'required|image|mimes:jpeg,png,jpg|max:5120',
    ]);

    $file = $request->file('medical_certificate');
    $savedPath = $file->store('leaves_temp', 'public');
    $absolutePath = storage_path('app/public/' . $savedPath);

    try {
        $extractedData = $this->leaveService->analyzeMedicalCertificate($absolutePath, $file->getClientMimeType());
    } catch (\Exception $e) {
        if ($e->getMessage() === 'AI_QUOTA_EXCEEDED') {
            Storage::disk('public')->delete($savedPath);
            return response()->json([
                'message' => 'Layanan AI sedang sibuk (kuota harian habis). Silakan coba lagi nanti atau isi data secara manual.'
            ], 503);
        }
        throw $e;
    }

    return response()->json([
        'message' => 'Surat dokter berhasil diproses',
        'data' => [
            'hospital_name' => $extractedData['hospital_name'] ?? null,
            'certificate_date' => $extractedData['certificate_date'] ?? null,
            'patient_name' => $extractedData['patient_name'] ?? null,         
            'rest_duration_days' => $extractedData['rest_duration_days'] ?? null, 
            'medical_certificate_url' => $savedPath,
        ]
    ], 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'leave_type' => 'required|in:annual,sick,marriage,maternity',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string',
            'hospital_name' => 'nullable|string',
            'certificate_date' => 'nullable|date',
            'patient_name' => 'nullable|string',              
            'rest_duration_days' => 'nullable|integer|min:1',  
            'medical_certificate' => 'required_if:leave_type,sick|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        $hasPendingLeave = Leave::where('user_id', $request->user()->id)
                                ->where('status', 'pending')
                                ->exists();

        if ($hasPendingLeave) {
            return response()->json([
                'message' => 'Anda masih memiliki pengajuan cuti yang sedang menunggu persetujuan (pending). Harap tunggu hingga diproses.'
            ], 400);
        }

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $daysRequested = $startDate->diffInDays($endDate) + 1;
        $user = $request->user();

        if ($request->leave_type === 'annual') {
            if ($user->leave_balance <= 0) {
                return response()->json([
                    'message' => 'Mohon maaf, sisa jatah cuti tahunan Anda sudah habis (0 hari).'
                ], 400);
            }
            if ($daysRequested > $user->leave_balance) {
                return response()->json([
                    'message' => "Pengajuan ditolak. Anda meminta {$daysRequested} hari, tetapi sisa jatah cuti Anda hanya tersisa {$user->leave_balance} hari."
                ], 400);
            }
        }

        $verificationNotes = [];

        if ($request->leave_type === 'sick') {
            if ($request->patient_name) {
                $nameMatch = stripos($user->name, $request->patient_name) !== false
                        || stripos($request->patient_name, $user->name) !== false;

                if (!$nameMatch) {
                    $verificationNotes[] = "Nama di surat ('{$request->patient_name}') berbeda dengan nama karyawan ('{$user->name}')";
                }
            }

            if ($request->rest_duration_days && $daysRequested > $request->rest_duration_days) {
                $verificationNotes[] = "Durasi cuti diajukan ({$daysRequested} hari) lebih dari rekomendasi dokter ({$request->rest_duration_days} hari)";
            }
        }

        $savedPath = null;
        if ($request->leave_type === 'sick' && $request->hasFile('medical_certificate')) {
            $file = $request->file('medical_certificate');
            $savedPath = $file->store('leaves', 'public');
        }

        $leave = Leave::create([
            'user_id' => $user->id,
            'leave_type' => $request->leave_type,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'reason' => $request->reason,
            'medical_certificate_url' => $savedPath,
            'hospital_name' => $request->hospital_name,
            'certificate_date' => $request->certificate_date,
            'patient_name' => $request->patient_name,
            'rest_duration_days' => $request->rest_duration_days,
            'verification_notes' => !empty($verificationNotes) ? json_encode($verificationNotes) : null,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Pengajuan cuti berhasil dikirim',
            'data' => $leave,
        ], 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected'
        ]);

        try {
            $result = $this->leaveService->updateStatus($id, $request->status, $request->user());
            
            return response()->json([
                'message' => 'Status cuti berhasil diperbarui',
                'data' => $result
            ]);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 ? $e->getCode() : 500;
            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    public function cancel(Request $request, $id)
    {
        try {
            $result = $this->leaveService->cancelLeave($id, $request->user());
            
            return response()->json([
                'message' => 'Pengajuan cuti berhasil dibatalkan',
                'data' => $result
            ]);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 ? $e->getCode() : 500;
            return response()->json(['message' => $e->getMessage()], $code);
        }
    }
}
