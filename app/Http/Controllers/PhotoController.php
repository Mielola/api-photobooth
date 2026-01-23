<?php

namespace App\Http\Controllers;

use App\Models\sessionPhoto;
use App\Models\session;
use App\Models\frame;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Illuminate\Support\Str;

class PhotoController extends Controller
{
    /**
     * Upload foto original dari camera
     */
    public function uploadOriginal(Request $request)
    {
        DB::beginTransaction();

        try {
            $validatedData = $request->validate([
                'session_uid' => 'required|string',
                'photo' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            ]);

            // Cari session dan validasi
            $session = session::where('uid', $validatedData['session_uid'])
                ->with('acara')
                ->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session tidak ditemukan',
                ], 404);
            }

            // Cek apakah session masih aktif
            $now = Carbon::now();
            $expired = Carbon::parse($session->expired_time);
            if ($now->greaterThanOrEqualTo($expired)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session sudah kadaluarsa',
                ], 403);
            }

            // Buat folder berdasarkan nama pengantin dan session
            $namaPengantin = Str::slug($session->acara->nama_pengantin, '_');
            $folderPath = 'photos/' . $namaPengantin . '/' . $session->uid;

            // Upload foto
            $file = $request->file('photo');
            $filename = 'original_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs($folderPath, $filename, 'public');

            $photo = sessionPhoto::create([
                'type' => 'original',
                'photo_path' => $path,
                'session_id' => $session->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Foto berhasil diupload',
                'data' => [
                    'uid' => $photo->uid,
                    'type' => $photo->type,
                    'url' => asset('storage/' . $path),
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
                'message' => 'Gagal mengupload foto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload foto yang sudah diaplikasikan frame (result)
     */
    public function uploadFramed(Request $request)
    {
        DB::beginTransaction();

        try {
            $validatedData = $request->validate([
                'session_uid' => 'required|string',
                'frame_uid' => 'required|string',
                'photo' => 'required|image|mimes:jpeg,png,jpg|max:10240',
            ]);

            // Validasi session
            $session = session::where('uid', $validatedData['session_uid'])
                ->with('acara')
                ->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session tidak ditemukan',
                ], 404);
            }

            // Validasi frame
            $frame = frame::where('uid', $validatedData['frame_uid'])->first();

            if (!$frame) {
                return response()->json([
                    'success' => false,
                    'message' => 'Frame tidak ditemukan',
                ], 404);
            }

            // Cek session aktif
            $now = Carbon::now();
            $expired = Carbon::parse($session->expired_time);
            if ($now->greaterThanOrEqualTo($expired)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session sudah kadaluarsa',
                ], 403);
            }

            // Upload foto hasil dengan frame
            $namaPengantin = Str::slug($session->acara->nama_pengantin, '_');
            $folderPath = 'photos/' . $namaPengantin . '/' . $session->uid;

            $file = $request->file('photo');
            $filename = 'framed_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs($folderPath, $filename, 'public');

            $photo = sessionPhoto::create([
                'type' => 'framed',
                'photo_path' => $path,
                'session_id' => $session->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Foto dengan frame berhasil diupload',
                'data' => [
                    'uid' => $photo->uid,
                    'type' => $photo->type,
                    'url' => asset('storage/' . $path),
                    'frame' => [
                        'uid' => $frame->uid,
                        'nama_frame' => $frame->nama_frame,
                    ],
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
                'message' => 'Gagal mengupload foto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get semua foto dalam satu session
     */
    public function getBySession($session_uid)
    {
        $session = session::where('uid', $session_uid)->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Session tidak ditemukan',
            ], 404);
        }

        $photos = sessionPhoto::where('session_id', $session->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($photo) {
                return [
                    'uid' => $photo->uid,
                    'type' => $photo->type,
                    'url' => asset('storage/' . $photo->photo_path),
                    'created_at' => $photo->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Daftar foto berhasil diambil',
            'data' => [
                'session_uid' => $session_uid,
                'total' => $photos->count(),
                'photos' => $photos,
            ],
        ], 200);
    }

    /**
     * Get foto berdasarkan tipe
     */
    public function getByType($session_uid, $type)
    {
        $session = session::where('uid', $session_uid)->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Session tidak ditemukan',
            ], 404);
        }

        $photos = sessionPhoto::where('session_id', $session->id)
            ->where('type', $type)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($photo) {
                return [
                    'uid' => $photo->uid,
                    'type' => $photo->type,
                    'url' => asset('storage/' . $photo->photo_path),
                    'created_at' => $photo->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => "Daftar foto {$type} berhasil diambil",
            'data' => [
                'session_uid' => $session_uid,
                'type' => $type,
                'total' => $photos->count(),
                'photos' => $photos,
            ],
        ], 200);
    }

    /**
     * Retake/Replace foto
     */
    public function retake(Request $request, $uid)
    {
        DB::beginTransaction();

        try {
            $validatedData = $request->validate([
                'photo' => 'required|image|mimes:jpeg,png,jpg|max:10240',
            ]);

            $oldPhoto = sessionPhoto::where('uid', $uid)->first();

            if (!$oldPhoto) {
                return response()->json([
                    'success' => false,
                    'message' => 'Foto tidak ditemukan',
                ], 404);
            }

            $session = session::with('acara')->find($oldPhoto->session_id);

            // Cek session aktif
            $now = Carbon::now();
            $expired = Carbon::parse($session->expired_time);
            if ($now->greaterThanOrEqualTo($expired)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session sudah kadaluarsa',
                ], 403);
            }

            // Hapus foto lama dari storage
            if (Storage::disk('public')->exists($oldPhoto->photo_path)) {
                Storage::disk('public')->delete($oldPhoto->photo_path);
            }

            // Upload foto baru
            $namaPengantin = Str::slug($session->acara->nama_pengantin, '_');
            $folderPath = 'photos/' . $namaPengantin . '/' . $session->uid;

            $file = $request->file('photo');
            $filename = $oldPhoto->type . '_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs($folderPath, $filename, 'public');

            $oldPhoto->update([
                'photo_path' => $path,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Foto berhasil di-retake',
                'data' => [
                    'uid' => $oldPhoto->uid,
                    'type' => $oldPhoto->type,
                    'url' => asset('storage/' . $path),
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
                'message' => 'Gagal melakukan retake foto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete foto
     */
    public function delete($uid)
    {
        DB::beginTransaction();

        try {
            $photo = sessionPhoto::where('uid', $uid)->first();

            if (!$photo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Foto tidak ditemukan',
                ], 404);
            }

            // Hapus dari storage
            if (Storage::disk('public')->exists($photo->photo_path)) {
                Storage::disk('public')->delete($photo->photo_path);
            }

            $photo->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Foto berhasil dihapus',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus foto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download single foto
     */
    public function download($uid)
    {
        $photo = sessionPhoto::where('uid', $uid)->first();

        if (!$photo) {
            return response()->json([
                'success' => false,
                'message' => 'Foto tidak ditemukan',
            ], 404);
        }

        $filePath = storage_path('app/public/' . $photo->photo_path);

        if (!file_exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'File foto tidak ditemukan di storage',
            ], 404);
        }

        return response()->download($filePath);
    }

    /**
     * Download semua foto dalam session sebagai ZIP
     * WAJIB ada email di database sebelum bisa download
     */
    public function downloadAll($session_uid)
    {
        $session = session::where('uid', $session_uid)->with('acara')->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Session tidak ditemukan',
            ], 404);
        }

        // VALIDASI: Email wajib ada di database sebelum download
        if (empty($session->email)) {
            return response()->json([
                'success' => false,
                'message' => 'Email belum diinput. Silakan input email terlebih dahulu untuk download foto',
            ], 400);
        }

        $photos = sessionPhoto::where('session_id', $session->id)
            ->where('type', 'framed') // hanya download yang sudah di-frame
            ->get();

        if ($photos->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada foto untuk didownload',
            ], 404);
        }

        try {
            $zip = new \ZipArchive();
            $zipFileName = Str::slug($session->acara->nama_pengantin) . '_' . $session->uid . '_' . time() . '.zip';
            $zipFilePath = storage_path('app/public/temp/' . $zipFileName);

            // Buat folder temp jika belum ada
            if (!file_exists(storage_path('app/public/temp'))) {
                mkdir(storage_path('app/public/temp'), 0755, true);
            }

            if ($zip->open($zipFilePath, \ZipArchive::CREATE) === TRUE) {
                foreach ($photos as $index => $photo) {
                    $filePath = storage_path('app/public/' . $photo->photo_path);
                    if (file_exists($filePath)) {
                        $extension = pathinfo($photo->photo_path, PATHINFO_EXTENSION);
                        $zip->addFile($filePath, 'foto_' . ($index + 1) . '.' . $extension);
                    }
                }
                $zip->close();

                return response()->download($zipFilePath)->deleteFileAfterSend(true);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal membuat ZIP file',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mendownload foto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
