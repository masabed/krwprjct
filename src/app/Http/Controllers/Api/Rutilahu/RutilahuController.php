<?php

namespace App\Http\Controllers\Api\Rutilahu;

use App\Http\Controllers\Controller;
use App\Models\Rutilahu;
use App\Models\RutilahuUpload;
use App\Models\RutilahuUploadTemp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RutilahuController extends Controller
{
    /** POST /rutilahu/upload */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:4096',
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $uuid = (string) Str::uuid();
        $ext  = strtolower(
            $request->file('file')->getClientOriginalExtension()
            ?: $request->file('file')->extension()
            ?: 'bin'
        );

        $timestamp = now()->format('Ymd_His');
        $basename  = "{$timestamp}_{$uuid}.{$ext}";
        $path      = $request->file('file')->storeAs('rutilahu_temp', $basename, 'local');

        $temp = RutilahuUploadTemp::create([
            'uuid'      => $uuid,
            'user_id'   => $user->id,
            'file_path' => $path,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'uuid'      => $temp->uuid,
                'user_id'   => $temp->user_id,
                'file_path' => $temp->file_path,
            ],
        ], 201);
    }

    /** POST /rutilahu/create */
    public function store(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        // Aliases & normalisasi UUID array
        $this->applyAliases($request);
        foreach (['fotoKTP','fotoSuratTanah','fotoRumah','fotoKK','dokumentasiSurvey'] as $f) {
            $this->normalizeUuidArrayField($request, $f);
        }

        // Normalisasi nilai kosong → null untuk field nullable (kondisi/akses)
        $this->nullifyEmpty($request, [
            'kondisiPondasi','kondisiSloof','kondisiKolom','kondisiRingBalok',
            'kondisiRangkaAtap','kondisiDinding','kondisiLantai','kondisiPenutupAtap',
            'aksesAirMinum','aksesAirSanitasi','pesan_verifikasi'
        ]);

        $request->validate([
            'kecamatan'              => 'required|string|max:150',
            'kelurahan'              => 'required|string|max:150',
            'nama_CPCL'              => 'required|string|max:255',
            'nomorNIK'               => 'required|string|max:30',
            'nomorKK'                => 'required|string|max:30',
            'jumlahKeluarga'         => 'required|string|max:10',

            'alamatDusun'            => 'required|string|max:255',
            'alamatRT'               => 'required|string|max:10',
            'alamatRW'               => 'required|string|max:10',

            'umur'                   => 'required|string|max:10',
            'luasTanah'              => 'required|string|max:100',
            'luasBangunan'           => 'required|string|max:100',
            'pendidikanTerakhir'     => 'required|string|max:100',
            'pekerjaan'              => 'required|string|max:100',
            'besaranPenghasilan'     => 'required|string|max:50',
            'statusKepemilikanRumah' => 'required|string|max:50',
            'asetRumahLain'          => 'required|string|max:100',
            'asetTanahLain'          => 'required|string|max:100',
            'sumberPenerangan'       => 'required|string|max:100',
            'bantuanPerumahan'       => 'required|string|max:100',
            'jenisKawasan'           => 'required|string|max:100',
            'jenisProgram'           => 'required|string|max:100',
            'jenisKelamin'           => 'required|string|max:20',

            // Opsional
            'kondisiPondasi'         => 'nullable|string|max:255',
            'kondisiSloof'           => 'nullable|string|max:255',
            'kondisiKolom'           => 'nullable|string|max:255',
            'kondisiRingBalok'       => 'nullable|string|max:255',
            'kondisiRangkaAtap'      => 'nullable|string|max:255',
            'kondisiDinding'         => 'nullable|string|max:255',
            'kondisiLantai'          => 'nullable|string|max:255',
            'kondisiPenutupAtap'     => 'nullable|string|max:255',
            'aksesAirMinum'          => 'nullable|string|max:100',
            'aksesAirSanitasi'       => 'nullable|string|max:100',

            // FILE ARRAYS
            'fotoKTP'                => 'required|array|min:1|max:5',
            'fotoKTP.*'              => 'uuid',
            'fotoSuratTanah'         => 'required|array|min:1|max:5',
            'fotoSuratTanah.*'       => 'uuid',
            'fotoRumah'              => 'required|array|min:1|max:5',
            'fotoRumah.*'            => 'uuid',
            'fotoKK'                 => 'required|array|min:1|max:5',
            'fotoKK.*'               => 'uuid',

            'dokumentasiSurvey'      => 'nullable|array|max:5',
            'dokumentasiSurvey.*'    => 'uuid',

            'pesan_verifikasi'       => 'nullable|string|max:512',
        ]);

        $uuid = (string) Str::uuid();

        Rutilahu::create([
            'uuid'                   => $uuid,
            'kecamatan'              => $request->kecamatan,
            'kelurahan'              => $request->kelurahan,
            'nama_CPCL'              => $request->nama_CPCL,
            'nomorNIK'               => $request->nomorNIK,
            'nomorKK'                => $request->nomorKK,
            'jumlahKeluarga'         => $request->jumlahKeluarga,

            'alamatDusun'            => $request->alamatDusun,
            'alamatRT'               => $request->alamatRT,
            'alamatRW'               => $request->alamatRW,

            'umur'                   => $request->umur,
            'luasTanah'              => $request->luasTanah,
            'luasBangunan'           => $request->luasBangunan,
            'pendidikanTerakhir'     => $request->pendidikanTerakhir,
            'pekerjaan'              => $request->pekerjaan,
            'besaranPenghasilan'     => $request->besaranPenghasilan,
            'statusKepemilikanRumah' => $request->statusKepemilikanRumah,
            'asetRumahLain'          => $request->asetRumahLain,
            'asetTanahLain'          => $request->asetTanahLain,
            'sumberPenerangan'       => $request->sumberPenerangan,
            'bantuanPerumahan'       => $request->bantuanPerumahan,
            'jenisKawasan'           => $request->jenisKawasan,
            'jenisProgram'           => $request->jenisProgram,
            'jenisKelamin'           => $request->jenisKelamin,

            // Arrays (JSON via casts)
            'fotoKTP'                => $request->fotoKTP,
            'fotoKK'                 => $request->fotoKK,
            'fotoSuratTanah'         => $request->fotoSuratTanah,
            'fotoRumah'              => $request->fotoRumah,
            'dokumentasiSurvey'      => $request->dokumentasiSurvey ?? [],

            // Opsional
            'kondisiPondasi'         => $request->kondisiPondasi,
            'kondisiSloof'           => $request->kondisiSloof,
            'kondisiKolom'           => $request->kondisiKolom,
            'kondisiRingBalok'       => $request->kondisiRingBalok,
            'kondisiRangkaAtap'      => $request->kondisiRangkaAtap,
            'kondisiDinding'         => $request->kondisiDinding,
            'kondisiLantai'          => $request->kondisiLantai,
            'kondisiPenutupAtap'     => $request->kondisiPenutupAtap,
            'aksesAirMinum'          => $request->aksesAirMinum,
            'aksesAirSanitasi'       => $request->aksesAirSanitasi,

            'pesan_verifikasi'       => $request->pesan_verifikasi,
            'statusVerifikasi'       => 0,
            'user_id'                => $user->id,
        ]);

        // Pindahkan file dari TEMP → FINAL
        $allUuids = array_unique(array_merge(
            $request->fotoKTP,
            $request->fotoSuratTanah,
            $request->fotoRumah,
            $request->fotoKK,
            $request->dokumentasiSurvey ?? []
        ));
        $this->moveTempsToFinal($allUuids, (string) $user->id);

        return response()->json([
            'success' => true,
            'message' => 'Usulan Rutilahu berhasil disimpan',
            'uuid'    => $uuid,
        ], 201);
    }

    /** POST /rutilahu/update/{uuid} */
    public function update(Request $request, string $uuid)
{
    $item = Rutilahu::where('uuid', $uuid)->first();
    if (!$item) {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
    }

    $this->applyAliases($request);

    $fileFields = ['fotoKTP','fotoSuratTanah','fotoRumah','fotoKK','dokumentasiSurvey'];

    // Normalisasi input file (boleh JSON/CSV/single/path → array UUID)
    foreach ($fileFields as $f) {
        $this->normalizeUuidArrayField($request, $f);
    }

    // Kosong → null untuk field nullable (kondisi/akses/pesan)
    $this->nullifyEmpty($request, [
        'kondisiPondasi','kondisiSloof','kondisiKolom','kondisiRingBalok',
        'kondisiRangkaAtap','kondisiDinding','kondisiLantai','kondisiPenutupAtap',
        'aksesAirMinum','aksesAirSanitasi','pesan_verifikasi'
    ]);

    $validated = $request->validate([
        'kecamatan'              => 'sometimes|string|max:150',
        'kelurahan'              => 'sometimes|string|max:150',
        'nama_CPCL'              => 'sometimes|string|max:255',
        'nomorNIK'               => 'sometimes|string|max:30',
        'nomorKK'                => 'sometimes|string|max:30',
        'jumlahKeluarga'         => 'sometimes|string|max:10',
        'alamatDusun'            => 'sometimes|string|max:255',
        'alamatRT'               => 'sometimes|string|max:10',
        'alamatRW'               => 'sometimes|string|max:10',
        'umur'                   => 'sometimes|string|max:10',
        'luasTanah'              => 'sometimes|string|max:100',
        'luasBangunan'           => 'sometimes|string|max:100',
        'pendidikanTerakhir'     => 'sometimes|string|max:100',
        'pekerjaan'              => 'sometimes|string|max:100',
        'besaranPenghasilan'     => 'sometimes|string|max:50',
        'statusKepemilikanRumah' => 'sometimes|string|max:50',
        'asetRumahLain'          => 'sometimes|string|max:100',
        'asetTanahLain'          => 'sometimes|string|max:100',
        'sumberPenerangan'       => 'sometimes|string|max:100',
        'bantuanPerumahan'       => 'sometimes|string|max:100',
        'jenisKawasan'           => 'sometimes|string|max:100',
        'jenisProgram'           => 'sometimes|string|max:100',
        'jenisKelamin'           => 'sometimes|string|max:20',

        'kondisiPondasi'         => 'sometimes|nullable|string|max:255',
        'kondisiSloof'           => 'sometimes|nullable|string|max:255',
        'kondisiKolom'           => 'sometimes|nullable|string|max:255',
        'kondisiRingBalok'       => 'sometimes|nullable|string|max:255',
        'kondisiRangkaAtap'      => 'sometimes|nullable|string|max:255',
        'kondisiDinding'         => 'sometimes|nullable|string|max:255',
        'kondisiLantai'          => 'sometimes|nullable|string|max:255',
        'kondisiPenutupAtap'     => 'sometimes|nullable|string|max:255',
        'aksesAirMinum'          => 'sometimes|nullable|string|max:100',
        'aksesAirSanitasi'       => 'sometimes|nullable|string|max:100',

        // FILE arrays (nullable → kalau null, abaikan / tidak mengganti)
        'fotoKTP'                => 'sometimes|nullable|array|min:1|max:5',
        'fotoKTP.*'              => 'uuid',
        'fotoSuratTanah'         => 'sometimes|nullable|array|min:1|max:5',
        'fotoSuratTanah.*'       => 'uuid',
        'fotoRumah'              => 'sometimes|nullable|array|min:1|max:5',
        'fotoRumah.*'            => 'uuid',
        'fotoKK'                 => 'sometimes|nullable|array|min:1|max:5',
        'fotoKK.*'               => 'uuid',
        'dokumentasiSurvey'      => 'sometimes|nullable|array|max:5',
        'dokumentasiSurvey.*'    => 'uuid',

        'dokumentasiSurvey_text' => 'sometimes|nullable|string|max:1000',
        'statusVerifikasi'       => 'sometimes|integer|in:0,1,2,3,4',
        'pesan_verifikasi'       => 'sometimes|nullable|string|max:512',
    ]);

    // Hitung UUID baru (pindah temp→final) & UUID lama yang dihapus (hanya kolom yang DIKIRIM)
    $uuidsToMove   = [];
    $removedUuids  = [];
    foreach ($fileFields as $f) {
        if ($request->has($f)) {
            $incoming = $request->input($f);          // boleh null/array
            $existing = $item->getAttribute($f) ?? []; // array

            if (is_array($incoming)) {
                // baru → pindah dari temp
                $diffNew = array_diff($incoming, is_array($existing) ? $existing : []);
                if ($diffNew) $uuidsToMove = array_merge($uuidsToMove, $diffNew);

                // lama yang tidak lagi ada pada kolom tsb → hapus
                $diffRemoved = array_diff(is_array($existing) ? $existing : [], $incoming);
                if ($diffRemoved) $removedUuids = array_merge($removedUuids, $diffRemoved);
            }
            // incoming null → abaikan (tidak mengganti nilai lama, jadi tidak ada yang dihapus)
        }
    }

    if ($uuidsToMove) {
        $this->moveTempsToFinal(array_values(array_unique($uuidsToMove)), (string) $item->user_id);
    }

    // Siapkan payload: jika kolom file dikirim null → jangan update (biarkan nilai lama)
    $updateData = $validated;
    foreach ($fileFields as $f) {
        if (array_key_exists($f, $updateData) && is_null($updateData[$f])) {
            unset($updateData[$f]);
        }
    }

    // Catat field berubah
    $changedFields = [];
    foreach ($updateData as $k => $v) {
        if ($item->getAttribute($k) !== $v) {
            $changedFields[] = $k;
        }
    }

    if ($updateData) {
        $item->update($updateData);
    }

    // Hapus file FINAL & row upload yang lama — hanya untuk kolom yang diupdate
    if ($removedUuids) {
        $this->deleteFinalFiles(array_values(array_unique($removedUuids)));
    }

    return response()->json([
        'success' => true,
        'message' => empty($changedFields)
            ? 'Tidak ada perubahan data'
            : 'Field Berikut Berhasil di Perbaharui: ' . implode(', ', $changedFields),
        'uuid'    => $item->uuid,
    ]);
}

    /** GET /rutilahu */
    public function index()
    {
        $list = Rutilahu::latest()->get()->map(function ($item) {
            return [
                'uuid'                   => $item->uuid,
                'kecamatan'              => $item->kecamatan,
                'kelurahan'              => $item->kelurahan,
                'nama_CPCL'              => $item->nama_CPCL,
                'nomorNIK'               => $item->nomorNIK,
                'nomorKK'                => $item->nomorKK,
                'jumlahKeluarga'         => $item->jumlahKeluarga,

                'alamatDusun'            => $item->alamatDusun,
                'alamatRT'               => $item->alamatRT,
                'alamatRW'               => $item->alamatRW,

                'umur'                   => $item->umur,
                'luasTanah'              => $item->luasTanah,
                'luasBangunan'           => $item->luasBangunan,
                'pendidikanTerakhir'     => $item->pendidikanTerakhir,
                'pekerjaan'              => $item->pekerjaan,
                'besaranPenghasilan'     => $item->besaranPenghasilan,
                'statusKepemilikanRumah' => $item->statusKepemilikanRumah,
                'asetRumahLain'          => $item->asetRumahLain,
                'asetTanahLain'          => $item->asetTanahLain,
                'sumberPenerangan'       => $item->sumberPenerangan,
                'bantuanPerumahan'       => $item->bantuanPerumahan,
                'jenisKawasan'           => $item->jenisKawasan,
                'jenisProgram'           => $item->jenisProgram,
                'jenisKelamin'           => $item->jenisKelamin,

                // Kondisi & akses
                'kondisiPondasi'         => $item->kondisiPondasi,
                'kondisiSloof'           => $item->kondisiSloof,
                'kondisiKolom'           => $item->kondisiKolom,
                'kondisiRingBalok'       => $item->kondisiRingBalok,
                'kondisiRangkaAtap'      => $item->kondisiRangkaAtap,
                'kondisiDinding'         => $item->kondisiDinding,
                'kondisiLantai'          => $item->kondisiLantai,
                'kondisiPenutupAtap'     => $item->kondisiPenutupAtap,
                'aksesAirMinum'          => $item->aksesAirMinum,
                'aksesAirSanitasi'       => $item->aksesAirSanitasi,

                // Arrays UUID
                'fotoKTP'                => $item->fotoKTP,
                'fotoSuratTanah'         => $item->fotoSuratTanah,
                'fotoRumah'              => $item->fotoRumah,
                'fotoKK'                 => $item->fotoKK,
                'dokumentasiSurvey'      => $item->dokumentasiSurvey,

                'pesan_verifikasi'       => $item->pesan_verifikasi,
                'statusVerifikasi'       => $item->statusVerifikasi,
                'created_at'             => $item->created_at,
                'updated_at'             => $item->updated_at,
            ];
        });

        return response()->json(['success' => true, 'data' => $list]);
    }

    /** DELETE /rutilahu/{uuid} */
    public function destroy(string $uuid)
    {
        if (!auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $item = Rutilahu::where('uuid', $uuid)->first();
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil dihapus',
            'uuid'    => $uuid,
        ]);
    }

    /** GET /rutilahu/{uuid} */
    public function show($uuid)
    {
        $item = Rutilahu::where('uuid', $uuid)->first();
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'usulan' => [
                    'uuid'                   => $item->uuid,
                    'kecamatan'              => $item->kecamatan,
                    'kelurahan'              => $item->kelurahan,
                    'nama_CPCL'              => $item->nama_CPCL,
                    'nomorNIK'               => $item->nomorNIK,
                    'nomorKK'                => $item->nomorKK,
                    'jumlahKeluarga'         => $item->jumlahKeluarga,

                    'alamatDusun'            => $item->alamatDusun,
                    'alamatRT'               => $item->alamatRT,
                    'alamatRW'               => $item->alamatRW,

                    'umur'                   => $item->umur,
                    'luasTanah'              => $item->luasTanah,
                    'luasBangunan'           => $item->luasBangunan,
                    'pendidikanTerakhir'     => $item->pendidikanTerakhir,
                    'pekerjaan'              => $item->pekerjaan,
                    'besaranPenghasilan'     => $item->besaranPenghasilan,
                    'statusKepemilikanRumah' => $item->statusKepemilikanRumah,
                    'asetRumahLain'          => $item->asetRumahLain,
                    'asetTanahLain'          => $item->asetTanahLain,
                    'sumberPenerangan'       => $item->sumberPenerangan,
                    'bantuanPerumahan'       => $item->bantuanPerumahan,
                    'jenisKawasan'           => $item->jenisKawasan,
                    'jenisProgram'           => $item->jenisProgram,
                    'jenisKelamin'           => $item->jenisKelamin,

                    // Kondisi & akses
                    'kondisiPondasi'         => $item->kondisiPondasi,
                    'kondisiSloof'           => $item->kondisiSloof,
                    'kondisiKolom'           => $item->kondisiKolom,
                    'kondisiRingBalok'       => $item->kondisiRingBalok,
                    'kondisiRangkaAtap'      => $item->kondisiRangkaAtap,
                    'kondisiDinding'         => $item->kondisiDinding,
                    'kondisiLantai'          => $item->kondisiLantai,
                    'kondisiPenutupAtap'     => $item->kondisiPenutupAtap,
                    'aksesAirMinum'          => $item->aksesAirMinum,
                    'aksesAirSanitasi'       => $item->aksesAirSanitasi,

                    // Arrays UUID
                    'fotoKTP'                => $item->fotoKTP,
                    'fotoSuratTanah'         => $item->fotoSuratTanah,
                    'fotoRumah'              => $item->fotoRumah,
                    'fotoKK'                 => $item->fotoKK,
                    'dokumentasiSurvey'      => $item->dokumentasiSurvey,

                    'pesan_verifikasi'       => $item->pesan_verifikasi,
                    'statusVerifikasi'       => $item->statusVerifikasi,
                    'created_at'             => $item->created_at,
                ]
            ]
        ]);
    }

    // ================= Helpers =================

    /** Aliases snake_case → camelCase + pesan & jenisKelamin + kondisi/akses */
    private function applyAliases(Request $request): void
    {
        $aliases = [
            // file arrays
            'foto_ktp'               => 'fotoKTP',
            'foto_surat_tanah'       => 'fotoSuratTanah',
            'foto_rumah'             => 'fotoRumah',
            'foto_kk'                => 'fotoKK',
            'dokumentasi_survey'     => 'dokumentasiSurvey',

            // lainnya
            'pesanVerifikasi'        => 'pesan_verifikasi',
            'jenis_kelamin'          => 'jenisKelamin',

            // KONDISI (snake → camel)
            'kondisi_pondasi'        => 'kondisiPondasi',
            'kondisi_sloof'          => 'kondisiSloof',
            'kondisi_kolom'          => 'kondisiKolom',
            'kondisi_ring_balok'     => 'kondisiRingBalok',
            'kondisi_rangka_atap'    => 'kondisiRangkaAtap',
            'kondisi_dinding'        => 'kondisiDinding',
            'kondisi_lantai'         => 'kondisiLantai',
            'kondisi_penutup_atap'   => 'kondisiPenutupAtap',

            // AKSES
            'akses_air_minum'        => 'aksesAirMinum',
            'akses_air_sanitasi'     => 'aksesAirSanitasi',
        ];

        $merge = [];
        foreach ($aliases as $from => $to) {
            if ($request->has($from) && !$request->has($to)) {
                $merge[$to] = $request->input($from);
            }
        }
        if ($merge) $request->merge($merge);
    }

    /** Normalisasi input array-UUID dari berbagai bentuk (JSON/CSV/single/path). */
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

    /** Ubah "", "null", whitespace → null untuk daftar field (nullable). */
    private function nullifyEmpty(Request $request, array $fields): void
    {
        $merge = [];
        foreach ($fields as $f) {
            if ($request->has($f)) {
                $v = $request->input($f);
                if ($v === '' || $v === 'null' || (is_string($v) && trim($v) === '')) {
                    $merge[$f] = null;
                }
            }
        }
        if ($merge) $request->merge($merge);
    }

    /** Ekstrak UUID v1–v5 dari string/path. */
    private function extractUuid(string $value): ?string
    {
        if (preg_match('/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}/', $value, $m)) {
            return strtolower($m[0]);
        }
        return null;
    }

    private function deleteFinalFiles(array $uuids): void
{
    $uuids = array_values(array_unique(array_filter($uuids)));
    if (!$uuids) return;

    foreach ($uuids as $u) {
        $upload = RutilahuUpload::where('uuid', $u)->first();
        if ($upload) {
            if ($upload->file_path && Storage::exists($upload->file_path)) {
                Storage::delete($upload->file_path);
            }
            $upload->delete();
        }
    }
}
    /** Pindahkan file dari TEMP → FINAL untuk daftar UUID (user yang sama). */
    private function moveTempsToFinal(array $fileUuids, string $userId): void
    {
        $fileUuids = array_values(array_unique(array_filter($fileUuids)));
        if (!$fileUuids) return;

        $temps = RutilahuUploadTemp::whereIn('uuid', $fileUuids)
            ->where('user_id', $userId)
            ->get()
            ->keyBy('uuid');

        foreach ($fileUuids as $u) {
            $temp = $temps->get($u);
            if (!$temp) {
                // mungkin sudah final (reuse)
                continue;
            }

            $oldPath = $temp->file_path; // rutilahu_temp/<timestamp>_<uuid>.<ext>
            $ext     = strtolower(pathinfo($oldPath, PATHINFO_EXTENSION) ?: 'bin');
            $newPath = 'rutilahu_final/'.$u.'.'.$ext;

            if (Storage::exists($oldPath)) {
                Storage::move($oldPath, $newPath);
            } elseif (!Storage::exists($newPath)) {
                continue;
            }

            RutilahuUpload::updateOrCreate(
                ['uuid' => $u],
                ['user_id' => $userId, 'file_path' => $newPath]
            );

            $temp->delete();
        }
        
    }
}
