<?php

namespace App\Http\Controllers;

use App\Models\acara;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcaraController extends Controller
{
    //
    public function create(Request $request)
    {
        try {
            DB::beginTransaction();
            $validatedData = $request->validate([
                'nama_acara' => 'required|string|max:255',
                'tanggal_acara' => 'required|date',
                'lokasi_acara' => 'required|string|max:255',
            ]);

            $acara = acara::create($validatedData);

            DB::commit();
            return response()->json(['message' => 'Acara created successfully', 'data' => $acara], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Creation failed', 'error' => $e->getMessage()], 500);
        }
    }
}
