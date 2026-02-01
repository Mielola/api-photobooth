<?php

namespace App\Http\Controllers;

use App\Models\acara;
use App\Models\frame;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FrameController extends Controller
{
    //
    public function create(Request $request)
    {
        try {
            $validated = $request->validate([
                'nama_frame' => 'required|string|max:100',
                'jumlah_foto' => 'required|integer|min:1|max:10',
                'photo' => 'required|image|mimes:png,jpg,jpeg|max:5120',
                'acara_uid' => 'required|exists:table_acara,uid',
            ]);

            /**
             * ===============================
             * AMBIL DATA ACARA
             * ===============================
             */
            $acara = Acara::where('uid', $validated['acara_uid'])->first();

            if (!$acara) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Acara tidak ditemukan',
                ], 404);
            }

            /**
             * ===============================
             * TENTUKAN PATH FOLDER FRAME
             * ===============================
             */
            $slugNamaAcara = Str::slug($acara->nama_acara);
            $framePath = "Acara/{$slugNamaAcara}-{$acara->uid}/Frame";

            /**
             * ===============================
             * UPLOAD FOTO FRAME
             * ===============================
             */
            if ($request->hasFile('photo')) {
                $validated['photo'] = $request->file('photo')->store(
                    $framePath,
                    'public'
                );
            }

            /**
             * ===============================
             * SIMPAN KE DB
             * ===============================
             */
            $frame = Frame::create([
                'nama_frame' => $validated['nama_frame'],
                'jumlah_foto' => $validated['jumlah_foto'],
                'photo' => $validated['photo'],
                'acara_id' => $acara->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Frame berhasil dibuat',
                'data' => $frame,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Frame gagal dibuat',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 10);
        $query = frame::query();

        $frames = $query->orderByDesc('created_at')->paginate($perPage);
        return response()->json([
            'success' => true,
            'message' => 'Data Frame berhasil ditemukan',
            'data' => $frames,
        ], 200);
    }

    public function show($uid)
    {
        $frame = frame::where('uid', $uid)->first();
        if (!$frame) {
            return response()->json([
                'success' => false,
                'message' => 'Data Frame tidak ditemukan',
            ], 404);
        }
        return response()->json([
            'success' => true,
            'message' => 'Data Frame berhasil ditemukan',
            'data' => $frame,
        ], 200);
    }

    public function delete($uid)
    {
        $frame = Frame::with('acara')->where('uid', $uid)->first();

        if (!$frame) {
            return response()->json([
                'success' => false,
                'message' => 'Data Frame tidak ditemukan',
            ], 404);
        }

        /**
         * ===============================
         * HAPUS FILE FRAME
         * ===============================
         */
        if ($frame->photo && Storage::disk('public')->exists($frame->photo)) {
            Storage::disk('public')->delete($frame->photo);
        }

        /**
         * ===============================
         * HAPUS DATA DB
         * ===============================
         */
        $frame->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data Frame berhasil dihapus',
        ], 200);
    }

    public function update(Request $request, $uid)
    {
        try {
            $frame = Frame::with('acara')->where('uid', $uid)->first();

            if (!$frame) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Frame tidak ditemukan',
                ], 404);
            }

            $validated = $request->validate([
                'nama_frame' => 'sometimes|required|string|max:100',
                'jumlah_foto' => 'sometimes|required|integer|min:1|max:10',
                'photo' => 'sometimes|required|image|mimes:png,jpg,jpeg|max:5120',
            ]);

            /**
             * ===============================
             * JIKA UPDATE FOTO FRAME
             * ===============================
             */
            if ($request->hasFile('photo')) {

                // hapus foto lama
                if ($frame->photo && Storage::disk('public')->exists($frame->photo)) {
                    Storage::disk('public')->delete($frame->photo);
                }

                // tentukan folder acara
                $slugNamaAcara = Str::slug($frame->acara->nama_acara);
                $framePath = "Acara/{$slugNamaAcara}-{$frame->acara->uid}/Frame";

                // simpan foto baru
                $validated['photo'] = $request->file('photo')->store(
                    $framePath,
                    'public'
                );
            }

            /**
             * ===============================
             * UPDATE DATA FRAME
             * ===============================
             */
            $frame->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Data Frame berhasil diperbarui',
                'data' => $frame,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data Frame gagal diperbarui',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getByAcara($acara_uid)
    {
        try {
            $acara = Acara::where('uid', $acara_uid)->first();

            if (!$acara) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Acara tidak ditemukan',
                ], 404);
            }

            $frames = Frame::where('acara_id', $acara->id)
                ->orderBy('nama_frame')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Data Frame berhasil ditemukan',
                'data' => $frames,
            ], 200, [], JSON_UNESCAPED_SLASHES);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data frame',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
