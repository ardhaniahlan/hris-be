<?php

namespace App\Http\Services;

use App\Http\Services\Concerns\AnalyzesWithGemini;
use App\Models\Leave;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class LeaveService
{
    use AnalyzesWithGemini;

    protected $apiKey;
    protected $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->apiUrl = config('services.gemini.api_url');
    }

    public function analyzeMedicalCertificate(string $imagePath, string $mimeType): ?array
    {
        $prompt = 'Anda adalah sistem verifikasi HRD profesional. Analisis gambar Surat Keterangan Sakit / Medical Certificate ini.
        Kembalikan HANYA dalam format objek JSON valid tanpa markdown tambahan.
        Struktur JSON yang diminta:
        {
            "hospital_name": "Nama rumah sakit, klinik, puskesmas, atau praktik dokter yang menerbitkan surat ini. Jika tidak terbaca, isi null.",
            "certificate_date": "Tanggal surat ini DITERBITKAN/DIBUAT oleh dokter, dalam format YYYY-MM-DD. Jika tidak terbaca, isi null.",
            "patient_name": "Nama pasien/karyawan yang tertulis di surat ini. Jika tidak terbaca, isi null.",
            "rest_duration_days": "Jumlah hari istirahat yang direkomendasikan dokter, HANYA angka (contoh: 3). Jika tidak disebutkan angka pasti, isi null."
        }
        Jika gambar bukan surat keterangan sakit sama sekali, isi semua field dengan null.';

        return $this->callGeminiVision($imagePath, $mimeType, $prompt, 'Leave');
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