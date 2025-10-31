<?php

namespace App\Http\Controllers\Api\Sanpam;

use App\Http\Controllers\Controller;
use App\Models\UsulanSAPDSFasilitasUmum;
use App\Models\SAPDUpload;
use App\Models\SAPDUploadTemp;
use App\Models\Perencanaan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SAPDFasilitasUmumController extends Controller
{
    private const FILE_ARRAY_FIELDS = [
        'buktiKepemilikan',
        'proposal',
        'fotoLahan',
    ];

    // (Opsional) Upload ke TEMP — path sama (sapd_temp)
    public function upload(Request $request)
    {
        if (!auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:4096',
        ]);

        $path = $request->file('file')->store('sapd_temp');

        $temp = SAPDUploadTemp::create([
            'uuid'      => (string) Str::uuid(),
            'user_id'   => (string) auth()->id(),
            'file_path' => $path,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'uuid'    => $temp->uuid,
                'user_id' => $temp->user_id,
            ],
        ]);
    }

    // Submit usulan + pindahkan file dari TEMP ke FINAL (sapd_final)
    public function submit(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        // Aliases + normalisasi semua field file (boleh JSON/comma/single/array)
        $this->applyAliases($request);
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            $this->normalizeUuidArrayField($request, $f);
        }

        $payload = $request->validate([
            'namaFasilitasUmum'   => 'required|string',
            'alamatFasilitasUmum' => 'required|string',
            'rwFasilitasUmum'     => 'required|string',
            'rtFasilitasUmum'     => 'required|string',
            'kecamatan'           => 'required|string',
            'kelurahan'           => 'required|string',
            'ukuranLahan'         => 'nullable|string',
            'statusKepemilikan'   => 'required|string',
            'titikLokasi'         => 'nullable|string',
            'pesan_verifikasi'    => 'nullable|string|max:512',

            // ARRAY UUID (min 1, max 10)
            'buktiKepemilikan'    => 'required|array|min:1|max:10',
            'buktiKepemilikan.*'  => 'uuid',
            'proposal'            => 'required|array|min:1|max:10',
            'proposal.*'          => 'uuid',
            'fotoLahan'           => 'required|array|min:1|max:10',
            'fotoLahan.*'         => 'uuid',
        ]);

        $payload['uuid']  = (string) Str::uuid();
        $payload['user_id'] = (string) $user->id;               // <<— simpan user pemilik
        $payload['status_verifikasi_usulan'] = 0;

        // Pindahkan semua UUID dari TEMP → FINAL
        $allUuids = array_merge(
            $payload['buktiKepemilikan'],
            $payload['proposal'],
            $payload['fotoLahan'],
        );
        $this->moveTempsToFinal($allUuids, (string) $user->id);

        $data = UsulanSAPDSFasilitasUmum::create($payload);

        return response()->json([
            'success' => true,
            'message' => 'Usulan SAPD Fasilitas Umum berhasil disimpan',
            'uuid'    => $data->uuid,
        ]);
    }

    /**
     * POST /api/sanpam/fasum/update/{uuid}
     * Partial update: hanya field yang dikirim akan diubah.
     * Kolom file adalah ARRAY UUID (kirim array untuk ganti; kirim null → diabaikan; tidak kirim → tetap).
     */
    public function update(Request $request, string $uuid)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $item = UsulanSAPDSFasilitasUmum::where('uuid', $uuid)
            // ->where('user_id', $user->id) // aktifkan jika hanya pemilik boleh update
            ->first();

        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        // Aliases + normalisasi semua file-array
        $this->applyAliases($request);
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            $this->normalizeUuidArrayField($request, $f);
        }

        $validated = $request->validate([
            'namaFasilitasUmum'        => 'sometimes|string',
            'alamatFasilitasUmum'      => 'sometimes|string',
            'rwFasilitasUmum'          => 'sometimes|string',
            'rtFasilitasUmum'          => 'sometimes|string',
            'kecamatan'                => 'sometimes|string',
            'kelurahan'                => 'sometimes|string',
            'ukuranLahan'              => 'sometimes|nullable|string',
            'statusKepemilikan'        => 'sometimes|string',
            'titikLokasi'              => 'sometimes|nullable|string',
            'pesan_verifikasi'         => 'sometimes|nullable|string|max:512',

            // File arrays
            'buktiKepemilikan'         => 'sometimes|nullable|array|min:1|max:10',
            'buktiKepemilikan.*'       => 'uuid',
            'proposal'                 => 'sometimes|nullable|array|min:1|max:10',
            'proposal.*'               => 'uuid',
            'fotoLahan'                => 'sometimes|nullable|array|min:1|max:10',
            'fotoLahan.*'              => 'uuid',

            // Verifikasi
            'status_verifikasi_usulan' => 'sometimes|integer|in:0,1,2,3,4,5,6,7,8,9',
        ]);

        // Pindahkan UUID baru (yang belum ada di existing)
        $uuidsToMove = [];
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if ($request->has($f) && is_array($request->input($f))) {
                $incoming = $request->input($f);
                $existing = $item->getAttribute($f) ?? [];
                $diff     = array_diff($incoming, is_array($existing) ? $existing : []);
                if ($diff) {
                    $uuidsToMove = array_merge($uuidsToMove, $diff);
                }
            }
        }
        if ($uuidsToMove) {
            $this->moveTempsToFinal(array_values(array_unique($uuidsToMove)), (string) $user->id);
        }

        // Siapkan payload update; jika kolom file dikirim null → jangan tulis (biar nilai lama tidak hilang)
        $updateData = $validated;
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if (array_key_exists($f, $updateData) && is_null($updateData[$f])) {
                unset($updateData[$f]);
            }
        }

        // Catat field yang berubah (untuk pesan)
        $changed = [];
        foreach ($updateData as $key => $val) {
            if ($item->getAttribute($key) !== $val) {
                $changed[] = $key;
            }
        }

        if ($updateData) {
            $item->update($updateData);
        }

        $message = $changed
            ? 'Field Berikut Berhasil di Perbaharui: ' . implode(', ', $changed)
            : 'Tidak ada perubahan data';

        return response()->json([
            'success' => true,
            'message' => $message,
            'uuid'    => $item->uuid,
            'data'    => $item->fresh(),
        ]);
    }

    // DELETE /api/sanpam/fasum/{uuid}
    public function destroy(string $uuid)
    {
        $item = UsulanSAPDSFasilitasUmum::where('uuid', $uuid)
            // ->where('user_id', auth()->id()) // aktifkan jika hanya pemilik boleh hapus
            ->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ], 404);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil dihapus',
        ]);
    }

    // List (tanpa pagination)
    // Tambahkan ?mine=1 untuk hanya data milik user login
    public function index(Request $request)
    {
        $q = UsulanSAPDSFasilitasUmum::query()->latest();

        if ($request->boolean('mine') && auth()->check()) {
            $q->where('user_id', (string) auth()->id());
        }

        $list = $q->get()->map(function ($item) {
            return [
                'uuid'                     => $item->uuid,
                'user_id'                  => $item->user_id,
                'namaFasilitasUmum'        => $item->namaFasilitasUmum,
                'alamatFasilitasUmum'      => $item->alamatFasilitasUmum,
                'rwFasilitasUmum'          => $item->rwFasilitasUmum,
                'rtFasilitasUmum'          => $item->rtFasilitasUmum,
                'kecamatan'                => $item->kecamatan,
                'kelurahan'                => $item->kelurahan,
                'ukuranLahan'              => $item->ukuranLahan,
                'statusKepemilikan'        => $item->statusKepemilikan,
                'titikLokasi'              => $item->titikLokasi,
                'pesan_verifikasi'         => $item->pesan_verifikasi,

                // Array UUID file
                'buktiKepemilikan'         => $item->buktiKepemilikan,
                'proposal'                 => $item->proposal,
                'fotoLahan'                => $item->fotoLahan,

                // status verifikasi usulan
                'status_verifikasi_usulan' => $item->status_verifikasi_usulan,

                'created_at'               => $item->created_at,
            ];
        });

        return response()->json(['success' => true, 'data' => $list]);
    }

    // Detail (kembalikan array UUID; tanpa path file)
   public function show($uuid)
{
    // Ambil usulan utama
    $item = UsulanSAPDSFasilitasUmum::where('uuid', $uuid)
        // ->where('user_id', auth()->id()) // aktifkan kalau mau limit by pemilik
        ->first();

    if (!$item) {
        return response()->json([
            'success' => false,
            'message' => 'Data tidak ditemukan'
        ], 404);
    }

    // Ambil semua perencanaan yang nempel ke usulan ini
    // Match: perencanaans.uuidUsulan == $uuid (uuid usulan fasilitas umum)
    $perencanaanRows = Perencanaan::where('uuidUsulan', $uuid)
        ->orderBy('created_at', 'desc')
        ->get();

    // Rapihin bentuk respons perencanaan
    $perencanaanList = $perencanaanRows->map(function ($row) {
        return [
            'uuidPerencanaan' => $row->id,            // PK UUID dari tabel perencanaans
            'uuidUsulan'      => $row->uuidUsulan,    // harusnya sama dengan yang diminta di param
            'nilaiHPS'        => $row->nilaiHPS,
            'catatanSurvey'   => $row->catatanSurvey,
            'created_at'      => $row->created_at,
            'updated_at'      => $row->updated_at,
        ];
    })->values();

    return response()->json([
        'success' => true,
        'data' => [
            'usulan' => [
                'uuid'                     => $item->uuid,
                'user_id'                  => $item->user_id,
                'namaFasilitasUmum'        => $item->namaFasilitasUmum,
                'alamatFasilitasUmum'      => $item->alamatFasilitasUmum,
                'rwFasilitasUmum'          => $item->rwFasilitasUmum,
                'rtFasilitasUmum'          => $item->rtFasilitasUmum,
                'kecamatan'                => $item->kecamatan,
                'kelurahan'                => $item->kelurahan,
                'ukuranLahan'              => $item->ukuranLahan,
                'statusKepemilikan'        => $item->statusKepemilikan,
                'titikLokasi'              => $item->titikLokasi,
                'pesan_verifikasi'         => $item->pesan_verifikasi,

                // Array UUID file
                'buktiKepemilikan'         => $item->buktiKepemilikan,
                'proposal'                 => $item->proposal,
                'fotoLahan'                => $item->fotoLahan,

                // status verifikasi usulan
                'status_verifikasi_usulan' => $item->status_verifikasi_usulan,

                'created_at'               => $item->created_at,
                'updated_at'               => $item->updated_at,
            ],

            // daftar perencanaan terkait usulan ini (bisa kosong [])
            'perencanaan' => $perencanaanList,
        ],
    ]);
}

    // ================ Helpers ================

    /** snake_case → camelCase aliases */
    private function applyAliases(Request $request): void
    {
        $aliases = [
            'nama_fasilitas_umum'   => 'namaFasilitasUmum',
            'alamat_fasilitas_umum' => 'alamatFasilitasUmum',
            'rw_fasilitas_umum'     => 'rwFasilitasUmum',
            'rt_fasilitas_umum'     => 'rtFasilitasUmum',
            'ukuran_lahan'          => 'ukuranLahan',
            'status_kepemilikan'    => 'statusKepemilikan',
            'titik_lokasi'          => 'titikLokasi',
            'bukti_kepemilikan'     => 'buktiKepemilikan',
            'dokumen_proposal'      => 'proposal',
            'foto_lahan'            => 'fotoLahan',
            'pesanVerifikasi'       => 'pesan_verifikasi',
        ];
        $merge = [];
        foreach ($aliases as $from => $to) {
            if ($request->has($from) && !$request->has($to)) {
                $merge[$to] = $request->input($from);
            }
        }
        if ($merge) $request->merge($merge);
    }

    /**
     * Normalisasi satu field array-UUID dari berbagai bentuk input:
     * - JSON array string: '["uuid1","uuid2"]'
     * - Comma-separated:  'uuid1,uuid2'
     * - Single UUID:      'uuid1'
     * - Array campur path: ['sapd_temp/xx_uuid1.pdf','uuid2']
     * - "null"/'' → null (untuk update)
     */
    private function normalizeUuidArrayField(Request $request, string $field): void
    {
        if (!$request->has($field)) return;

        $val = $request->input($field);
        if ($val === null) return;

        if (is_array($val)) {
            $uuids = [];
            foreach ($val as $v) {
                $u = $this->extractUuid((string)$v);
                if ($u) $uuids[] = $u;
            }
            $request->merge([$field => array_values(array_unique($uuids))]);
            return;
        }

        if (is_string($val)) {
            $t = trim($val);

            if ($t === '' || strtolower($t) === 'null') {
                $request->merge([$field => null]);
                return;
            }

            if ($t !== '' && $t[0] === '[') {
                $arr = json_decode($t, true);
                if (is_array($arr)) {
                    $uuids = [];
                    foreach ($arr as $v) {
                        $u = $this->extractUuid((string)$v);
                        if ($u) $uuids[] = $u;
                    }
                    $request->merge([$field => array_values(array_unique($uuids))]);
                    return;
                }
            }

            if (str_contains($t, ',')) {
                $parts = array_map('trim', explode(',', $t));
                $uuids = [];
                foreach ($parts as $p) {
                    $u = $this->extractUuid($p);
                    if ($u) $uuids[] = $u;
                }
                $request->merge([$field => array_values(array_unique($uuids))]);
                return;
            }

            $u = $this->extractUuid($t);
            $request->merge([$field => $u ? [$u] : []]);
            return;
        }
    }

    /** Ekstrak UUID dari string (v1–v7, toleran). */
    private function extractUuid(string $value): ?string
    {
        if (preg_match('/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}/', $value, $m)) {
            return strtolower($m[0]);
        }
        return null;
    }

    /** Pindahkan file dari TEMP → FINAL untuk daftar UUID (user yang sama). */
    private function moveTempsToFinal(array $uuids, string $userId): void
    {
        $uuids = array_values(array_unique(array_filter($uuids)));
        if (!$uuids) return;

        $temps = SAPDUploadTemp::whereIn('uuid', $uuids)
            ->where('user_id', $userId)
            ->get()
            ->keyBy('uuid');

        foreach ($uuids as $u) {
            $temp = $temps->get($u);
            if (!$temp) {
                // mungkin sudah FINAL (reuse) → lewati
                continue;
            }

            $oldPath  = $temp->file_path; // sapd_temp/<name>.<ext>
            $filename = basename($oldPath);
            $newPath  = 'sapd_final/' . $filename;

            // Hindari overwrite
            if (Storage::exists($newPath)) {
                $ext     = pathinfo($filename, PATHINFO_EXTENSION);
                $newName = (string) Str::uuid() . ($ext ? ".{$ext}" : '');
                $newPath = 'sapd_final/' . $newName;
            }

            if (Storage::exists($oldPath)) {
                Storage::move($oldPath, $newPath);
            } elseif (!Storage::exists($newPath)) {
                continue;
            }

            SAPDUpload::updateOrCreate(
                ['uuid' => $temp->uuid],
                ['user_id' => $userId, 'file_path' => $newPath]
            );

            $temp->delete();
        }
    }
}
