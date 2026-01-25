<?php

namespace App\Http\Controllers;

use App\Models\acara;
use App\Models\frame;
use App\Models\sessionPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

            // Format tanggal ke 2000-01-01
            $validatedData['tanggal'] = date('Y-m-d', strtotime($validatedData['tanggal']));

            if ($request->hasFile('background')) {
                $validatedData['background'] = $request
                    ->file('background')
                    ->store('acara/backgrounds', 'public');
            }

            $acara = Acara::create($validatedData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Acara berhasil dibuat',
                'data' => $acara,
            ], 201);
        } catch (ValidationException $e) {
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

        $acaras = $query->orderByDesc('created_at')->paginate($perPage);
        return response()->json([
            'success' => true,
            'message' => 'Data Acara berhasil ditemukan',
            'data' => $acaras,
        ], 200);
    }

    public function show($uid)
    {
        $acara = acara::where('uid', $uid)->first();
        $sessionPhoto = sessionPhoto::join('table_session', 'table_session_photo.session_id', '=', 'table_session.id')
            ->where('table_session.acara_id', $acara->id)
            ->select('table_session_photo.*')
            ->get();
        $frame = frame::where('acara_id', $acara->id)->get();
        $acara->session_photos = $sessionPhoto;
        $acara->frame = $frame;

        if (!$acara) {
            return response()->json(['message' => 'Acara not found'], 404);
        }
        return response()->json([
            'success' => true,
            'message' => 'Data Acara berhasil ditemukan',
            'data' => $acara,
        ], 200);
    }

    public function delete($uid)
    {
        $acara = acara::where('uid', $uid)->first();
        if (!$acara) {
            return response()->json(['message' => 'Acara not found'], 404);
        }
        $acara->delete();
        return response()->json([
            'success' => true,
            'message' => 'Data Acara berhasil dihapus',
        ], 200);
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

}

