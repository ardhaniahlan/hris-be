<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Reimbursement;
use App\Http\Services\ReimbursementAiService;

class ReimbursementController extends Controller
{
    //

    protected $aiService;

    public function __construct(ReimbursementAiService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function store(Request $request)
    {
        $request->validate([
            'receipt_image' => 'required|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        $file = $request->file('receipt_image');
        $savedPath = $file->store('receipts', 'public');
        
        $absolutePath = storage_path('app/public/' . $savedPath);

        $extractedData = $this->aiService->analyzeReceipt($absolutePath, $file->getClientMimeType());

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
}
