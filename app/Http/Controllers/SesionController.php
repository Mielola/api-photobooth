<?php

namespace App\Http\Controllers;

use App\Models\acara;
use App\Models\session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class SesionController extends Controller
{
    //
    public function create(Request $request)
    {

        try {
            DB::beginTransaction();

            $validation = $request->validate([
                'acara_uid' => 'required|string',
            ]);
            //cari acara
            $acara = acara::where('uid', $validation['acara_uid'])->first();

            if (!$acara) {
                DB::rollBack();
                return response()->json(['message' => 'Acara tidak ditemukan', 'error' => 'Acara dengan UID tersebut tidak ditemukan'], 404);
            }

            //cek aktif
            if (!$acara->status) {
                DB::rollBack();
                return response()->json(['message' => 'Acara belum aktif', 'error' => 'Acara belum diaktifkan atau sudah berakhir'], 403);
            }

            $durasi = 10;
            $expired_time = Carbon::now()->addMinutes($durasi);

            $session = session::create([
                'acara_id' => $acara->id,
                'expired_time' => $expired_time,
            ]);

            DB::commit();
            return response()->json([
                'message' => 'Session berhasil dibuat',
                'data' => [
                    'session' => $session,
                    'acara' => $acara,
                    'expired_at' => $expired_time->format('Y-m-d H:i:s'),
                    'waktu_tersisa_menit' => $durasi
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal membuat session', 'error' => $e->getMessage()], 500);
        }
    }
}
