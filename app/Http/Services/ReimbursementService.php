<?php

namespace App\Http\Services;

use App\Models\Reimbursement;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ReimbursementService
{
    protected $apiKey;
    protected $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent';
    }

    public function analyzeReceipt(string $imagePath, string $mimeType): ?array
    {
        $base64Image = base64_encode(file_get_contents($imagePath));

        $prompt = 'Anda adalah asisten akuntansi profesional. Analisis gambar struk ini. Ekstrak informasi berikut dan kembalikan HANYA dalam format objek JSON valid tanpa markdown tambahan:
        1. "amount": Total nilai transaksi (hanya angka, tanpa simbol mata uang atau titik/koma ribuan).
        2. "transaction_date": Tanggal transaksi dalam format YYYY-MM-DD. Jika tidak ada, isi null.
        3. "merchant_name": Nama toko atau merchant tempat transaksi terjadi.';

        try {
            $response = Http::post($this->apiUrl . '?key=' . $this->apiKey, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => $base64Image
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

            if ($response->successful()) {
                $result = $response->json();
                
                $textResponse = $result['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
                
                $textResponse = str_replace(['```json', '```'], '', $textResponse);
                
                return json_decode(trim($textResponse), true);
            }

            Log::error('Gemini API Error: ' . $response->body());
            return null;

        } catch (Exception $e) {
            Log::error('Gemini Exception: ' . $e->getMessage());
            return null;
        }
    }

    public function getReimbursements(User $user)
    {
        if (in_array($user->role, ['admin_finance', 'admin_hr'])) {
            return Reimbursement::with('user:id,name,email')->orderBy('created_at', 'desc')->get();
        }

        return Reimbursement::where('user_id', $user->id)->orderBy('created_at', 'desc')->get();
    }

    public function updateStatus(int $id, string $status, User $admin)
    {
        if (!in_array($admin->role, ['admin_finance', 'admin_hr'])) {
            throw new Exception('Akses ditolak. Hanya Admin yang dapat mengubah status.', 403);
        }

        $reimbursement = Reimbursement::find($id);

        if (!$reimbursement) {
            throw new Exception('Data reimbursement tidak ditemukan.', 404);
        }

        $reimbursement->status = $status;
        $reimbursement->save();

        return $reimbursement;
    }
}