<?php

namespace App\Http\Controllers;

use App\Http\Services\Concerns\AnalyzesWithGemini;
use Illuminate\Http\Request;
use App\Models\Reimbursement;
use App\Http\Services\ReimbursementService;
use Exception;
use Storage;

class ReimbursementController extends Controller
{
    //

    protected $reimbursementService;

    public function __construct(ReimbursementService $reimbursementService)
    {
        $this->reimbursementService = $reimbursementService;
    }

    public function analyze(Request $request)
    {
        $request->validate([
            'receipt_image' => 'required|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        $file = $request->file('receipt_image');

        $savedPath = $file->store('receipts', 'public');
        $absolutePath = storage_path('app/public/' . $savedPath);

        try {
            $extractedData = $this->reimbursementService->analyzeReceipt($absolutePath, $file->getClientMimeType());
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
            'message' => 'Struk berhasil diproses oleh AI',
            'data' => [
                'amount' => $extractedData['amount'] ?? null,
                'date' => $extractedData['transaction_date'] ?? null,
                'merchant_name' => $extractedData['merchant_name'] ?? null,
                'receipt_number' => $extractedData['receipt_number'] ?? null,
                'temp_image_path' => $savedPath,
            ]
        ], 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'receipt_image' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'amount'        => 'required|numeric',
            'date'          => 'required|date',
            'merchant_name' => 'required|string',
            'description'   => 'required|string',
            'receipt_number' => 'nullable|string',
        ]);

        $verificationNotes = [];

        if ($request->receipt_number) {
            $isDuplicate = Reimbursement::where('user_id', $request->user()->id)
                ->where('receipt_number', $request->receipt_number)
                ->exists();

            if ($isDuplicate) {
                $verificationNotes[] = "Nomor struk '{$request->receipt_number}' sudah pernah diajukan sebelumnya — cek kemungkinan duplikat";
            }
        }

        $file = $request->file('receipt_image');
        $savedPath = $file->store('receipts', 'public');

        $reimbursement = Reimbursement::create([
            'user_id'           => $request->user()->id,
            'amount'            => $request->amount,
            'transaction_date'  => $request->date,
            'merchant_name'     => $request->merchant_name,
            'description'       => $request->description,
            'receipt_image_url' => $savedPath,
            'receipt_number'    => $request->receipt_number,
            'verification_notes' => !empty($verificationNotes) ? json_encode($verificationNotes) : null,
            'status'            => 'pending',
        ]);

        return response()->json([
            'message' => 'Reimbursement berhasil diajukan',
            'data'    => $reimbursement
        ], 201);
    }

    public function index(Request $request)
    {
        $data = $this->reimbursementService->getReimbursements($request->user());
        return response()->json(['data' => $data]);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected'
        ]);

        try {
            $result = $this->reimbursementService->updateStatus($id, $request->status, $request->user());

            return response()->json([
                'message' => 'Status berhasil diperbarui',
                'data' => $result
            ]);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 ? $e->getCode() : 500;
            return response()->json(['message' => $e->getMessage()], $code);
        }
    }
}
