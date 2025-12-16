<?php

namespace App\Http\Controllers\Api\Pembangunan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pembangunan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// Model usulan yang direferensikan (sesuaikan dengan yang ada di project kamu)
use App\Models\UsulanFisikBSL;
use App\Models\PSUUsulanFisikPerumahan;
use App\Models\PSUUsulanFisikTPU;
use App\Models\PSUUsulanFisikPJL;
use App\Models\PsuSerahTerima;
use App\Models\Rutilahu;
use App\Models\Permukiman;
use App\Models\UsulanSAPDSIndividual;
use App\Models\UsulanSAPDSFasilitasUmum;
use App\Models\SAPDLahanMasyarakat;
use App\Models\User;

class PembangunanController extends Controller
{
    /**
     * GET /api/pembangunan
     * Ambil semua data pembangunan (tanpa filter nomorSPK).
     */
public function index(Request $request)
{
    // Ambil semua pembangunan (terbaru dulu)
    $rows = \App\Models\Pembangunan::query()->latest()->get();

    // Kumpulkan semua pengawas untuk batch-lookup
    $pengawasKeys = $rows->pluck('pengawasLapangan')
        ->filter(fn ($v) => !empty($v))
        ->map(fn ($v) => (string) $v)
        ->unique()
        ->values();

    $usersById   = collect();
    $usersByUuid = collect();

    if ($pengawasKeys->isNotEmpty() && class_exists(\App\Models\User::class)) {
        try {
            $usersById = \App\Models\User::query()
                ->select('id', 'name', 'username')
                ->whereIn('id', $pengawasKeys)
                ->get()
                ->keyBy(fn ($u) => (string) $u->id);
        } catch (\Throwable $e) {
            $usersById = collect();
        }

        try {
            $userTable = (new \App\Models\User)->getTable();
            if (\Illuminate\Support\Facades\Schema::hasColumn($userTable, 'uuid')) {
                $usersByUuid = \App\Models\User::query()
                    ->select('uuid', 'name', 'username')
                    ->whereIn('uuid', $pengawasKeys)
                    ->get()
                    ->keyBy(fn ($u) => (string) $u->uuid);
            }
        } catch (\Throwable $e) {
            $usersByUuid = collect();
        }
    }

    // Helper: normalisasi kolom uuidUsulan (array / json string / single string) → array string
    $normalizeUuidList = function ($raw) {
        if (is_array($raw)) return array_values(array_filter($raw, fn($x) => trim((string)$x) !== ''));
        if (is_string($raw)) {
            $dec = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($dec)) {
                return array_values(array_filter($dec, fn($x) => trim((string)$x) !== ''));
            }
            // fallback: single string
            $raw = trim($raw);
            return $raw === '' ? [] : [$raw];
        }
        return [];
    };

    $data = $rows->map(function ($b) use ($usersById, $usersByUuid, $normalizeUuidList) {
        $uuids = $normalizeUuidList($b->uuidUsulan);
        $uuCount = count(array_values(array_unique($uuids)));

        $pengawasKey = (string) ($b->pengawasLapangan ?? '');
        $pengawasName = null;
        if ($pengawasKey !== '') {
            if ($usersById->has($pengawasKey)) {
                $u = $usersById->get($pengawasKey);
                $pengawasName = $u->name ?? $u->username ?? null;
            } elseif ($usersByUuid->has($pengawasKey)) {
                $u = $usersByUuid->get($pengawasKey);
                $pengawasName = $u->name ?? $u->username ?? null;
            }
        }

        // Susun payload TANPA unit & user_id
        return [
            'id'                   => (string) $b->id,
            'uuidUsulan'           => $uuids,                // tetap tampilkan daftar UUID usulan
            'nomorSPK'             => $b->nomorSPK,
            'tanggalSPK'           => $b->tanggalSPK,
            'nilaiKontrak'         => $b->nilaiKontrak,
            'kontraktorPelaksana'  => $b->kontraktorPelaksana,
            'tanggalMulai'         => $b->tanggalMulai,
            'tanggalSelesai'       => $b->tanggalSelesai,
            'jangkaWaktu'          => $b->jangkaWaktu,
            'pengawasLapangan'     => $b->pengawasLapangan,
            'pengawasLapangan_name'=> $pengawasName,
            'uuidUsulan_count'     => $uuCount,
            'created_at'           => $b->created_at,
            'updated_at'           => $b->updated_at,
        ];
    });

    return response()->json([
        'success' => true,
        'data'    => $data,
    ]);
}


   public function indexDedupSpk(Request $request)
{
    // Ambil semua pembangunan (urut terbaru dulu)
    $rows = Pembangunan::query()->latest()->get();

    $data = $rows->map(function ($row) {
        $uu = $row->uuidUsulan;
        $list = [];

        // Normalisasi uuidUsulan → array string
        if (is_array($uu)) {
            $list = $uu;
        } elseif (is_string($uu)) {
            $t = trim($uu);
            if ($t === '') {
                $list = [];
            } else {
                // Jika json array: "[...]" → decode; kalau bukan, anggap single uuid
                if ($t[0] === '[') {
                    $decoded = json_decode($t, true);
                    $list = is_array($decoded) ? $decoded : [$t];
                } else {
                    $list = [$t];
                }
            }
        } elseif (is_object($uu)) {
            $list = (array) $uu;
        }

        // Bersihkan elemen kosong & pastikan string
        $list = array_values(array_filter(array_map('strval', $list), function ($v) {
            return trim($v) !== '';
        }));

        // Hitung jumlah elemen (kalau mau unik, ganti ke count(array_unique($list)))
        $count = count($list);

        return [
            'uuidPembangunan'  => (string) $row->id,
            'nomorSPK'         => $row->nomorSPK,
            'uuidUsulan_count' => $count,
        ];
    });

    return response()->json([
        'success' => true,
        'data'    => $data,
    ]);
}


    /**
     * GET /api/pembangunan/{id}
     */
   public function show(string $id)
{
    $row = Pembangunan::find($id);

    if (!$row) {
        return response()->json([
            'success' => false,
            'message' => 'Data tidak ditemukan',
        ], 404);
    }

    // --- Resolve nama pengawas dari tabel users (by id atau uuid) ---
    $pengawasName = null;
    try {
        $pengawasKey = trim((string) $row->pengawasLapangan);
        if ($pengawasKey !== '' && class_exists(\App\Models\User::class)) {
            // Coba cocokkan ke kolom PK "id"
            $user = \App\Models\User::query()
                ->select('id', 'name', 'username')
                ->where('id', $pengawasKey)
                ->first();

            // Jika tidak ketemu & tabel users punya kolom 'uuid', coba via uuid
            if (!$user) {
                $userTable = (new \App\Models\User)->getTable();
                if (\Illuminate\Support\Facades\Schema::hasColumn($userTable, 'uuid')) {
                    $user = \App\Models\User::query()
                        ->select('uuid', 'name', 'username')
                        ->where('uuid', $pengawasKey)
                        ->first();
                }
            }

            if ($user) {
                $pengawasName = $user->name ?? $user->username ?? null;
            }
        }
    } catch (\Throwable $e) {
        // biarkan null jika gagal resolve
    }

    // --- Hitung jumlah uuidUsulan DARI ROW INI (bukan lagi via SPK) ---
    $uuidList = [];
    $raw = $row->uuidUsulan;

    if (is_array($raw)) {
        $uuidList = $raw;
    } elseif (is_string($raw) && $raw !== '') {
        // Jika tersimpan sebagai JSON string
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $uuidList = $decoded;
        } else {
            // fallback: single value string
            $uuidList = [$raw];
        }
    }

    // Normalisasi & uniq
    $uuidUsulan_count = collect($uuidList)
        ->map(fn($v) => trim((string)$v))
        ->filter(fn($v) => $v !== '')
        ->unique()
        ->count();

    // Susun payload, hilangkan 'user_id' & 'unit'
    $data = $row->toArray();
    unset($data['user_id'], $data['unit']);

    // Tambahan field
    $data['pengawasLapangan_name'] = $pengawasName;
    $data['uuidUsulan_count']      = $uuidUsulan_count;

    return response()->json([
        'success' => true,
        'data'    => $data,
    ]);
}

    /**
     * POST /api/pembangunan/create
     * Wajib: uuidUsulan yang status_verifikasi_usulan ∈ {6,7}
     * Catatan: nomorSPK TIDAK unik (boleh duplikat).
     */
   // === ADMIN ONLY ===
// POST /api/pembangunan/create
// === ADMIN ONLY ===
// POST /api/pembangunan/create
public function store(Request $request)
{
    $auth = $request->user();
if (!$auth) {
    return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
}

$role = strtolower((string) ($auth->role ?? ''));

if (!in_array($role, ['admin', 'operator'], true)) {
    return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
}

    // Hanya SATU uuidUsulan (string uuid), bukan array
    $rules = [
        'uuidUsulan'          => ['required','uuid'],
        'nomorSPK'            => ['required','string','max:150'], // tetap required karena kolom DB tidak nullable
        'tanggalSPK'          => ['sometimes','nullable','date'],
        'nilaiKontrak'        => ['sometimes','nullable','string','max:100'],
        'kontraktorPelaksana' => ['sometimes','nullable','string','max:255'],
        'tanggalMulai'        => ['sometimes','nullable','date'],
        'tanggalSelesai'      => ['sometimes','nullable','date'],
        'jangkaWaktu'         => ['sometimes','nullable','string','max:100'],
        'pengawasLapangan'    => ['sometimes','nullable','string','max:255'],
    ];
    $validated = $request->validate($rules, [], ['nomorSPK' => 'nomor SPK']);

    // Tolak jika uuidUsulan sudah ada (harus unik per usulan)
    if (Pembangunan::where('uuidUsulan', $validated['uuidUsulan'])->exists()) {
        return response()->json([
            'success'    => false,
            'message'    => 'Pembangunan untuk uuidUsulan tersebut sudah ada. Tidak boleh input duplikat.',
            'uuidUsulan' => $validated['uuidUsulan'],
        ], 422);
    }

    // Pastikan usulan tujuan ada
    $usulan = $this->findUsulanByUuid($validated['uuidUsulan']);
    if (!$usulan) {
        return response()->json([
            'success' => false,
            'message' => 'Usulan tidak ditemukan untuk uuidUsulan yang diberikan.',
        ], 422);
    }

    // Buat + naikkan status usulan -> 6 (clear pesan)
    $row = null;
    DB::transaction(function () use (&$row, $validated, $usulan) {
        $row = Pembangunan::create($validated);

        $table = $usulan->getTable();
        if (Schema::hasColumn($table, 'status_verifikasi_usulan')) {
            $usulan->status_verifikasi_usulan = 6;
            if (Schema::hasColumn($table, 'pesan_verifikasi')) {
                $usulan->pesan_verifikasi = null;
            }
            $usulan->save();
        }
    });

    return response()->json([
        'success' => true,
        'message' => 'Data pembangunan berhasil dibuat. Status usulan dinaikkan ke 6.',
        'data'    => $row,
    ], 201);
}

// === ADMIN ONLY ===
// POST /api/pembangunan/clone
// Body: { "uuidPembangunan": "<sumber id>", "uuidUsulan": "<tujuan uuid>" }
public function storeCloneFromExisting(Request $request)
{
    // === Auth & Role: ADMIN ONLY ===
    $auth = $request->user();
    if (!$auth) {
        return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
    }
    if (strtolower((string)($auth->role ?? '')) !== 'admin') {
        return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
    }

    // === Validasi input ===
    $validated = $request->validate([
        'uuidUsulan'      => ['required','uuid'],
        'uuidPembangunan' => ['required','uuid'],
    ], [], [
        'uuidUsulan'      => 'UUID Usulan',
        'uuidPembangunan' => 'UUID Pembangunan',
    ]);

    /** @var \App\Models\Pembangunan|null $row */
    $row = \App\Models\Pembangunan::find($validated['uuidPembangunan']);
    if (!$row) {
        return response()->json([
            'success' => false,
            'message' => 'uuidPembangunan tidak ditemukan.',
            'uuidPembangunan' => $validated['uuidPembangunan'],
        ], 422);
    }

    $targetUsulan = $this->findUsulanByUuid($validated['uuidUsulan']);
    if (!$targetUsulan) {
        return response()->json([
            'success' => false,
            'message' => 'Usulan tujuan tidak ditemukan untuk uuidUsulan yang diberikan.',
        ], 422);
    }

    // Normalizer: null|string(JSON)|string single|array -> array<string>
    $toArray = function ($val): array {
        if (is_null($val)) return [];
        if (is_array($val)) {
            return collect($val)->map(fn($v)=>(string)$v)->filter()->values()->all();
        }
        $s = trim((string)$val);
        if ($s === '') return [];
        if (str_starts_with($s, '[')) {
            $arr = json_decode($s, true);
            return is_array($arr)
                ? collect($arr)->map(fn($v)=>(string)$v)->filter()->values()->all()
                : [$s];
        }
        return [$s];
    };

    $incoming = (string) $validated['uuidUsulan'];
    $movedFrom = [];

    \DB::transaction(function () use (&$row, $incoming, $toArray, &$movedFrom, $targetUsulan) {
        // Lock row target
        $row = \App\Models\Pembangunan::whereKey($row->getKey())->lockForUpdate()->first();

        // 1) Cari baris pembangunan LAIN yang mengandung uuidUsulan ini
        //    - JSON (whereJsonContains)
        //    - legacy single string (=)
        //    - TEXT berisi JSON string (LIKE "%\"uuid\"%") dan fallback LIKE umum
        $others = \App\Models\Pembangunan::query()
            ->where('id', '!=', $row->getKey())
            ->whereNotNull('uuidUsulan')
            ->where(function($q) use ($incoming) {
                // JSON native
                $q->whereJsonContains('uuidUsulan', $incoming)
                  // legacy single
                  ->orWhere('uuidUsulan', $incoming)
                  // TEXT yang berisi JSON array string
                  ->orWhere('uuidUsulan', 'like', '%"'.$incoming.'"%')
                  // fallback sangat longgar (antisipasi format custom)
                  ->orWhere('uuidUsulan', 'like', '%'.$incoming.'%');
            })
            ->lockForUpdate()
            ->get();

        // 2) Hapus uuidUsulan dari baris-baris lain tsb (proses di PHP biar pasti)
        foreach ($others as $o) {
            $list = collect($toArray($o->uuidUsulan))
                ->reject(fn($v) => $v === $incoming)
                ->values()
                ->all();

            $o->uuidUsulan = count($list) ? $list : null; // kosong -> null biar rapi
            $o->save();
            $movedFrom[] = (string) $o->id;
        }

        // 3) Tambahkan ke baris TARGET (unik)
        $current = $toArray($row->uuidUsulan);
        if (!in_array($incoming, $current, true)) {
            $current[] = $incoming;
            $row->uuidUsulan = array_values(array_unique($current));
            $row->save();
        }

        // 4) Naikkan status usulan target -> 6 & clear pesan_verifikasi (jika kolom ada)
        $table = $targetUsulan->getTable();
        if (\Schema::hasColumn($table, 'status_verifikasi_usulan')) {
            $targetUsulan->status_verifikasi_usulan = 6;
            if (\Schema::hasColumn($table, 'pesan_verifikasi')) {
                $targetUsulan->pesan_verifikasi = null;
            }
            $targetUsulan->save();
        }
    });

    return response()->json([
        'success' => true,
        'message' => 'UUID usulan dipindahkan dari baris lain (jika ada) dan ditambahkan ke baris target. Status usulan dinaikkan ke 6.',
        'data'    => [
            'uuidUsulan'      => $incoming,
            'uuidPembangunan' => (string) $row->id,
            'moved_from_rows' => array_values(array_unique($movedFrom)),
            'target_row'      => $row->fresh(), // pastikan casts di model
        ],
    ], 201);
}


public function update(Request $request, string $id)
{
    // ==== Auth & Role Guard (ADMIN ONLY) ====
    $user = $request->user();
if (!$user) {
    return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
}

$role = strtolower((string) ($user->role ?? ''));
if (!in_array($role, ['admin', 'operator'], true)) {
    return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
}
// =============================================

$row = Pembangunan::find($id);
if (!$row) {
    return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
}

    // Normalisasi input uuidUsulan (boleh kirim string JSON atau "a,b,c")
    if ($request->has('uuidUsulan')) {
        $in = $request->input('uuidUsulan');

        if (is_string($in)) {
            $decoded = json_decode($in, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $request->merge(['uuidUsulan' => $decoded]);
            } else {
                $arr = array_values(array_filter(array_map('trim', explode(',', $in))));
                $request->merge(['uuidUsulan' => $arr]);
            }
        }
    }

    // VALIDASI (tanpa field 'unit', dan TIDAK boleh update user_id)
    $validated = $request->validate([
        'uuidUsulan'           => ['sometimes','array','min:1'],
        'uuidUsulan.*'         => ['uuid'],

        'nomorSPK'             => ['sometimes','string','max:150'],
        'tanggalSPK'           => ['sometimes','nullable','date'],
        'nilaiKontrak'         => ['sometimes','nullable','string','max:100'],
        'kontraktorPelaksana'  => ['sometimes','nullable','string','max:255'],
        'tanggalMulai'         => ['sometimes','nullable','date'],
        'tanggalSelesai'       => ['sometimes','nullable','date'],
        'jangkaWaktu'          => ['sometimes','nullable','string','max:100'],
        'pengawasLapangan'     => ['sometimes','nullable','string','max:255'],
    ], [], [
        'nomorSPK' => 'nomor SPK',
    ]);

    // Pastikan 'unit' diabaikan jika dikirim
    if (array_key_exists('unit', $validated)) {
        unset($validated['unit']);
    }

    $row->fill($validated);
    $dirty = $row->getDirty();

    if (empty($dirty)) {
        // Sanitasi output: sembunyikan user_id & unit
        $data = $row->toArray();
        unset($data['user_id'], $data['unit']);

        return response()->json([
            'success' => true,
            'message' => 'Tidak ada perubahan data',
            'data'    => $data,
            'changed' => [],
        ]);
    }

    $row->save();

    // Ambil data terbaru & sanitasi output
    $fresh = $row->fresh()->toArray();
    unset($fresh['user_id'], $fresh['unit']);

    return response()->json([
        'success' => true,
        'message' => 'Field berikut berhasil diperbarui: ' . implode(', ', array_keys($dirty)),
        'data'    => $fresh,
    ]);
}

    /**
     * DELETE /api/pembangunan/{id}
     */
    public function destroy(string $id)
    {
        $row = Pembangunan::find($id);
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $row->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil dihapus',
        ]);
    }

    // ============================== Helpers ==============================

    /**
     * Cari user by primary key/uuid/id (fleksibel).
     */
    private function findUserByAnyKey(string $value): ?User
    {
        $model = new User;
        $table = $model->getTable();

        // Coba pakai primary key model, lalu 'uuid', lalu 'id'
        $keysToTry = array_values(array_unique([
            $model->getKeyName(), // biasanya 'id'
            'uuid',
            'id',
        ]));

        foreach ($keysToTry as $col) {
            if (Schema::hasColumn($table, $col)) {
                $u = User::where($col, $value)->first();
                if ($u) return $u;
            }
        }
        return null;
    }

    /**
     * Cari usulan di beberapa tabel kandidat dengan kunci fleksibel:
     * - primary key model (getKeyName())
     * - 'uuid' (jika ada kolomnya)
     * - 'id'   (jika ada kolomnya)
     */
    private function findUsulanByUuid(string $value): ?object
    {
        $candidates = [
            UsulanFisikBSL::class,          // PK: id (UUID)
            PSUUsulanFisikPerumahan::class, // PK: mungkin 'uuid'
            PSUUsulanFisikTPU::class,       // PK: mungkin 'uuid'
            PSUUsulanFisikPJL::class,
            Permukiman::class,
            Rutilahu::class,
            SAPDLahanMasyarakat::class,
            UsulanSAPDSFasilitasUmum::class,
            UsulanSAPDSIndividual::class,
        ];

        foreach ($candidates as $modelClass) {
            if (!class_exists($modelClass)) continue;

            /** @var \Illuminate\Database\Eloquent\Model $instance */
            $instance = new $modelClass;
            $table = $instance->getTable();

            $keysToTry = array_values(array_unique([
                $instance->getKeyName(), // primary key dari model
                'uuid',
                'id',
            ]));

            foreach ($keysToTry as $col) {
                if (!Schema::hasColumn($table, $col)) continue;

                $found = $modelClass::where($col, $value)->first();
                if ($found) {
                    return $found;
                }
            }
        }

        return null;
    }
}
