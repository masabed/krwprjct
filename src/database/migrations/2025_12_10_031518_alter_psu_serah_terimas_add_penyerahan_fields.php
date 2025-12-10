<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('psu_serah_terimas', function (Blueprint $table) {
            // Relasi ke perumahan
            if (!Schema::hasColumn('psu_serah_terimas', 'perumahanId')) {
                $table->uuid('perumahanId')->nullable()->after('id');
            }

            // Data pemohon
            if (!Schema::hasColumn('psu_serah_terimas', 'tipePengaju')) {
                $table->string('tipePengaju', 100)->nullable()->after('perumahanId');
            }
            if (!Schema::hasColumn('psu_serah_terimas', 'namaPemohon')) {
                $table->string('namaPemohon', 255)->nullable()->after('tipePengaju');
            }
            if (!Schema::hasColumn('psu_serah_terimas', 'nikPemohon')) {
                $table->string('nikPemohon', 100)->nullable()->after('namaPemohon');
            }
            if (!Schema::hasColumn('psu_serah_terimas', 'noKontak')) {
                $table->string('noKontak', 100)->nullable()->after('nikPemohon');
            }
            if (!Schema::hasColumn('psu_serah_terimas', 'email')) {
                $table->string('email', 255)->nullable()->after('noKontak');
            }

            // Data developer
            if (!Schema::hasColumn('psu_serah_terimas', 'jenisDeveloper')) {
                $table->string('jenisDeveloper', 100)->nullable()->after('email');
            }
            if (!Schema::hasColumn('psu_serah_terimas', 'namaDeveloper')) {
                $table->string('namaDeveloper', 255)->nullable()->after('jenisDeveloper');
            }
            if (!Schema::hasColumn('psu_serah_terimas', 'alamatDeveloper')) {
                $table->string('alamatDeveloper', 500)->nullable()->after('namaDeveloper');
            }
            if (!Schema::hasColumn('psu_serah_terimas', 'rtDeveloper')) {
                $table->string('rtDeveloper', 10)->nullable()->after('alamatDeveloper');
            }
            if (!Schema::hasColumn('psu_serah_terimas', 'rwDeveloper')) {
                $table->string('rwDeveloper', 10)->nullable()->after('rtDeveloper');
            }

            // Administratif
            if (!Schema::hasColumn('psu_serah_terimas', 'tanggalPengusulan')) {
                $table->date('tanggalPengusulan')->nullable()->after('rwDeveloper');
            }
            if (!Schema::hasColumn('psu_serah_terimas', 'tahapanPenyerahan')) {
                $table->string('tahapanPenyerahan', 100)->nullable()->after('tanggalPengusulan');
            }
            if (!Schema::hasColumn('psu_serah_terimas', 'jenisPSU')) {
                $table->json('jenisPSU')->nullable()->after('tahapanPenyerahan');
            }
            if (!Schema::hasColumn('psu_serah_terimas', 'nomorSiteplan')) {
                $table->string('nomorSiteplan', 150)->nullable()->after('jenisPSU');
            }
            if (!Schema::hasColumn('psu_serah_terimas', 'tanggalSiteplan')) {
                $table->date('tanggalSiteplan')->nullable()->after('nomorSiteplan');
            }
            if (!Schema::hasColumn('psu_serah_terimas', 'noSuratPST')) {
                $table->string('noSuratPST', 150)->nullable()->after('tanggalSiteplan');
            }

            // Luasan
            if (!Schema::hasColumn('psu_serah_terimas', 'luasKeseluruhan')) {
                $table->string('luasKeseluruhan', 50)->nullable()->after('noSuratPST');
            }
            if (!Schema::hasColumn('psu_serah_terimas', 'luasRuangTerbangun')) {
                $table->string('luasRuangTerbangun', 50)->nullable()->after('luasKeseluruhan');
            }
            if (!Schema::hasColumn('psu_serah_terimas', 'luasRuangTerbuka')) {
                $table->string('luasRuangTerbuka', 50)->nullable()->after('luasRuangTerbangun');
            }

            // File: JSON array of UUID
            if (!Schema::hasColumn('psu_serah_terimas', 'dokumenIzinBangunan')) {
                $table->json('dokumenIzinBangunan')->nullable()->after('luasRuangTerbuka');
            }
            if (!Schema::hasColumn('psu_serah_terimas', 'dokumenIzinPemanfaatan')) {
                $table->json('dokumenIzinPemanfaatan')->nullable()->after('dokumenIzinBangunan');
            }
            if (!Schema::hasColumn('psu_serah_terimas', 'dokumenKondisi')) {
                $table->json('dokumenKondisi')->nullable()->after('dokumenIzinPemanfaatan');
            }
            if (!Schema::hasColumn('psu_serah_terimas', 'dokumenTeknis')) {
                $table->json('dokumenTeknis')->nullable()->after('dokumenKondisi');
            }
            if (!Schema::hasColumn('psu_serah_terimas', 'ktpPemohon')) {
                $table->json('ktpPemohon')->nullable()->after('dokumenTeknis');
            }
            if (!Schema::hasColumn('psu_serah_terimas', 'aktaPerusahaan')) {
                $table->json('aktaPerusahaan')->nullable()->after('ktpPemohon');
            }
            if (!Schema::hasColumn('psu_serah_terimas', 'suratPermohonanPenyerahan')) {
                $table->json('suratPermohonanPenyerahan')->nullable()->after('aktaPerusahaan');
            }
            if (!Schema::hasColumn('psu_serah_terimas', 'dokumenSiteplan')) {
                $table->json('dokumenSiteplan')->nullable()->after('suratPermohonanPenyerahan');
            }
            if (!Schema::hasColumn('psu_serah_terimas', 'salinanSertifikat')) {
                $table->json('salinanSertifikat')->nullable()->after('dokumenSiteplan');
            }

            // Non-file tambahan
            if (!Schema::hasColumn('psu_serah_terimas', 'noBASTPSU')) {
                $table->string('noBASTPSU', 255)->nullable()->after('salinanSertifikat');
            }

            // Verifikasi & audit
            if (!Schema::hasColumn('psu_serah_terimas', 'status_verifikasi_usulan')) {
                $table->unsignedTinyInteger('status_verifikasi_usulan')->default(0)->after('noBASTPSU');
            }
            if (!Schema::hasColumn('psu_serah_terimas', 'pesan_verifikasi')) {
                $table->string('pesan_verifikasi', 512)->nullable()->after('status_verifikasi_usulan');
            }
            if (!Schema::hasColumn('psu_serah_terimas', 'user_id')) {
                $table->string('user_id', 64)->nullable()->index()->after('pesan_verifikasi');
            }
        });
    }

    public function down(): void
    {
        Schema::table('psu_serah_terimas', function (Blueprint $table) {
            // Hapus balik kalau perlu (opsional, tapi aku tulis lengkap)
            $cols = [
                'perumahanId',
                'tipePengaju',
                'namaPemohon',
                'nikPemohon',
                'noKontak',
                'email',
                'jenisDeveloper',
                'namaDeveloper',
                'alamatDeveloper',
                'rtDeveloper',
                'rwDeveloper',
                'tanggalPengusulan',
                'tahapanPenyerahan',
                'jenisPSU',
                'nomorSiteplan',
                'tanggalSiteplan',
                'noSuratPST',
                'luasKeseluruhan',
                'luasRuangTerbangun',
                'luasRuangTerbuka',
                'dokumenIzinBangunan',
                'dokumenIzinPemanfaatan',
                'dokumenKondisi',
                'dokumenTeknis',
                'ktpPemohon',
                'aktaPerusahaan',
                'suratPermohonanPenyerahan',
                'dokumenSiteplan',
                'salinanSertifikat',
                'noBASTPSU',
                'status_verifikasi_usulan',
                'pesan_verifikasi',
                'user_id',
            ];

            foreach ($cols as $col) {
                if (Schema::hasColumn('psu_serah_terimas', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
