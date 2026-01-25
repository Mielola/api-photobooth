<?php

namespace App\Http\Controllers;

use App\Models\acara;
use App\Models\session;
use App\Models\frame;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class SessionController extends Controller
{
    /**
     * Buat session tanpa email (client langsung mulai photobooth)
     */
    public function create(Request $request)
    {
        DB::beginTransaction();

        try {
            $validatedData = $request->validate([
                'acara_uid' => 'required|exists:table_acara,uid',
            ]);

            // Cari acara berdasarkan UID
            $acara = acara::where('uid', $validatedData['acara_uid'])->first();

            if (!$acara) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acara tidak ditemukan',
                ], 404);
            }

            // Cek apakah acara aktif
            if (!$acara->status) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acara belum aktif atau sudah berakhir',
                ], 403);
            }

            // Set durasi session (10 menit) - PERBAIKI INI
            $durasi = 10; // 10 menit, bukan 100
            $expired_time = Carbon::now()->addMinutes($durasi);

            $session = session::create([
                'acara_id' => $acara->id,
                'email' => null,
                'expired_time' => $expired_time,
            ]);

            // Load frames yang tersedia untuk acara ini
            $frames = frame::where('acara_id', $acara->id)->get();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Session berhasil dibuat',
                'data' => [
                    'session_uid' => $session->uid,
                    'acara' => [
                        'uid' => $acara->uid,
                        'nama_acara' => $acara->nama_acara,
                        'nama_pengantin' => $acara->nama_pengantin,
                        'tanggal' => $acara->tanggal,
                        'background' => $acara->background ? asset('storage/' . $acara->background) : null,
                    ],
                    'frames' => $frames->map(function ($frame) {
                        return [
                            'uid' => $frame->uid,
                            'nama_frame' => $frame->nama_frame,
                            'jumlah_foto' => $frame->jumlah_foto,
                            'photo' => $frame->photo ? asset('storage/' . $frame->photo) : null,
                        ];
                    }),
                    'expired_time' => $expired_time->toIso8601String(), // ISO format
                    'expired_timestamp' => $expired_time->timestamp * 1000, // Timestamp in milliseconds
                    'waktu_tersisa_menit' => $durasi,
                ],
            ], 201);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Session gagal dibuat',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update email session (dipanggil saat user klik tombol cetak dan input email)
     * Email hanya disimpan di database untuk data kontak, tidak dikirim email
     */
    public function updateEmail(Request $request, $uid)
    {
        try {
            DB::beginTransaction();

            $session = session::where('uid', $uid)->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session tidak ditemukan',
                ], 404);
            }

            $validatedData = $request->validate([
                'email' => 'required|email|max:255',
            ]);

            $session->update([
                'email' => $validatedData['email'],
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Email berhasil disimpan',
                'data' => [
                    'session_uid' => $session->uid,
                    'email' => $session->email,
                ],
            ], 200);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Update email gagal',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate QR Code untuk download (setelah email diinput)
     */
    public function generateDownloadQR($uid)
    {
        $session = session::with('acara')->where('uid', $uid)->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Session tidak ditemukan',
            ], 404);
        }

        if (empty($session->email)) {
            return response()->json([
                'success' => false,
                'message' => 'Email belum diinput, silakan input email terlebih dahulu',
            ], 400);
        }

        // URL untuk download foto
        $downloadUrl = url('/download/' . $session->uid);

        return response()->json([
            'success' => true,
            'message' => 'QR Code berhasil digenerate',
            'data' => [
                'session_uid' => $session->uid,
                'email' => $session->email,
                'download_url' => $downloadUrl,
                'qr_data' => $downloadUrl, // Client akan generate QR code dari URL ini
            ],
        ], 200);
    }

    /**
     * Get detail session beserta frames yang tersedia
     */
    public function show($uid)
    {
        $session = session::with(['acara'])->where('uid', $uid)->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Session tidak ditemukan',
            ], 404);
        }

        // Cek status aktif session
        $now = Carbon::now();
        $expired = Carbon::parse($session->expired_time);
        $is_active = $now->lessThan($expired);
        $waktu_tersisa = $is_active ? $now->diffInMinutes($expired) : 0;

        if (!$is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Session sudah kadaluarsa',
            ], 403);
        }

        // Load frames yang tersedia untuk acara ini
        $frames = frame::where('acara_id', $session->acara_id)->get();

        return response()->json([
            'success' => true,
            'message' => 'Data Session berhasil ditemukan',
            'data' => [
                'session_uid' => $session->uid,
                'email' => $session->email,
                'has_email' => !empty($session->email),
                'acara' => [
                    'uid' => $session->acara->uid,
                    'nama_acara' => $session->acara->nama_acara,
                    'nama_pengantin' => $session->acara->nama_pengantin,
                    'tanggal' => $session->acara->tanggal,
                    'background' => $session->acara->background ? asset('storage/' . $session->acara->background) : null,
                ],
                'frames' => $frames->map(function ($frame) {
                    return [
                        'uid' => $frame->uid,
                        'nama_frame' => $frame->nama_frame,
                        'jumlah_foto' => $frame->jumlah_foto,
                        'photo' => $frame->photo ? asset('storage/' . $frame->photo) : null,
                    ];
                }),
                'is_active' => $is_active,
                'waktu_tersisa_menit' => $waktu_tersisa,
                'expired_time' => $session->expired_time,
            ],
        ], 200);
    }

    /**
     * Cek status aktif session
     */
    public function checkActive($uid)
    {
        $session = session::where('uid', $uid)->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Session tidak ditemukan',
            ], 404);
        }

        $now = Carbon::now();
        $expired = Carbon::parse($session->expired_time);
        $is_active = $now->lessThan($expired);
        $waktu_tersisa = $is_active ? $now->diffInMinutes($expired) : 0;

        return response()->json([
            'success' => true,
            'message' => $is_active ? 'Session masih aktif' : 'Session sudah kadaluarsa',
            'data' => [
                'is_active' => $is_active,
                'waktu_tersisa_menit' => $waktu_tersisa,
                'waktu_tersisa_detik' => $is_active ? $now->diffInSeconds($expired) : 0,
                'expired_time' => $session->expired_time,
            ],
        ], 200);
    }

    /**
     * Perpanjang waktu session
     */
    public function extend($uid)
    {
        try {
            DB::beginTransaction();

            $session = session::where('uid', $uid)->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session tidak ditemukan',
                ], 404);
            }

            // Perpanjang 10 menit lagi
            $durasi = 10;
            $expired_time = Carbon::now()->addMinutes($durasi);

            $session->update([
                'expired_time' => $expired_time,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Session berhasil diperpanjang',
                'data' => [
                    'expired_time' => $expired_time->format('Y-m-d H:i:s'),
                    'waktu_tersisa_menit' => $durasi,
                ],
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperpanjang session',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List semua session (untuk admin)
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 10);
        $query = session::with('acara');

        $sessions = $query->orderByDesc('created_at')->paginate($perPage);

        // Tambahkan informasi status aktif pada setiap session
        $sessions->getCollection()->transform(function ($session) {
            $now = Carbon::now();
            $expired = Carbon::parse($session->expired_time);
            $is_active = $now->lessThan($expired);
            $waktu_tersisa = $is_active ? $now->diffInMinutes($expired) : 0;

            $session->is_active = $is_active;
            $session->waktu_tersisa_menit = $waktu_tersisa;
            $session->has_email = !empty($session->email);

            return $session;
        });

        return response()->json([
            'success' => true,
            'message' => 'Data Session berhasil ditemukan',
            'data' => $sessions,
        ], 200);
    }

    /**
     * Update session (untuk admin)
     */
    public function update(Request $request, $uid)
    {
        try {
            DB::beginTransaction();

            $session = session::where('uid', $uid)->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session tidak ditemukan',
                ], 404);
            }

            $validatedData = $request->validate([
                'email' => 'sometimes|nullable|email|max:255',
                'expired_time' => 'sometimes|required|date',
            ]);

            $session->update($validatedData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Session berhasil diupdate',
                'data' => $session,
            ], 200);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Update failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Hapus session (untuk admin)
     */
    public function delete($uid)
    {
        $session = session::where('uid', $uid)->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Session tidak ditemukan',
            ], 404);
        }

        $session->delete();

        return response()->json([
            'success' => true,
            'message' => 'Session berhasil dihapus',
        ], 200);
    }
}
