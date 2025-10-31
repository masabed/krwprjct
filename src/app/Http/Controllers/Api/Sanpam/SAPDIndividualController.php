<?php

namespace App\Http\Controllers\Api\Sanpam;

use App\Http\Controllers\Controller;
use App\Models\UsulanSAPDSIndividual;
use App\Models\SAPDUpload;
use App\Models\SAPDUploadTemp;
use App\Models\Perencanaan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SAPDIndividualController extends Controller
{
    private const FILE_ARRAY_FIELDS = ['fotoRumah', 'fotoLahan'];

    /**
     * POST /api/sanpam/individual/upload
     * Upload file ke TEMP: return UUID file temp
     */
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
        ], 201);
    }

    /**
     * POST /api/sanpam/individual/submit
     * Create + pindahkan file-file dari TEMP → FINAL
     */
    public function submit(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        // Aliases & normalisasi array-UUID
        $this->applyAliases($request);
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            $this->normalizeUuidArrayField($request, $f);
        }

        $payload = $request->validate([
            'namaCalonPenerima'  => 'required|string',
            'nikCalonPenerima'   => 'required|string',
            'noKKCalonPenerima'  => 'required|string',
            'alamatPenerima'     => 'required|string',
            'rwPenerima'         => 'required|string',
            'rtPenerima'         => 'required|string',
            'kecamatan'          => 'required|string',
            'kelurahan'          => 'required|string',
            'ukuranLahan'        => 'nullable|string',
            'ketersedianSumber'  => 'required|string',
            'titikLokasi'        => 'nullable|string',
            'pesan_verifikasi'   => 'nullable|string|max:512',

            // FILE ARRAYS
            'fotoRumah'          => 'required|array|min:1|max:10',
            'fotoRumah.*'        => 'uuid',
            'fotoLahan'          => 'required|array|min:1|max:10',
            'fotoLahan.*'        => 'uuid',
        ]);

        $uuid = (string) Str::uuid();

        // Simpan data utama + user_id
        $data = UsulanSAPDSIndividual::create([
            'uuid'                     => $uuid,
            'user_id'                  => (string) $user->id,     // simpan pemilik
            'namaCalonPenerima'        => $payload['namaCalonPenerima'],
            'nikCalonPenerima'         => $payload['nikCalonPenerima'],
            'noKKCalonPenerima'        => $payload['noKKCalonPenerima'],
            'alamatPenerima'           => $payload['alamatPenerima'],
            'rwPenerima'               => $payload['rwPenerima'],
            'rtPenerima'               => $payload['rtPenerima'],
            'kecamatan'                => $payload['kecamatan'],
            'kelurahan'                => $payload['kelurahan'],
            'ukuranLahan'              => $payload['ukuranLahan'] ?? null,
            'ketersedianSumber'        => $payload['ketersedianSumber'],
            'titikLokasi'              => $payload['titikLokasi'] ?? null,
            'pesan_verifikasi'         => $payload['pesan_verifikasi'] ?? null,
            'fotoRumah'                => $payload['fotoRumah'],
            'fotoLahan'                => $payload['fotoLahan'],
            'status_verifikasi_usulan' => 0,
        ]);

        // Pindahkan file-file dari TEMP → FINAL
        $allUuids = array_values(array_unique(array_merge($payload['fotoRumah'], $payload['fotoLahan'])));
        $this->moveTempsToFinal($allUuids, (string) $user->id);

        return response()->json([
            'success' => true,
            'message' => 'Usulan SAPD Individual berhasil disimpan',
            'uuid'    => $uuid,
            'data'    => $data,
        ], 201);
    }

    /**
     * POST /api/sanpam/individual/update/{uuid}
     * Partial update; kolom file berupa ARRAY UUID
     */
    public function update(Request $request, string $uuid)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $item = UsulanSAPDSIndividual::where('uuid', $uuid)
            // ->where('user_id', $user->id) // aktifkan jika hanya pemilik boleh update
            ->first();
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        // Aliases & normalisasi file arrays
        $this->applyAliases($request);
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            $this->normalizeUuidArrayField($request, $f);
        }

        $validated = $request->validate([
            'namaCalonPenerima'        => 'sometimes|string',
            'nikCalonPenerima'         => 'sometimes|string',
            'noKKCalonPenerima'        => 'sometimes|string',
            'alamatPenerima'           => 'sometimes|string',
            'rwPenerima'               => 'sometimes|string',
            'rtPenerima'               => 'sometimes|string',
            'kecamatan'                => 'sometimes|string',
            'kelurahan'                => 'sometimes|string',
            'ukuranLahan'              => 'sometimes|nullable|string',
            'ketersedianSumber'        => 'sometimes|string',
            'titikLokasi'              => 'sometimes|nullable|string',
            'pesan_verifikasi'         => 'sometimes|nullable|string|max:512',

            'fotoRumah'                => 'sometimes|nullable|array|min:1|max:10',
            'fotoRumah.*'              => 'uuid',
            'fotoLahan'                => 'sometimes|nullable|array|min:1|max:10',
            'fotoLahan.*'              => 'uuid',

            'status_verifikasi_usulan' => 'sometimes|integer|in:0,1,2,3,4,5,6,7,8,9',
        ]);

        // Siapkan UUID baru yang perlu dipindah
        $uuidsToMove = [];
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if ($request->has($f) && is_array($request->input($f))) {
                $incoming = $request->input($f);
                $existing = $item->getAttribute($f) ?? [];
                $diff     = array_diff($incoming, is_array($existing) ? $existing : []);
                if ($diff) $uuidsToMove = array_merge($uuidsToMove, $diff);
            }
        }
        if ($uuidsToMove) {
            $this->moveTempsToFinal(array_values(array_unique($uuidsToMove)), (string) $user->id);
        }

        // Payload update (jika null dikirim untuk file-array, abaikan)
        $updateData = $validated;
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if (array_key_exists($f, $updateData) && is_null($updateData[$f])) {
                unset($updateData[$f]);
            }
        }

        // Catat field berubah
        $changed = [];
        foreach ($updateData as $k => $v) {
            if ($item->getAttribute($k) !== $v) $changed[] = $k;
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

    /**
     * DELETE /api/sanpam/individual/{uuid}
     */
    public function destroy(string $uuid)
    {
        $item = UsulanSAPDSIndividual::where('uuid', $uuid)
            // ->where('user_id', auth()->id()) // aktifkan jika hanya pemilik boleh hapus
            ->first();
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil dihapus',
        ]);
    }

    /**
     * GET /api/sanpam/individual
     * Tanpa parameter 'mine': selalu kembalikan semua data (urut terbaru)
     */
    public function index()
    {
        $list = UsulanSAPDSIndividual::latest()->get()->map(function ($item) {
            return [
                'uuid'                      => $item->uuid,
                'user_id'                   => $item->user_id,
                'namaCalonPenerima'         => $item->namaCalonPenerima,
                'nikCalonPenerima'          => $item->nikCalonPenerima,
                'noKKCalonPenerima'         => $item->noKKCalonPenerima,
                'alamatPenerima'            => $item->alamatPenerima,
                'rwPenerima'                => $item->rwPenerima,
                'rtPenerima'                => $item->rtPenerima,
                'kecamatan'                 => $item->kecamatan,
                'kelurahan'                 => $item->kelurahan,
                'ukuranLahan'               => $item->ukuranLahan,
                'ketersedianSumber'         => $item->ketersedianSumber,
                'titikLokasi'               => $item->titikLokasi,
                'pesan_verifikasi'          => $item->pesan_verifikasi,

                // arrays of UUIDs
                'fotoRumah'                 => $item->fotoRumah,
                'fotoLahan'                 => $item->fotoLahan,

                'status_verifikasi_usulan'  => $item->status_verifikasi_usulan,
                'created_at'                => $item->created_at,
            ];
        });

        return response()->json(['success' => true, 'data' => $list]);
    }

    /**
     * GET /api/sanpam/individual/{uuid}
     */
    public function show($uuid)
{
    $item = UsulanSAPDSIndividual::where('uuid', $uuid)
        // ->where('user_id', auth()->id()) // aktifkan kalau mau batasi hanya pemilik yg boleh lihat
        ->first();

    if (!$item) {
        return response()->json([
            'success' => false,
            'message' => 'Data tidak ditemukan'
        ], 404);
    }

    // Ambil semua data perencanaan yang terkait usulan ini
    $perencanaanRows = Perencanaan::where('uuidUsulan', $uuid)
        ->orderBy('created_at', 'desc')
        ->get();

    // Format rapi biar FE enak baca
    $perencanaanList = $perencanaanRows->map(function ($row) {
        return [
            'uuidPerencanaan' => $row->id,          // PK UUID dari tabel perencanaans
            'uuidUsulan'      => $row->uuidUsulan,  // harusnya sama kayak $uuid
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
                'uuid'                      => $item->uuid,
                'user_id'                   => $item->user_id,
                'namaCalonPenerima'         => $item->namaCalonPenerima,
                'nikCalonPenerima'          => $item->nikCalonPenerima,
                'noKKCalonPenerima'         => $item->noKKCalonPenerima,
                'alamatPenerima'            => $item->alamatPenerima,
                'rwPenerima'                => $item->rwPenerima,
                'rtPenerima'                => $item->rtPenerima,
                'kecamatan'                 => $item->kecamatan,
                'kelurahan'                 => $item->kelurahan,
                'ukuranLahan'               => $item->ukuranLahan,
                'ketersedianSumber'         => $item->ketersedianSumber,
                'titikLokasi'               => $item->titikLokasi,
                'pesan_verifikasi'          => $item->pesan_verifikasi,

                // ARRAY UUID file
                'fotoRumah'                 => $item->fotoRumah,
                'fotoLahan'                 => $item->fotoLahan,

                'status_verifikasi_usulan'  => $item->status_verifikasi_usulan,
                'created_at'                => $item->created_at,
                'updated_at'                => $item->updated_at,
            ],

            // daftar perencanaan yang terkait sama usulan ini
            'perencanaan' => $perencanaanList,
        ],
    ]);
}


    // ================= Helpers =================

    /** Terima juga variasi snake_case/camelCase di request */
    private function applyAliases(Request $request): void
    {
        $aliases = [
            'nama_calon_penerima'  => 'namaCalonPenerima',
            'nik_calon_penerima'   => 'nikCalonPenerima',
            'no_kk_calon_penerima' => 'noKKCalonPenerima',
            'alamat_penerima'      => 'alamatPenerima',
            'rw_penerima'          => 'rwPenerima',
            'rt_penerima'          => 'rtPenerima',
            'ketersedian_sumber'   => 'ketersedianSumber',
            'titik_lokasi'         => 'titikLokasi',
            'foto_rumah'           => 'fotoRumah',
            'foto_lahan'           => 'fotoLahan',
            'pesanVerifikasi'      => 'pesan_verifikasi',
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
     * Normalisasi satu field array-UUID:
     * - JSON array string: '["uuid1","uuid2"]'
     * - CSV string:        'uuid1,uuid2'
     * - Single UUID/path:  'sapd_temp/xxx_uuid1.jpg' → ['uuid1']
     * - Array campur path: ['sapd_temp/...uuid1.jpg','uuid2']
     * - "null"/''          → null
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

            if (str_starts_with($t, '[')) {
                $arr = json_decode($t, true);
                if (is_array($arr)) {
                    $uuids = [];
                    foreach ($arr as $v) {
                        $u = $this->extractUuid((string)$v);
                        if ($u) $uuids[] = $u;
                    }
                    $request->merge([$field => array_values(array_unique($uuids))]);
                }
                return;
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

    /** Ekstrak UUID (v1–v7) dari string/path */
    private function extractUuid(string $value): ?string
    {
        if (preg_match('/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}/', $value, $m)) {
            return strtolower($m[0]);
        }
        return null;
    }

    /** Pindahkan file dari TEMP → FINAL untuk daftar UUID (milik user yang sama) */
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
            if (!$temp) continue; // mungkin sudah final

            $oldPath  = $temp->file_path;
            $filename = basename($oldPath);
            $newPath  = 'sapd_final/' . $filename;

            // Hindari overwrite bila kebetulan sama
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

    /** Resolve array UUID → array of ['uuid' => ..., 'path' => '/storage/...'] */
    private function resolveFilePaths(?array $uuids): array
    {
        if (!is_array($uuids) || empty($uuids)) return [];
        $files = SAPDUpload::whereIn('uuid', $uuids)->get()->keyBy('uuid');

        $out = [];
        foreach ($uuids as $u) {
            $path = optional($files->get($u))->file_path;
            $out[] = [
                'uuid' => $u,
                'path' => $path ? '/storage/' . ltrim($path, '/') : null,
            ];
        }
        return $out;
    }
}
