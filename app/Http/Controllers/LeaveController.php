<?php

namespace App\Http\Controllers;

use App\Http\Services\LeaveService;
use App\Models\Leave;
use Exception;
use Illuminate\Http\Request;

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

    public function store(Request $request)
    {
        $request->validate([
            'leave_type' => 'required|in:annual,sick',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string',
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

        $hospitalName = null;
        $savedPath = null;
        $aiRawData = null;

        if ($request->leave_type === 'sick' && $request->hasFile('medical_certificate')) {
            $file = $request->file('medical_certificate');
            $savedPath = $file->store('leaves', 'public');
            $absolutePath = storage_path('app/public/' . $savedPath);

            $extractedData = $this->leaveService->analyzeMedicalCertificate($absolutePath, $file->getClientMimeType());

            if (!$extractedData) {
                return response()->json(['message' => 'AI gagal menganalisis gambar. Pastikan surat dokter terbaca jelas.'], 422);
            }

            $hospitalName = $extractedData['hospital_name'] ?? null;
            $aiRawData = $extractedData;
        }

        $leave = Leave::create([
            'user_id' => $request->user()->id,
            'leave_type' => $request->leave_type,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'reason' => $request->reason,
            'medical_certificate_url' => $savedPath,
            'hospital_name' => $hospitalName,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Pengajuan cuti berhasil dikirim',
            'data' => $leave,
            'ai_raw_data' => $aiRawData
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
