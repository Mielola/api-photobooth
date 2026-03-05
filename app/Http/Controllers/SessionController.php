<?php

namespace App\Http\Controllers;

use App\Models\acara;
use App\Models\session;
use App\Models\frame;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

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
        try {
            $session = session::where('uid', $uid)->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session tidak ditemukan',
                ], 404);
            }

            $now = Carbon::now();
            $expiredTime = Carbon::parse($session->expired_time);

            // Cek apakah session expired atau sudah direset
            // Jika expired_time < sekarang, berarti sudah tidak aktif
            $isActive = $expiredTime->greaterThan($now);

            // Cek juga apakah expired_time adalah tahun 1999 (direset manual)
            $isReset = $expiredTime->year === 1999;

            if ($isReset) {
                $message = 'Session telah direset oleh admin';
            } elseif (!$isActive) {
                $message = 'Session telah expired';
            } else {
                $message = 'Session masih aktif';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'session_uid' => $session->uid,
                    'is_active' => $isActive && !$isReset,
                    'is_reset' => $isReset,
                    'expired_time' => $expiredTime->toIso8601String(),
                    'expired_timestamp' => $expiredTime->timestamp * 1000,
                    'remaining_seconds' => $isActive ? $now->diffInSeconds($expiredTime) : 0,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memeriksa status session',
                'error' => $e->getMessage(),
            ], 500);
        }
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

    /**
     * Get sessions by Acara UID (untuk admin dashboard)
     */
    public function getByAcaraUid(Request $request, $acaraUid)
    {
        try {
            // Cari acara berdasarkan UID
            $acara = acara::where('uid', $acaraUid)->first();

            if (!$acara) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acara tidak ditemukan',
                ], 404);
            }

            // Ambil parameter pagination
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);
            $search = $request->input('search', '');

            // Query sessions berdasarkan acara_id
            $query = session::where('acara_id', $acara->id)
                ->orderBy('created_at', 'desc');

            // Search by email atau session_uid jika ada
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('uid', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Pagination
            $sessions = $query->paginate($perPage, ['*'], 'page', $page);

            // Transform data
            $transformedData = $sessions->getCollection()->map(function ($session) {
                $now = Carbon::now();
                $expiredTime = Carbon::parse($session->expired_time);

                return [
                    'session_uid' => $session->uid,
                    'email' => $session->email,
                    'is_active' => $expiredTime->greaterThan($now) && $expiredTime->year !== 1999,
                    'is_reset' => $expiredTime->year === 1999,
                    'created_at' => $session->created_at->toIso8601String(),
                    'session_start' => $session->session_start,
                    'session_end' => $expiredTime->toIso8601String(),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Data session berhasil diambil',
                'data' => $transformedData,
                'pagination' => [
                    'current_page' => $sessions->currentPage(),
                    'per_page' => $sessions->perPage(),
                    'total' => $sessions->total(),
                    'last_page' => $sessions->lastPage(),
                    'from' => $sessions->firstItem(),
                    'to' => $sessions->lastItem(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data session',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete session by UID
     */
    public function destroy($sessionUid)
    {
        try {
            $session = session::where('uid', $sessionUid)->first();

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

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus session',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function exportToExcel($acaraUid)
    {
        try {
            // Cari acara berdasarkan UID
            $acara = acara::where('uid', $acaraUid)->first();

            if (!$acara) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acara tidak ditemukan',
                ], 404);
            }

            // Ambil semua sessions untuk acara ini
            $sessions = session::where('acara_id', $acara->id)
                ->orderBy('created_at', 'desc')
                ->get();

            if ($sessions->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data session untuk di-export',
                ], 404);
            }

            // Buat Spreadsheet baru
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set judul worksheet
            $sheet->setTitle('Sessions');

            // Header Excel
            $sheet->setCellValue('A1', 'No');
            $sheet->setCellValue('B1', 'Session ID');
            $sheet->setCellValue('C1', 'Email Client');
            $sheet->setCellValue('D1', 'Waktu Mulai');
            $sheet->setCellValue('E1', 'Waktu Selesai');

            // Styling header
            $headerStyle = [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 12,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '6A9C89'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ];
            $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

            // Set lebar kolom
            $sheet->getColumnDimension('A')->setWidth(5);
            $sheet->getColumnDimension('B')->setWidth(35);
            $sheet->getColumnDimension('C')->setWidth(30);
            $sheet->getColumnDimension('D')->setWidth(20);
            $sheet->getColumnDimension('E')->setWidth(20);

            // Isi data
            $row = 2;
            foreach ($sessions as $index => $session) {
                $now = Carbon::now();
                $expiredTime = Carbon::parse($session->expired_time);
                $isActive = $expiredTime->greaterThan($now) && $expiredTime->year !== 1999;
                $isReset = $expiredTime->year === 1999;

                $status = $isReset ? 'Reset' : ($isActive ? 'Aktif' : 'Expired');

                $sheet->setCellValue('A' . $row, $index + 1);
                $sheet->setCellValue('B' . $row, $session->uid);
                $sheet->setCellValue('C' . $row, $session->email ?: '-');
                $sheet->setCellValue('D' . $row, $session->created_at->format('d/m/Y H:i:s'));
                $sheet->setCellValue('E' . $row, $expiredTime->format('d/m/Y H:i:s'));
                $row++;
            }

            // Styling untuk body
            $bodyStyle = [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ];
            $sheet->getStyle('A1:F' . ($row - 1))->applyFromArray($bodyStyle);

            // Alignment untuk kolom nomor dan status
            $sheet->getStyle('A2:A' . ($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('F2:F' . ($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Generate filename
            $filename = 'Session_' . str_replace(' ', '_', $acara->nama_acara) . '_' . date('Y-m-d_His') . '.xlsx';

            // Set headers untuk download
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            // Write file ke output
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal export data session',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
