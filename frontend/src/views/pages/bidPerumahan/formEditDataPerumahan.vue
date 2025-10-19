
<script>
import Layout from "../../layouts/main.vue";
import PageHeader from "@/components/page-header.vue";
import kelurahanList from "@/data/KecKelurahanList";
import imageCompression from "browser-image-compression";
import Swal from "sweetalert2";

export default {
  name: "FormEditDataPerumahan",
  components: { Layout, PageHeader },
  data() {
    return {
      kelurahanList,
      bidangOptions: ["Perumahan", "Pertanahan", "Permukiman", "Air Minum"],
      isCompressing: false,
      selectedPhoto: null,
      originalForm: {},
      previewPhotos: [],
      previewPDFs: [],
      formData: {
        bidang: "", kegiatan: "", nik: "", nama_cpcl: "", dusun: "",
        kelurahan: "", kecamatan: "", no_surat: "", tanggal_sp: "",
        nilai_kontrak: null, jumlah_unit: null, type: "", kontraktor_pelaksana: "",
        tanggal_mulai: "", tanggal_selesai: "", waktu_kerja: null, pengawas_lapangan: "",
        pdfs: [], photos: []
      },
      fieldLabels: {
        nik: "NIK", nama_cpcl: "Nama CPCL", kecamatan: "Kecamatan", kelurahan: "Kelurahan",
        dusun: "Dusun", no_surat: "No. Surat", tanggal_sp: "Tanggal SP",
        nilai_kontrak: "Nilai Kontrak", jumlah_unit: "Jumlah Unit", type: "Tipe",
        kontraktor_pelaksana: "Kontraktor Pelaksana", tanggal_mulai: "Tanggal Mulai",
        tanggal_selesai: "Tanggal Selesai", waktu_kerja: "Waktu Kerja (hari)",
        pengawas_lapangan: "Pengawas Lapangan"
      },
    };
  },
  mounted() {
    this.fetchExistingData();
  },
  methods: {
    inputType(key) {
      return key.includes("tanggal") ? "date" : "text";
    },
    preventNegative(key) {
      if (this.formData[key] < 0) this.formData[key] = 0;
    },
    formatDateTime(filename) {
      const match = filename.match(/^(\d{8})(\d{6})_/);
      if (!match) return { date: "Format Tidak Valid", time: "-" };
      const [_, ymd, hms] = match;
      return {
        date: `${ymd.slice(6, 8)}/${ymd.slice(4, 6)}/${ymd.slice(0, 4)}`,
        time: `${hms.slice(0, 2)}:${hms.slice(2, 4)}:${hms.slice(4, 6)}`
      };
    },
    async fetchExistingData() {
  const id = this.$route.params.id;
  const ASSET_BASE = import.meta.env.VITE_ASSET_BASE_URL || "http://localhost:8000";

  try {
    const res = await this.$axios.get(`/perumahan/${id}`);
    if (res.data.success) {
      const data = res.data.data;

      this.formData = {
        ...this.formData,
        ...data,
        tanggal_sp: data.tanggal_sp?.substring(0, 10) || "",
        tanggal_mulai: data.tanggal_mulai?.substring(0, 10) || "",
        tanggal_selesai: data.tanggal_selesai?.substring(0, 10) || "",
        pdfs: [],
        photos: [],
      };

      await this.$nextTick();

      // isi default kelurahan jika belum ada
      if (!this.formData.kelurahan && this.formData.kecamatan && kelurahanList[this.formData.kecamatan]?.length) {
        this.formData.kelurahan = kelurahanList[this.formData.kecamatan][0];
      }

      this.originalForm = JSON.stringify(this.formData);

      this.previewPDFs = (data.pdfs || []).map(f => `${ASSET_BASE}/storage/${f}`);
      this.previewPhotos = (data.photos || []).map(f => {
        const filename = f.split("/").pop();
        const { date, time } = this.formatDateTime(filename);
        return { url: `${ASSET_BASE}/storage/${f}`, date, time };
      });
    }
  } catch (err) {
    console.error("Fetch error:", err);
  }
},

    async handleSinglePhoto(event) {
      const file = event.target.files[0];
      if (!file) return;
      if (this.formData.photos.length >= 10) {
        Swal.fire("Batas Foto", "Maksimal 10 foto.", "warning");
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
        this.previewPhotos.push({ url: URL.createObjectURL(compressed), date: "Baru", time: "-" });
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
    showPhoto(photo) {
      this.selectedPhoto = photo;
    },
    // simplified submitForm for correct token usage

async submitForm() {
  if (!this.formData.kecamatan || !this.formData.kelurahan) {
    return Swal.fire("Validasi Gagal", "Kecamatan dan Kelurahan wajib dipilih", "warning");
  }

  if (
    JSON.stringify(this.formData) === this.originalForm &&
    this.formData.photos.length === 0 &&
    this.formData.pdfs.length === 0
  ) {
    return Swal.fire("Tidak Ada Perubahan", "Tidak ada data yang diubah", "info");
  }

  const id = this.$route.params.id;
  const form = new FormData();

  for (const key in this.formData) {
    const val = this.formData[key];
    if (Array.isArray(val)) {
      val.forEach(v => form.append(`${key}[]`, v));
    } else {
      form.append(key, val);
    }
  }

  try {
    const res = await this.$axios.post(`/perumahan/${id}`, form); 
    if (res.data.success) {
      Swal.fire("Sukses", "Data berhasil diperbarui", "success").then(() => {
        this.$router.push("/bidPerumahan/list");
      });
    } else {
      Swal.fire("Gagal", "Gagal memperbarui data", "error");
    }
  } catch (err) {
    console.error("Submit error:", err.response?.data || err);
    Swal.fire("Error", "Gagal menyimpan data", "error");
  }
},
  },
};
</script>


<style scoped>
.modal-backdrop {
  position: fixed;
  top: 0; left: 0;
  width: 100%; height: 100%;
  background: rgba(0,0,0,0.6);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1050;
}
.modal-content-custom {
  background: white;
  padding: 20px;
  border-radius: 10px;
  max-width: 90%;
  max-height: 90%;
  overflow: auto;
}
</style>


<!-- formEditDataPerumahan.vue -->

<template>
  <Layout>
    <PageHeader
      :title="'Edit Data Perumahan'"
      :items="[
        { text: 'Menu' },
        { text: 'Bidang Perumahan', href: '/bidPerumahan/list' },
        { text: 'Edit', active: true }
      ]"
    />

    <div class="card">
      <div class="card-body">
        <form @submit.prevent="submitForm">
          <div class="alert alert-danger mb-3">
            Semua form wajib diisi. <strong class="text-danger">Foto dan File Dokumen dapat diabaikan</strong>.
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Bidang</label>
              <select v-model="formData.bidang" class="form-control" required>
                <option disabled value="">-- Pilih Bidang --</option>
                <option v-for="option in bidangOptions" :key="option" :value="option">{{ option }}</option>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Kegiatan</label>
              <input v-model="formData.kegiatan" type="text" class="form-control" required />
            </div>

            <div class="col-md-6" v-for="(label, key) in fieldLabels" :key="key">
              <div class="mb-3" v-if="['nilai_kontrak','jumlah_unit','waktu_kerja'].includes(key)">
                <label class="form-label">{{ label }}</label>
                <input v-model.number="formData[key]" type="number" class="form-control" required @input="preventNegative(key)" />
              </div>
              <div class="mb-3" v-else-if="key === 'kecamatan'">
                <label class="form-label">{{ label }}</label>
                <select v-model="formData.kecamatan" class="form-control" required>
                  <option value="">-- Pilih Kecamatan --</option>
                  <option v-for="kec in Object.keys(kelurahanList)" :key="kec" :value="kec">{{ kec }}</option>
                </select>
              </div>
              <div class="mb-3" v-else-if="key === 'kelurahan'">
                <label class="form-label">{{ label }}</label>
                <select v-model="formData.kelurahan" class="form-control" required>
                  <option value="">-- Pilih Kelurahan --</option>
                  <option v-for="kel in kelurahanList[formData.kecamatan] || []" :key="kel" :value="kel">{{ kel }}</option>
                </select>
              </div>
              <div class="mb-3" v-else>
                <label class="form-label">{{ label }}</label>
                <input v-model="formData[key]" :type="inputType(key)" class="form-control" required />
              </div>
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label">Upload PDF</label>
              <input type="file" multiple accept=".pdf" class="form-control" @change="handlePDFs" />
              <ul class="mt-2">
                <li v-for="(pdf, idx) in previewPDFs" :key="idx">
                  <a :href="pdf" target="_blank">{{ pdf.split('/').pop() }}</a>
                </li>
              </ul>
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label">Ambil Foto</label>
              <input type="file" accept="image/*" capture="environment" class="form-control"
                @change="handleSinglePhoto" :disabled="formData.photos.length >= 10 || isCompressing" />
              <div class="mt-2 d-flex flex-wrap gap-2">
                <div v-for="(src, idx) in previewPhotos" :key="idx" class="position-relative">
                  <img :src="src.url" style="width: 100px; height: 100px; object-fit: cover; cursor: pointer"
                    @click="showPhoto(src)" />
                  <button class="btn btn-sm btn-danger position-absolute top-0 end-0" @click.prevent="removePhoto(idx)">×</button>
                </div>
              </div>
              <div v-if="isCompressing" class="progress mt-2">
                <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%">
                  Mengompres foto...
                </div>
              </div>
            </div>
          </div>

          <div class="text-end mt-3 d-flex justify-content-between">
            <button class="btn btn-light" @click="$router.push('/bidPerumahan/list')">← Kembali</button>
            <button class="btn btn-primary" type="submit" :disabled="isCompressing">
              <i class="mdi mdi-content-save me-1"></i> Update
            </button>
          </div>
        </form>
      </div>
    </div>

    <div v-if="selectedPhoto" class="modal-backdrop" @click.self="selectedPhoto = null">
      <div class="modal-content-custom">
        <img :src="selectedPhoto.url" style="max-width:100%; max-height:60vh" class="mb-2" />
        <p class="text-center text-muted">
          Di Upload pada Tanggal: <strong>{{ selectedPhoto.date }}</strong> Pukul <strong>{{ selectedPhoto.time }}</strong>
        </p>
        <button class="btn btn-sm btn-secondary d-block mx-auto" @click="selectedPhoto = null">Tutup</button>
      </div>
    </div>
  </Layout>
</template>
