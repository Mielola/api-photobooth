<?php

namespace App\Http\Controllers;

use App\Models\sessionPhoto;
use App\Models\session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Support\Str;

class PhotoController extends Controller
{

    public function create(Request $request)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validate([
                'session_uid' => 'required|string',
                'photo' => 'required|image|mimes:jpeg,png,jpg|max:5120',
                'type' => 'required|string|in:original,edited,filtered,result',
            ]);

            // Cari session
            $session = session::where('uid', $validatedData['session_uid'])->with('acara')->first();

            if (!$session) {
                DB::rollBack();
                return response()->json(['message' => 'Session tidak ditemukan', 'error' => 'Session dengan UID tersebut tidak ada'], 404);
            }

            // Cek apakah session masih aktif
            $now = Carbon::now();
            $expired = Carbon::parse($session->expired_time);
            if ($now->greaterThanOrEqualTo($expired)) {
                DB::rollBack();
                return response()->json(['message' => 'Session sudah kadaluarsa', 'error' => 'Waktu session telah habis'], 403);
            }

            // Buat nama folder berdasarkan nama pengantin
            $namaPengantin = Str::slug($session->acara->nama_pengantin, '_');
            $folderPath = 'event/' . $namaPengantin . '_1';

            // Upload foto
            $file = $request->file('photo');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs($folderPath, $filename, 'public');

            $photo = sessionPhoto::create([
                'type' => $validatedData['type'],
                'photo_path' => $path,
                'session_id' => $session->id
            ]);

            DB::commit();
            return response()->json([
                'message' => 'Foto berhasil diupload',
                'data' => [
                    'photo' => $photo,
                    'url' => asset('storage/' . $path)
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal mengupload foto', 'error' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'session_uid' => 'required|string',
            ]);

            $session = session::where('uid', $validatedData['session_uid'])->first();

            if (!$session) {
                return response()->json(['message' => 'Session tidak ditemukan', 'error' => 'Session dengan UID tersebut tidak ada'], 404);
            }

            $photos = sessionPhoto::where('session_id', $session->id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($photo) {
                    return [
                        'uid' => $photo->uid,
                        'type' => $photo->type,
                        'photo_path' => $photo->photo_path,
                        'url' => asset('storage/' . $photo->photo_path),
                        'created_at' => $photo->created_at
                    ];
                });

            return response()->json([
                'message' => 'Daftar foto berhasil diambil',
                'data' => [
                    'session_uid' => $validatedData['session_uid'],
                    'total_foto' => $photos->count(),
                    'photos' => $photos
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal mengambil daftar foto', 'error' => $e->getMessage()], 500);
        }
    }

    public function show($uid)
    {
        try {
            $photo = sessionPhoto::where('uid', $uid)->with('session')->first();

            if (!$photo) {
                return response()->json(['message' => 'Foto tidak ditemukan', 'error' => 'Foto dengan UID tersebut tidak ada'], 404);
            }

            return response()->json([
                'message' => 'Detail foto berhasil diambil',
                'data' => [
                    'photo' => $photo,
                    'url' => asset('storage/' . $photo->photo_path)
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal mengambil detail foto', 'error' => $e->getMessage()], 500);
        }
    }

    public function retake(Request $request, $uid)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validate([
                'photo' => 'required|image|mimes:jpeg,png,jpg|max:5120',
                'type' => 'required|string|in:original,edited,filtered,result',
            ]);

            $oldPhoto = sessionPhoto::where('uid', $uid)->first();

            if (!$oldPhoto) {
                DB::rollBack();
                return response()->json(['message' => 'Foto tidak ditemukan', 'error' => 'Foto dengan UID tersebut tidak ada'], 404);
            }

            $session = session::with('acara')->find($oldPhoto->session_id);
            $now = Carbon::now();
            $expired = Carbon::parse($session->expired_time);
            if ($now->greaterThanOrEqualTo($expired)) {
                DB::rollBack();
                return response()->json(['message' => 'Session sudah kadaluarsa', 'error' => 'Waktu session telah habis'], 403);
            }

            // Hapus foto lama
            if (Storage::disk('public')->exists($oldPhoto->photo_path)) {
                Storage::disk('public')->delete($oldPhoto->photo_path);
            }

            // Buat nama folder berdasarkan nama pengantin
            $namaPengantin = Str::slug($session->acara->nama_pengantin, '_');
            $folderPath = 'event/' . $namaPengantin . '_1';

            // Upload foto baru
            $file = $request->file('photo');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs($folderPath, $filename, 'public');

            $oldPhoto->update([
                'type' => $validatedData['type'],
                'photo_path' => $path,
            ]);

            DB::commit();
            return response()->json([
                'message' => 'Foto berhasil di-retake',
                'data' => [
                    'photo' => $oldPhoto->fresh(),
                    'url' => asset('storage/' . $path)
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal melakukan retake foto', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy($uid)
    {
        try {
            DB::beginTransaction();

            $photo = sessionPhoto::where('uid', $uid)->first();

            if (!$photo) {
                DB::rollBack();
                return response()->json(['message' => 'Foto tidak ditemukan', 'error' => 'Foto dengan UID tersebut tidak ada'], 404);
            }

            if (Storage::disk('public')->exists($photo->photo_path)) {
                Storage::disk('public')->delete($photo->photo_path);
            }

            $photo->delete();

            DB::commit();
            return response()->json(['message' => 'Foto berhasil dihapus'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menghapus foto', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroyBySession(Request $request)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validate([
                'session_uid' => 'required|string',
            ]);

            $session = session::where('uid', $validatedData['session_uid'])->first();

            if (!$session) {
                DB::rollBack();
                return response()->json(['message' => 'Session tidak ditemukan', 'error' => 'Session dengan UID tersebut tidak ada'], 404);
            }

            $photos = sessionPhoto::where('session_id', $session->id)->get();

            foreach ($photos as $photo) {
                if (Storage::disk('public')->exists($photo->photo_path)) {
                    Storage::disk('public')->delete($photo->photo_path);
                }
                $photo->delete();
            }

            DB::commit();
            return response()->json(['message' => 'Semua foto dalam session berhasil dihapus', 'data' => ['total_deleted' => $photos->count()]], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menghapus foto', 'error' => $e->getMessage()], 500);
        }
    }

    public function download($uid)
    {
        try {
            $photo = sessionPhoto::where('uid', $uid)->first();

            if (!$photo) {
                return response()->json(['message' => 'Foto tidak ditemukan', 'error' => 'Foto dengan UID tersebut tidak ada'], 404);
            }

            $filePath = storage_path('app/public/' . $photo->photo_path);

            if (!file_exists($filePath)) {
                return response()->json(['message' => 'File foto tidak ditemukan', 'error' => 'File tidak ada di storage'], 404);
            }

            return response()->download($filePath);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal mendownload foto', 'error' => $e->getMessage()], 500);
        }
    }

    public function downloadAllBySession($session_uid)
    {
        try {
            $session = session::where('uid', $session_uid)->first();

            if (!$session) {
                return response()->json(['message' => 'Session tidak ditemukan', 'error' => 'Session dengan UID tersebut tidak ada'], 404);
            }

            if (empty($session->email)) {
                return response()->json(['message' => 'Email wajib diisi', 'error' => 'Silakan isi email terlebih dahulu sebelum mendownload foto'], 403);
            }

            $photos = sessionPhoto::where('session_id', $session->id)->get();

            if ($photos->isEmpty()) {
                return response()->json(['message' => 'Tidak ada foto', 'error' => 'Session ini belum memiliki foto'], 404);
            }

            $zip = new \ZipArchive();
            $zipFileName = 'photos_' . $session_uid . '_' . time() . '.zip';
            $zipFilePath = storage_path('app/public/temp/' . $zipFileName);

            if (!file_exists(storage_path('app/public/temp'))) {
                mkdir(storage_path('app/public/temp'), 0755, true);
            }

            if ($zip->open($zipFilePath, \ZipArchive::CREATE) === TRUE) {
                foreach ($photos as $index => $photo) {
                    $filePath = storage_path('app/public/' . $photo->photo_path);
                    if (file_exists($filePath)) {
                        $zip->addFile($filePath, 'foto_' . ($index + 1) . '_' . basename($photo->photo_path));
                    }
                }
                $zip->close();

                return response()->download($zipFilePath)->deleteFileAfterSend(true);
            } else {
                return response()->json(['message' => 'Gagal membuat ZIP file', 'error' => 'Tidak dapat membuat file ZIP'], 500);
            }

        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal mendownload foto', 'error' => $e->getMessage()], 500);
        }
    }
}
