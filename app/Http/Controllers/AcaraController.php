<?php

namespace App\Http\Controllers;

use App\Models\acara;
use App\Models\frame;
use App\Models\sessionPhoto;
use App\Models\session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class AcaraController extends Controller
{
    public function create(Request $request)
    {
        DB::beginTransaction();

        try {
            $validatedData = $request->validate([
                'nama_acara' => 'required|string|max:255',
                'nama_pengantin' => 'required|string|max:255',
                'tanggal' => 'required|date',
                'background' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
            ]);

            // format tanggal
            $validatedData['tanggal'] = date('Y-m-d', strtotime($validatedData['tanggal']));

            // simpan acara ke DB dulu
            $acara = Acara::create($validatedData);

            /**
             * ===============================
             * BUAT STRUKTUR FOLDER
             * ===============================
             */
            $slugNamaAcara = Str::slug($acara->nama_acara);
            $basePath = "Acara/{$slugNamaAcara}-{$acara->uid}";

            Storage::disk('public')->makeDirectory($basePath);
            Storage::disk('public')->makeDirectory("{$basePath}/Frame");
            Storage::disk('public')->makeDirectory("{$basePath}/photos");

            /**
             * ===============================
             * SIMPAN BACKGROUND KE FOLDER ACARA
             * ===============================
             */
            if ($request->hasFile('background')) {
                $backgroundPath = $request->file('background')->store(
                    "{$basePath}/background",
                    'public'
                );

                $acara->update([
                    'background' => $backgroundPath
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Acara berhasil dibuat',
                'data' => $acara,
            ], 201);
        } catch (ValidationException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Acara gagal dibuat',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 10);
        $query = acara::query();
        $search = $request->query('search');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_acara', 'like', '%' . $search . '%')
                    ->orWhere('nama_pengantin', 'like', '%' . $search . '%');
            });
        }

        $acaras = $query->orderByDesc('created_at')->paginate($perPage);

        // Ambil 1 foto terakhir per acara
        $acaraIds = $acaras->pluck('id');
        $sessionPhoto = sessionPhoto::join('table_session', 'table_session_photo.session_id', '=', 'table_session.id')
            ->join('table_acara', 'table_session.acara_id', '=', 'table_acara.id')
            ->whereIn('table_session.acara_id', $acaraIds)
            ->select('table_session_photo.*', 'table_session.acara_id', 'table_acara.uid as acara_uid')
            ->whereIn('table_session_photo.id', function ($query) use ($acaraIds) {
                $query->select(DB::raw('MAX(table_session_photo.id)'))
                    ->from('table_session_photo')
                    ->join('table_session', 'table_session_photo.session_id', '=', 'table_session.id')
                    ->whereIn('table_session.acara_id', $acaraIds)
                    ->groupBy('table_session.acara_id');
            })
            ->get()
            ->map(function ($photo) {
                $photo->photo_url = asset('storage/' . $photo->photo_path);
                return $photo;
            });

        return response()->json([
            'success' => true,
            'message' => 'Data Acara berhasil ditemukan',
            'data' => $acaras,
            'session_photos' => $sessionPhoto,
        ], 200);
    }

    public function show($uid)
    {
        try {
            $acara = acara::where('uid', $uid)->get()
                ->map(function ($acara) {
                    if ($acara->background) {
                        $acara->background_url = asset('storage/' . $acara->background);
                        $acara->background = NULL;
                    }
                    return $acara;
                })
                ->first();
            $sessionPhoto = sessionPhoto::join('table_session', 'table_session_photo.session_id', '=', 'table_session.id')
                ->where('table_session.acara_id', $acara->id)
                ->select('table_session_photo.*')
                ->orderByDesc('table_session_photo.created_at')
                ->get()
                ->map(function ($photo) {
                    $photo->photo_url = asset('storage/' . $photo->photo_path);
                    return $photo;
                });
            $acara->session_photos = $sessionPhoto;
            $frame = frame::where('acara_id', $acara->id)->get();
            $acara->frame = $frame;

            if (!$acara) {
                return response()->json(['message' => 'Acara not found'], 404);
            }
            return response()->json([
                'success' => true,
                'message' => 'Data Acara berhasil ditemukan',
                'data' => $acara,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data acara',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function delete($uid)
    {
        DB::beginTransaction();

        try {
            $acara = Acara::where('uid', $uid)->first();

            if (!$acara) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acara not found'
                ], 404);
            }

            /**
             * ===============================
             * HAPUS FOLDER ACARA
             * ===============================
             */
            $slugNamaAcara = Str::slug($acara->nama_acara);
            $basePath = "Acara/{$slugNamaAcara}-{$acara->uid}";

            if (Storage::disk('public')->exists($basePath)) {
                Storage::disk('public')->deleteDirectory($basePath);
            }

            /**
             * ===============================
             * HAPUS DATA DB
             * ===============================
             */
            $acara->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data acara dan seluruh folder berhasil dihapus',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus acara',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function downloadPhotosByAcara($acara_uid)
    {
        try {
            $acara = Acara::where('uid', $acara_uid)->first();

            if (!$acara) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Acara tidak ditemukan',
                ], 404);
            }

            $slugNamaAcara = Str::slug($acara->nama_acara);
            $photosPath = "Acara/{$slugNamaAcara}-{$acara->uid}/photos";

            if (!Storage::disk('public')->exists($photosPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Folder foto tidak ditemukan',
                ], 404);
            }

            /**
             * ===============================
             * SIAPKAN FILE ZIP
             * ===============================
             */
            $zipFileName = "{$slugNamaAcara}-{$acara->uid}-photos.zip";
            $zipFullPath = storage_path("app/tmp/{$zipFileName}");

            // pastikan folder tmp ada
            if (!file_exists(dirname($zipFullPath))) {
                mkdir(dirname($zipFullPath), 0755, true);
            }

            $zip = new ZipArchive;
            if ($zip->open($zipFullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \Exception('Gagal membuat file ZIP');
            }

            /**
             * ===============================
             * MASUKKAN SEMUA FILE FOTO
             * ===============================
             */
            $files = Storage::disk('public')->allFiles($photosPath);

            foreach ($files as $file) {
                $absolutePath = storage_path("app/public/{$file}");
                $relativePath = str_replace($photosPath . '/', '', $file);

                $zip->addFile($absolutePath, $relativePath);
            }

            $zip->close();

            /**
             * ===============================
             * DOWNLOAD + AUTO DELETE
             * ===============================
             */
            return response()->download($zipFullPath)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mendownload foto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $uid)
    {
        try {
            DB::beginTransaction();
            $acara = acara::where('uid', $uid)->first();
            if (!$acara) {
                return response()->json(['message' => 'Acara not found'], 404);
            }

            $validatedData = $request->validate([
                'nama_acara' => 'sometimes|required|string|max:255',
                'nama_pengantin' => 'sometimes|required|string|max:255',
                'tanggal' => 'sometimes|required|date',
                'background' => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            ]);

            if ($request->hasFile('background')) {
                $validatedData['background'] = $request
                    ->file('background')
                    ->store('acara/backgrounds', 'public');
            }

            $acara->update($validatedData);

            DB::commit();
            return response()->json(['message' => 'Acara updated successfully', 'data' => $acara], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Update failed', 'error' => $e->getMessage()], 500);
        }
    }

    public function getActive()
    {
        try {
            $acaras = acara::where('status', true)
                ->orderByDesc('tanggal')
                ->get();

            // Add full URL for background images
            $acaras->transform(function ($acara) {
                if ($acara->background) {
                    $acara->background_url = asset('storage/' . $acara->background);
                }
                return $acara;
            });

            return response()->json([
                'success' => true,
                'message' => 'Data Acara aktif berhasil ditemukan',
                'data' => $acaras,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data acara',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function setStatusEvent($uid)
    {
        try {
            $acara = acara::where('uid', $uid)->first();
            if (!$acara) {
                return response()->json(['message' => 'Acara not found'], 404);
            }
            $acara->status = !$acara->status;
            $acara->save();

            return response()->json([
                'success' => true,
                'message' => 'Status acara berhasil diubah',
                'data' => $acara->status,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengubah status acara',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function reprintLastSession($acaraUid)
    {
        $acara = Acara::where('uid', $acaraUid)->firstOrFail();

        $photos = SessionPhoto::join('table_session', 'table_session_photo.session_id', '=', 'table_session.id')
            ->where('table_session.acara_id', $acara->id)
            ->orderBy('table_session_photo.created_at', 'desc')
            ->get();

        if ($photos->isEmpty()) {
            abort(404, 'Tidak ada foto');
        }

        $lastSessionId = $photos->first()->session_id;
        $photos = $photos->where('session_id', $lastSessionId);

        // 🔹 TEMP folder (bukan public)
        $tempDir = storage_path('app/temp');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipName = 'reprint-' . $acara->uid . '-' . time() . '.zip';
        $zipPath = $tempDir . '/' . $zipName;

        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($photos as $i => $photo) {
            $zip->addFile(
                storage_path('app/public/' . $photo->photo_path),
                'foto-' . ($i + 1) . '.jpg'
            );
        }

        $zip->close();
        return response()->download($zipPath)->deleteFileAfterSend(true);
    }

    public function reprintLastSessionByUidSession($uidSession)
    {
        $session = session::where('uid', $uidSession)->firstOrFail();

        $photos = SessionPhoto::where('table_session_photo.session_id', $session->id)
            ->orderBy('table_session_photo.created_at', 'desc')
            ->get();

        if ($photos->isEmpty()) {
            abort(404, 'Tidak ada foto');
        }

        $lastSessionId = $photos->first()->session_id;
        $photos = $photos->where('session_id', $lastSessionId);

        // 🔹 TEMP folder (bukan public)
        $tempDir = storage_path('app/temp');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipName = 'reprint-' . $session->uid . '-' . time() . '.zip';
        $zipPath = $tempDir . '/' . $zipName;

        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($photos as $i => $photo) {
            $zip->addFile(
                storage_path('app/public/' . $photo->photo_path),
                'foto-' . ($i + 1) . '.jpg'
            );
        }

        $zip->close();
        return response()->download($zipPath)->deleteFileAfterSend(true);
    }

    public function resetSession($acaraUid)
    {
        DB::beginTransaction();
        try {
            $session = session::join(
                'table_acara',
                'table_session.acara_id',
                '=',
                'table_acara.id'
            )
                ->where('table_acara.uid', $acaraUid)
                ->select('table_session.*')
                ->orderByDesc('table_session.created_at')
                ->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesi tidak ditemukan untuk acara ini.',
                ], 404);
            }

            if (!$session->expired_time || $session->expired_time < now()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesi terakhir sudah direset',
                ], 400);
            }

            // Expire paksa
            $session->expired_time = '1999-01-01 00:00:00';
            $session->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sesi berhasil direset.',
                'data' => [
                    'session_uid' => $session->uid,
                    'session_id' => $session->id,
                ],
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mereset sesi.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
