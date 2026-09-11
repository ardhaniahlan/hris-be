<?php

namespace App\Http\Services;

use App\Models\Leave;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class LeaveService
{
    protected $apiKey;
    protected $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->apiUrl = config('services.gemini.api_url');
    }

    public function analyzeMedicalCertificate(string $imagePath, string $mimeType): ?array
    {
        $base64Image = base64_encode(file_get_contents($imagePath));

        $prompt = 'Anda adalah sistem verifikasi HRD profesional. Analisis gambar Surat Keterangan Sakit / Medical Certificate ini. 
        Temukan nama instansi kesehatannya dan kembalikan HANYA dalam format objek JSON valid tanpa markdown tambahan.
        Struktur JSON yang diminta:
        {
            "hospital_name": "Nama rumah sakit, klinik, puskesmas, atau praktik dokter yang menerbitkan surat ini. Jika gambar bukan surat sakit atau nama tidak terbaca, isi dengan null."
        }';

        try {
            $response = Http::withoutVerifying()
                ->timeout(30)
                ->post($this->apiUrl . '?key=' . $this->apiKey, [
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
                
                $decoded = json_decode(trim($textResponse), true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::error('Gemini JSON Parse Error (Leave): ' . $textResponse);
                    return null;
                }
                
                return $decoded;
            }

            Log::error('Gemini API Error (Leave): ' . $response->body());
            return null;

        } catch (Exception $e) {
            Log::error('Gemini Exception (Leave): ' . $e->getMessage());
            return null;
        }
    }

    public function getLeaves(User $user)
    {
        if (in_array($user->role, ['admin_finance', 'admin_hr'])) {
            return Leave::with('user:id,name,email')->orderBy('created_at', 'desc')->get();
        }

        return Leave::where('user_id', $user->id)->orderBy('created_at', 'desc')->get();
    }

    public function updateStatus(int $id, string $status, User $admin)
    {
        if (!in_array($admin->role, ['admin_finance', 'admin_hr'])) {
            throw new Exception('Akses ditolak. Hanya Admin yang dapat mengubah status cuti.', 403);
        }

        $leave = Leave::find($id);

        if (!$leave) {
            throw new Exception('Data cuti tidak ditemukan.', 404);
        }

        $leave->status = $status;
        $leave->save();

        return $leave;
    }

    public function cancelLeave(int $id, User $user)
    {
        $leave = Leave::find($id);

        if (!$leave) {
            throw new Exception('Data cuti tidak ditemukan.', 404);
        }

        if ($leave->user_id !== $user->id) {
            throw new Exception('Akses ditolak. Anda hanya dapat membatalkan pengajuan Anda sendiri.', 403);
        }

        if ($leave->status !== 'pending') {
            throw new Exception('Pengajuan tidak dapat dibatalkan karena sudah diproses (approved/rejected).', 400);
        }

        $leave->status = 'cancelled';
        $leave->save();

        return $leave;
    }
}