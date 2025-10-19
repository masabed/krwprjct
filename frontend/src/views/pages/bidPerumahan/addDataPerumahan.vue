<!-- src/pages/bidPerumahan/AddDataPerumahan.vue -->
<template>
  <Layout>
    <PageHeader
      :title="'Tambah Data Perumahan'"
      :items="[
        { text: 'Menu' },
        { text: 'Bidang Perumahan', href: '/perumahan' },
        { text: 'Tambah', active: true },
      ]"
    />
    <div class="card">
      <div class="card-body">
        <form @submit.prevent="submitForm">
          <div class="row">
            <div class="alert alert-danger">
              <strong>Perhatian:</strong> Semua field wajib diisi. <br />
              <em>Foto dan file dokumen bersifat opsional</em>
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label">Bidang</label>
              <select v-model="formData.bidang" class="form-control">
                <option disabled value="">-- Pilih Bidang --</option>
                <option v-for="option in bidangOptions" :key="option" :value="option">
                  {{ option }}
                </option>
              </select>
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label">Kegiatan</label>
              <input v-model="formData.kegiatan" type="text" class="form-control" />
            </div>

            <div class="col-md-6" v-for="(label, key) in fieldLabels" :key="key">
              <div class="mb-3" v-if="key === 'nilai_kontrak'">
                <label class="form-label">{{ label }}</label>
                <input
                  v-model.number="formData.nilai_kontrak"
                  type="number"
                  class="form-control"
                  @input="preventNegative('nilai_kontrak')"
                />
                <div class="form-text">{{ formatCurrency(formData.nilai_kontrak) }}</div>
              </div>

              <div class="mb-3" v-else-if="key === 'jumlah_unit'">
                <label class="form-label">{{ label }}</label>
                <input
                  v-model.number="formData.jumlah_unit"
                  type="number"
                  class="form-control"
                  @input="preventNegative('jumlah_unit')"
                />
              </div>

              <div class="mb-3" v-else-if="key === 'waktu_kerja'">
                <label class="form-label">{{ label }}</label>
                <input
                  v-model.number="formData.waktu_kerja"
                  type="number"
                  class="form-control"
                  @input="preventNegative('waktu_kerja')"
                />
              </div>

              <div class="mb-3" v-else-if="key === 'kecamatan'">
                <label class="form-label">Kecamatan</label>
                <select v-model="formData.kecamatan" class="form-control">
                  <option value="">-- Pilih Kecamatan --</option>
                  <option v-for="kec in Object.keys(kelurahanList)" :key="kec" :value="kec">
                    {{ kec }}
                  </option>
                </select>
              </div>

              <div class="mb-3" v-else-if="key === 'kelurahan'">
                <label class="form-label">Kelurahan</label>
                <select
                  v-model="formData.kelurahan"
                  class="form-control"
                  :disabled="!formData.kecamatan"
                >
                  <option value="">-- Pilih Kelurahan --</option>
                  <option
                    v-for="kel in kelurahanList[formData.kecamatan] || []"
                    :key="kel"
                    :value="kel"
                  >
                    {{ kel }}
                  </option>
                </select>
              </div>

              <div class="mb-3" v-else>
                <label class="form-label">{{ label }}</label>
                <input
                  v-model="formData[key]"
                  :type="inputType(key)"
                  class="form-control"
                />
              </div>
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label">Upload PDF</label>
              <input type="file" multiple accept=".pdf" class="form-control" @change="handlePDFs" />
              <ul class="mt-2">
                <li v-for="(pdf, idx) in previewPDFs" :key="idx">{{ pdf }}</li>
              </ul>
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label">Ambil Foto (Kamera)</label>
              <input
                type="file"
                accept="image/*"
                capture="environment"
                class="form-control"
                @change="handleSinglePhoto"
                :disabled="formData.photos.length >= 10 || isCompressing"
              />
              <div class="mt-2 d-flex flex-wrap gap-2">
                <div v-for="(src, idx) in previewPhotos" :key="idx" class="position-relative">
                  <img :src="src" style="width: 100px; height: 100px; object-fit: cover" />
                  <button
                    class="btn btn-sm btn-danger position-absolute top-0 end-0"
                    @click.prevent="removePhoto(idx)"
                  >×</button>
                </div>
              </div>
              <div v-if="isCompressing" class="progress mt-2">
                <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%">
                  Mengompres foto...
                </div>
              </div>
            </div>
          </div>

          <div class="text-end">
            <button class="btn btn-primary" type="submit" :disabled="formData.photos.length < 0 || isCompressing">
              <i class="mdi mdi-content-save me-1"></i> Simpan
            </button>
          </div>
        </form>
      </div>
    </div>
  </Layout>
</template>

<script>
import Layout from "../../layouts/main.vue";
import PageHeader from "@/components/page-header.vue";
import kelurahanList from "@/data/KecKelurahanList";
import Swal from "sweetalert2";
import imageCompression from "browser-image-compression";

export default {
  name: "AddDataPerumahan",
  components: { Layout, PageHeader },
  data() {
    return {
      kelurahanList,
      bidangOptions: ["Perumahan", "Pertanahan", "Permukiman", "Air Minum"],
      isCompressing: false,
      formData: {
        bidang: "",
        kegiatan: "",
        nik: "",
        nama_cpcl: "",
        dusun: "",
        kelurahan: "",
        kecamatan: "",
        no_surat: "",
        tanggal_sp: "",
        nilai_kontrak: null,
        jumlah_unit: null,
        type: "",
        kontraktor_pelaksana: "",
        tanggal_mulai: "",
        tanggal_selesai: "",
        waktu_kerja: null,
        pengawas_lapangan: "",
        pdfs: [],
        photos: [],
      },
      previewPhotos: [],
      previewPDFs: [],
      fieldLabels: {
        nik: "NIK",
        nama_cpcl: "Nama CPCL",
        kecamatan: "Kecamatan",
        kelurahan: "Kelurahan",
        dusun: "Dusun",
        no_surat: "No. Surat",
        tanggal_sp: "Tanggal SP",
        nilai_kontrak: "Nilai Kontrak",
        jumlah_unit: "Jumlah Unit",
        type: "Tipe",
        kontraktor_pelaksana: "Kontraktor Pelaksana",
        tanggal_mulai: "Tanggal Mulai",
        tanggal_selesai: "Tanggal Selesai",
        waktu_kerja: "Waktu Kerja (hari)",
        pengawas_lapangan: "Pengawas Lapangan",
      },
    };
  },
  methods: {
    inputType(key) {
      if (key.includes("tanggal")) return "date";
      if (["nilai_kontrak", "jumlah_unit", "waktu_kerja"].includes(key)) return "number";
      return "text";
    },
    preventNegative(field) {
      if (this.formData[field] < 0) this.formData[field] = 0;
    },
    formatCurrency(value) {
      if (!value && value !== 0) return "";
      return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
      }).format(value);
    },
    validateForm() {
      const nik = this.formData.nik;
      if (!/^[0-9]{16}$/.test(nik)) {
        Swal.fire("Validasi", "NIK harus 16 digit angka", "warning");
        return false;
      }
      if (this.formData.nilai_kontrak < 0) {
        Swal.fire("Validasi", "Nilai kontrak tidak boleh negatif", "warning");
        return false;
      }
      if (this.formData.jumlah_unit < 0) {
        Swal.fire("Validasi", "Jumlah unit tidak boleh negatif", "warning");
        return false;
      }
      if (this.formData.waktu_kerja < 0) {
        Swal.fire("Validasi", "Waktu kerja tidak boleh negatif", "warning");
        return false;
      }
      return true;
    },
    async handleSinglePhoto(event) {
      const file = event.target.files[0];
      if (!file) return;
      if (this.formData.photos.length >= 10) {
        Swal.fire("Batas Foto", "Maksimal 10 foto boleh diunggah.", "warning");
        return;
      }
      this.isCompressing = true;
      try {
        const compressed = await imageCompression(file, {
          maxSizeMB: 1,
          maxWidthOrHeight: 1920,
          useWebWorker: true,
        });
        this.formData.photos.push(compressed);
        this.previewPhotos.push(URL.createObjectURL(compressed));
      } catch {
        Swal.fire("Gagal", "Kompresi foto gagal.", "error");
      } finally {
        this.isCompressing = false;
      }
    },
    handlePDFs(event) {
      const files = Array.from(event.target.files);
      this.formData.pdfs = files;
      this.previewPDFs = files.map(f => f.name);
    },
    removePhoto(index) {
      this.formData.photos.splice(index, 1);
      this.previewPhotos.splice(index, 1);
    },
    async submitForm() {
    if (!this.validateForm()) return;

    try {
      const payload = new FormData();
      for (const key in this.formData) {
        const val = this.formData[key];
        if (Array.isArray(val)) {
          val.forEach(f => payload.append(`${key}[]`, f));
        } else {
          payload.append(key, val);
        }
      }

      const res = await this.$axios.post(`/perumahan/addData`, payload, {
        headers: {
          'Content-Type': 'multipart/form-data',
        }
      });

      if (res.data.success) {
        Swal.fire("Sukses", "Data berhasil disimpan", "success").then(() => {
          this.$router.push("/bidPerumahan/list");
        });
      } else {
        Swal.fire("Gagal", "Data gagal disimpan", "error");
      }
    } catch (error) {
      if (error.response?.status === 401) {
        Swal.fire("Sesi Habis", "Silakan login kembali.", "warning").then(() => {
          localStorage.removeItem("access_token");
          this.$router.push({ name: "Login" });
        });
      } else {
        Swal.fire("Error", "Terjadi kesalahan server", "error");
        console.error(error);
      }
    }
  }
}
};
</script>
