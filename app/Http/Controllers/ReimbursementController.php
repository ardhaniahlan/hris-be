<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Reimbursement;
use App\Http\Services\ReimbursementService;
use Exception;

class ReimbursementController extends Controller
{
    //

    protected $reimbursementService;

    public function __construct(ReimbursementService $reimbursementService)
    {
        $this->reimbursementService = $reimbursementService;
    }

    public function store(Request $request)
    {
        $request->validate([
            'receipt_image' => 'required|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        $file = $request->file('receipt_image');
        $savedPath = $file->store('receipts', 'public');
        
        $absolutePath = storage_path('app/public/' . $savedPath);

        $extractedData = $this->reimbursementService->analyzeReceipt($absolutePath, $file->getClientMimeType());

        if (!$extractedData) {
            return response()->json([
                'message' => 'AI gagal menganalisis gambar. Pastikan Anda mengunggah struk yang jelas.',
            ], 422);
        }

        $reimbursement = Reimbursement::create([
            'user_id' => $request->user()->id,
            'amount' => $extractedData['amount'] ?? null,
            'transaction_date' => $extractedData['transaction_date'] ?? null,
            'merchant_name' => $extractedData['merchant_name'] ?? null,
            'receipt_image_url' => $savedPath,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Struk berhasil diproses oleh AI',
            'data' => $reimbursement,
            'ai_raw_data' => $extractedData 
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
