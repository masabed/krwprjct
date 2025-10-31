<?php

namespace App\Http\Controllers\Api\Permukiman;

use App\Http\Controllers\Controller;
use App\Models\Permukiman;
use App\Models\PermukimanUpload;
use App\Models\PermukimanUploadTemp;
use App\Models\Perencanaan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PermukimanController extends Controller
{
    /**
     * Semua kolom file bertipe ARRAY UUID.
     */
    private const FILE_ARRAY_FIELDS = [
        'foto_sertifikat_status_tanah',
        'foto_sta0',
        'foto_sta100',
        'surat_pemohonan',
        'proposal_usulan',
    ];

    /**
     * POST /permukiman/upload
     * Upload ke TEMP; balikan UUID temp untuk dipakai saat store/update.
     */
    public function upload(Request $request)
    {
        if (!auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:4096',
        ]);

        $path   = $request->file('file')->store('permukiman_temp');
        $userId = (string) auth()->id();

        $temp = PermukimanUploadTemp::create([
            'uuid'      => (string) Str::uuid(),
            'user_id'   => $userId,
            'file_path' => $path,
        ]);

        return response()->json([
            'success' => true,
            'data'    => ['uuid' => $temp->uuid, 'user_id' => $temp->user_id],
        ], 201);
    }

    /**
     * GET /permukiman (list + filter)
     */
  public function index(Request $request)
{
    $q = Permukiman::query();

    if ($request->filled('status')) {
        $q->where('status_verifikasi', (int) $request->status);
    }

    if ($request->filled('search')) {
        $s = $request->search;
        $q->where(function ($qq) use ($s) {
            $qq->where('nama_pengusul', 'like', "%$s%")
               ->orWhere('instansi', 'like', "%$s%")
               ->orWhere('jenis_usulan', 'like', "%$s%");
        });
    }

    $data = $q->latest()->get();

    return response()->json([
        'success' => true,
        'count'   => $data->count(),
        'data'    => $data,
    ]);
}

    /**
     * GET /permukiman/{id} (detail by PK string)
     */
   public function show(string $id)
{
    // ambil data utama permukiman
    $data = Permukiman::find($id);

    if (!$data) {
        return response()->json([
            'success' => false,
            'message' => 'Data tidak ditemukan',
        ], 404);
    }

    // cari semua perencanaan yang nempel ke usulan ini
    // asumsi: kolom relasi = perencanaans.uuidUsulan == $id
    $perencanaanRows = Perencanaan::where('uuidUsulan', $id)
        ->orderBy('created_at', 'desc')
        ->get();

    // bentuk list rapi
    $perencanaanList = $perencanaanRows->map(function ($row) {
        return [
            'uuidPerencanaan' => $row->id,          // PK UUID di tabel perencanaans
            'uuidUsulan'      => $row->uuidUsulan,  // harusnya sama dengan $id yang diminta di endpoint
            'nilaiHPS'        => $row->nilaiHPS,
            'catatanSurvey'   => $row->catatanSurvey,
            'created_at'      => $row->created_at,
            'updated_at'      => $row->updated_at,
        ];
    })->values();

    return response()->json([
        'success' => true,
        'data'    => [
            // semua kolom bawaan Permukiman
            ...$data->toArray(),

            // plus relasi perencanaan (bisa kosong [])
            'perencanaan' => $perencanaanList,
        ],
    ]);
}

    /**
     * POST /permukiman/create
     * Create + pindahkan file dari TEMP → FINAL
     * Catatan: status_verifikasi auto = 0
     * Semua kolom file = ARRAY UUID
     */
   public function store(Request $request)
{
    if (!auth()->check()) {
        return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
    }

    $userId = (string) auth()->id();

    // Alias & normalisasi input file-array (boleh JSON/comma/single/array/path)
    $this->applyAliases($request);
    foreach (self::FILE_ARRAY_FIELDS as $f) {
        $this->normalizeUuidArrayField($request, $f);
    }

    $payload = $request->validate([
        'sumber_usulan'                  => ['required','string','max:255'],
        'jenis_usulan'                   => ['required','string','max:255'],
        'nama_pengusul'                  => ['required','string','max:255'],
        'no_kontak_pengusul'             => ['required','string','max:50'],
        'email'                          => ['required','email'],
        'instansi'                       => ['required','string','max:255'],
        'alamat_dusun_instansi'          => ['required','string','max:255'],
        'alamat_rt_instansi'             => ['required','string','max:10'],
        'alamat_rw_instansi'             => ['required','string','max:10'],
        'tanggal_usulan'                 => ['required','date'],
        'nama_pic'                       => ['required','string','max:255'],
        'no_kontak_pic'                  => ['required','string','max:50'],
        'status_tanah'                   => ['required','string','max:100'],
        'pesan_verifikasi'               => ['nullable','string','max:512'],

        // FILES (ARRAY UUID)
        'foto_sertifikat_status_tanah'   => ['required','array','min:1','max:10'],
        'foto_sertifikat_status_tanah.*' => ['uuid'],

        'panjang_usulan'                 => ['required','string','max:100'],
        'alamat_dusun_usulan'            => ['required','string','max:255'],
        'alamat_rt_usulan'               => ['required','string','max:10'],
        'alamat_rw_usulan'               => ['required','string','max:10'],
        'kecamatan'                      => ['required','string','max:100'],
        'kelurahan'                      => ['required','string','max:100'],
        'titik_lokasi'                   => ['required','string','max:255'],

        'foto_sta0'                      => ['required','array','min:1','max:10'],
        'foto_sta0.*'                    => ['uuid'],
        'foto_sta100'                    => ['required','array','min:1','max:10'],
        'foto_sta100.*'                  => ['uuid'],
        'surat_pemohonan'                => ['required','array','min:1','max:10'],
        'surat_pemohonan.*'              => ['uuid'],
        'proposal_usulan'                => ['required','array','min:1','max:10'],
        'proposal_usulan.*'              => ['uuid'],
    ]);

    // Set nilai otomatis
    $payload['status_verifikasi'] = 0;     // default saat submit
    $payload['user_id']           = $userId; // penting agar bisa difilter per-user

    // Pindahkan file dari TEMP → FINAL
    $allUuids = array_values(array_unique(array_merge(
        $payload['foto_sertifikat_status_tanah'],
        $payload['foto_sta0'],
        $payload['foto_sta100'],
        $payload['surat_pemohonan'],
        $payload['proposal_usulan'],
    )));
    $this->moveTempToFinalUuids($allUuids, $userId);

    // Simpan record
    $data = Permukiman::create($payload);

    return response()->json([
        'success' => true,
        'message' => 'Permukiman berhasil dibuat dan file dipindahkan',
        'data'    => $data,
    ], 201);
}

    /**
     * POST /permukiman/update/{id}
     * Partial update; kolom file: ARRAY UUID
     */
    public function update(Request $request, string $id)
    {
        if (!auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $item = Permukiman::find($id);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $this->applyAliases($request);

        // Normalisasi semua file-array (boleh JSON/comma/single/array)
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            $this->normalizeUuidArrayField($request, $f);
        }

        $validated = $request->validate([
            'sumber_usulan'                 => 'sometimes|string|max:255',
            'jenis_usulan'                  => 'sometimes|string|max:255',
            'nama_pengusul'                 => 'sometimes|string|max:255',
            'no_kontak_pengusul'            => 'sometimes|string|max:50',
            'email'                         => 'sometimes|email',
            'instansi'                      => 'sometimes|string|max:255',
            'alamat_dusun_instansi'         => 'sometimes|string|max:255',
            'alamat_rt_instansi'            => 'sometimes|string|max:10',
            'alamat_rw_instansi'            => 'sometimes|string|max:10',
            'tanggal_usulan'                => 'sometimes|date',
            'nama_pic'                      => 'sometimes|string|max:255',
            'no_kontak_pic'                 => 'sometimes|string|max:50',
            'status_tanah'                  => 'sometimes|string|max:100',
            'panjang_usulan'                => 'sometimes|string|max:100',
            'alamat_dusun_usulan'           => 'sometimes|string|max:255',
            'alamat_rt_usulan'              => 'sometimes|string|max:10',
            'alamat_rw_usulan'              => 'sometimes|string|max:10',
            'kecamatan'                     => 'sometimes|string|max:100',
            'kelurahan'                     => 'sometimes|string|max:100',
            'titik_lokasi'                  => 'sometimes|string|max:255',
            'pesan_verifikasi'              => 'sometimes|nullable|string|max:512',

            // FILE ARRAYS (nullable → kalau null dikirim, kita abaikan di payload agar tidak mengosongkan kolom)
            'foto_sertifikat_status_tanah'  => 'sometimes|nullable|array|min:1|max:10',
            'foto_sertifikat_status_tanah.*'=> 'uuid',
            'foto_sta0'                     => 'sometimes|nullable|array|min:1|max:10',
            'foto_sta0.*'                   => 'uuid',
            'foto_sta100'                   => 'sometimes|nullable|array|min:1|max:10',
            'foto_sta100.*'                 => 'uuid',
            'surat_pemohonan'               => 'sometimes|nullable|array|min:1|max:10',
            'surat_pemohonan.*'             => 'uuid',
            'proposal_usulan'               => 'sometimes|nullable|array|min:1|max:10',
            'proposal_usulan.*'             => 'uuid',

            'status_verifikasi'             => 'sometimes|integer|in:0,1,2,3,4,5,6,7,8,9',
        ]);

        // UUID baru yang harus dipindah
        $uuidsToMove = [];
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if ($request->has($f) && is_array($request->input($f))) {
                $incoming = $request->input($f);
                $existing = $item->getAttribute($f) ?? [];
                $diff     = array_diff($incoming, is_array($existing) ? $existing : []);
                if (!empty($diff)) {
                    $uuidsToMove = array_merge($uuidsToMove, $diff);
                }
            }
        }
        if (!empty($uuidsToMove)) {
            $this->moveTempToFinalUuids($uuidsToMove, (string) auth()->id());
        }

        // Payload update (jangan tulis kolom file kalau null dikirim → biar nilai lama tetap)
        $updateData = $validated;
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if (array_key_exists($f, $updateData) && is_null($updateData[$f])) {
                unset($updateData[$f]);
            }
        }

        // Catat field yang berubah
        $changedFields = [];
        foreach ($updateData as $key => $val) {
            if ($item->getAttribute($key) !== $val) {
                $changedFields[] = $key;
            }
        }

        if (!empty($updateData)) {
            $item->update($updateData);
        }

        $message = empty($changedFields)
            ? 'Tidak ada perubahan data'
            : 'Field Berikut Berhasil di Perbaharui: ' . implode(', ', $changedFields);

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    /**
     * DELETE /permukiman/{id}
     */
    public function destroy(string $id)
    {
        $data = Permukiman::where('id', $id)->first();

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ], 404);
        }

        $data->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil dihapus',
        ]);
    }

    // ====================== Helpers ======================

    /** Aliases camelCase → snake_case untuk field file-array & pesan_verifikasi */
    private function applyAliases(Request $request): void
    {
        $aliases = [
            'fotoSertifikatStatusTanah' => 'foto_sertifikat_status_tanah',
            'fotoSta0'                  => 'foto_sta0',
            'fotoSta100'                => 'foto_sta100',
            'suratPemohonan'            => 'surat_pemohonan',
            'proposalUsulan'            => 'proposal_usulan',
            // tambahan agar pesanVerifikasi ikut tersimpan
            'pesanVerifikasi'           => 'pesan_verifikasi',
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
     * - Array campur path: ['permukiman_temp/xx_uuid1.jpg','uuid2']
     * - "null"/'' → null
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

    /** Ekstrak UUID dari string (v1–v7, toleran) */
    private function extractUuid(string $value): ?string
    {
        if (preg_match('/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}/', $value, $m)) {
            return strtolower($m[0]);
        }
        return null;
    }

    /**
     * Pindahkan file dari TEMP → FINAL untuk daftar UUID (milik user yang sama).
     * Final path: permukiman_final/<basename_temp>
     */
    private function moveTempToFinalUuids(array $uuids, string $userId): void
    {
        $uuids = array_values(array_unique(array_filter($uuids)));
        if (!$uuids) return;

        $temps = PermukimanUploadTemp::whereIn('uuid', $uuids)
            ->where('user_id', $userId)
            ->get()
            ->keyBy('uuid');

        foreach ($uuids as $u) {
            $temp = $temps->get($u);
            if (!$temp) {
                // mungkin sudah final (reuse) → lewati
                continue;
            }

            $oldPath  = $temp->file_path;               // permukiman_temp/<hash>.<ext>
            $filename = basename($oldPath);
            $newPath  = 'permukiman_final/' . $filename;

            if (Storage::exists($oldPath)) {
                Storage::move($oldPath, $newPath);
            } elseif (!Storage::exists($newPath)) {
                continue;
            }

            PermukimanUpload::updateOrCreate(
                ['uuid' => $temp->uuid],
                ['user_id' => $userId, 'file_path' => $newPath]
            );

            $temp->delete();
        }
    }
}
