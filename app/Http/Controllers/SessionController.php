<?php

namespace App\Http\Controllers;

use App\Models\acara;
use App\Models\session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class SessionController extends Controller
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

    //cek session aktif
    public function checkActive($uid)
    {
        try {
            $session = session::where('uid', $uid)->first();

            if (!$session) {
                return response()->json(['message' => 'Session tidak ditemukan', 'error' => 'Session dengan UID tersebut tidak ada'], 404);
            }

            $now = Carbon::now();
            $expired = Carbon::parse($session->expired_time);
            $is_active = $now->lessThan($expired);
            $waktu_tersisa = $is_active ? $now->diffInMinutes($expired) : 0;

            return response()->json([
                'message' => $is_active ? 'Session masih aktif' : 'Session sudah kadaluarsa',
                'data' => [
                    'is_active' => $is_active,
                    'waktu_tersisa_menit' => $waktu_tersisa,
                    'expired_time' => $session->expired_time
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal mengecek status session', 'error' => $e->getMessage()], 500);
        }
    }

    //hapus session
    public function delete($uid)
    {
        try {
            DB::beginTransaction();

            $session = session::where('uid', $uid)->first();

            if (!$session) {
                DB::rollBack();
                return response()->json(['message' => 'Session tidak ditemukan', 'error' => 'Session dengan UID tersebut tidak ada'], 404);
            }

            $session->delete();

            DB::commit();
            return response()->json(['message' => 'Session berhasil dihapus'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menghapus session', 'error' => $e->getMessage()], 500);
        }
    }

        public function index()
    {
        try {
            $sessions = session::with('acara')->orderBy('created_at', 'desc')->get();

            $sessions = $sessions->map(function($session) {
                $now = Carbon::now();
                $expired = Carbon::parse($session->expired_time);
                $is_active = $now->lessThan($expired);

                return [
                    'uid' => $session->uid,
                    'acara' => $session->acara,
                    'email' => $session->email,
                    'expired_time' => $session->expired_time,
                    'is_active' => $is_active,
                    'created_at' => $session->created_at
                ];
            });

            return response()->json([
                'message' => 'Daftar session berhasil diambil',
                'data' => $sessions
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal mengambil daftar session', 'error' => $e->getMessage()], 500);
        }
    }

}
