<?php

namespace App\Http\Services;

use App\Http\Services\Concerns\AnalyzesWithGemini;
use App\Models\Reimbursement;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ReimbursementService
{
    use AnalyzesWithGemini;

    protected $apiKey;
    protected $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->apiUrl = config('services.gemini.api_url');
    }

    public function analyzeReceipt(string $imagePath, string $mimeType): ?array
    {
        $prompt = 'Anda adalah asisten akuntansi profesional. Analisis gambar struk ini. Ekstrak informasi berikut dan kembalikan HANYA dalam format objek JSON valid tanpa markdown tambahan:
        {
            "amount": "Total nilai transaksi, HANYA angka tanpa simbol mata uang atau titik/koma ribuan.",
            "transaction_date": "Tanggal transaksi dalam format YYYY-MM-DD. Jika tidak ada, isi null.",
            "merchant_name": "Nama toko atau merchant tempat transaksi terjadi.",
            "receipt_number": "Nomor struk atau nomor invoice/referensi transaksi, jika tercetak di struk. Jika tidak ada, isi null."
        }';

        return $this->callGeminiVision($imagePath, $mimeType, $prompt, 'Reimbursement');
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