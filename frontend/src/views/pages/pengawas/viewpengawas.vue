<template>
  <Layout>
    <PageHeader
      :title="'Data Pengawasan'"
      :items="[
        { text: 'Menu' },
        { text: 'Pengawasan', href: '/pengawas' },
        { text: 'Detail', active: true }
      ]"
    />

    <div class="card">
      <div class="card-body">
        <div v-if="loading" class="text-center my-4">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
        </div>

        <form v-else @submit.prevent="submitPhotos">
          <div class="row">
            <div
              class="col-md-6"
              v-for="(label, key) in fieldLabels"
              :key="key"
              v-if="key !== 'kontraktor_pelaksana'"
            >
              <div class="mb-3">
                <label class="form-label">{{ label }}</label>
                <input
                  :value="formatDateField(key, formData[key])"
                  class="form-control"
                  disabled
                />
              </div>
            </div>

            <!-- New Filtered Bidang Display -->
            <div class="col-md-6 mb-3">
              <label class="form-label">Bidang</label>
              <input type="text" class="form-control" :value="formData.bidang" disabled />
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label">Dokumen Terkait</label>
              <ul class="mb-0">
                <li v-for="(pdf, i) in formData.pdf_urls" :key="i">
                  <a :href="pdf" target="_blank" download>
                    {{ pdf.split('/').pop() }}
                  </a>
                </li>
              </ul>
            </div>

            <div class="col-12 mb-3">
              <label class="form-label">Foto Sebelumnya</label>
              <div class="d-flex flex-wrap gap-2">
                <div
                  v-for="(url, i) in formData.photo_urls"
                  :key="'existing-' + i"
                  class="text-center"
                  style="max-width: 110px"
                >
                  <img
                    :src="url"
                    @click="showPreviewModal(url)"
                    style="cursor:pointer;width:100px;height:100px;object-fit:cover"
                  />
                  <small class="d-block text-muted" style="font-size: 0.75rem">
                    Diunggah: {{ getDateFromFilename(url) }}
                  </small>
                </div>
              </div>
            </div>

            <div class="col-md-12 mb-3">
              <label class="form-label">Tambah Foto (Kamera)</label>
              <input
                type="file"
                accept="image/*"
                capture="environment"
                class="form-control"
                @change="handlePhotoUpload"
                :disabled="formData.photos.length >= 10 || isCompressing"
              />
              <div class="mt-2 d-flex flex-wrap gap-2 justify-content-center">
                <div
                  v-for="(src, idx) in previewPhotos"
                  :key="'new-' + idx"
                  class="position-relative text-center"
                  style="max-width: 110px"
                >
                  <img
                    :src="src.url"
                    style="width:100px;height:100px;object-fit:cover"
                  />
                  <span class="badge bg-secondary d-block mt-1">{{ src.time }}</span>
                  <button
                    class="btn btn-sm btn-danger position-absolute top-0 end-0"
                    @click.prevent="removePhoto(idx)"
                  >
                    ×
                  </button>
                </div>
              </div>
              <div v-if="isCompressing" class="progress mt-2">
                <div
                  class="progress-bar progress-bar-striped progress-bar-animated"
                  style="width: 100%"
                >
                  Mengompres foto...
                </div>
              </div>
            </div>
          </div>

          <div
            class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 mb-3"
          >
            <button
              class="btn btn-secondary w-20 w-md-auto"
              type="button"
              @click="$router.back()"
            >
              ← Kembali
            </button>

            <button
              class="btn btn-primary w-20 w-md-auto"
              type="submit"
              :disabled="formData.photos.length < 5 || isCompressing"
            >
              <i class="mdi mdi-camera me-1"></i> Upload Foto
            </button>
          </div>
        </form>
      </div>
    </div>
  </Layout>
</template>

<script>
import Layout from "@/views/layouts/main.vue";
import PageHeader from "@/components/page-header.vue";
import Swal from "sweetalert2";
import imageCompression from "browser-image-compression";

export default {
  name: "ViewPengawas",
  components: { Layout, PageHeader },
  data() {
    return {
      uid: this.$route.params.id,
      isCompressing: false,
      loading: true,
      formData: {
        photos: [],
        pdf_urls: [],
        photo_urls: [],
      },
      previewPhotos: [],
      fieldLabels: {
        nama_cpcl: "Nama CPCL",
        nik: "NIK",
        kegiatan: "Kegiatan",
        bidang: "Bidang",
        no_surat: "No. Surat",
        tanggal_sp: "Tanggal SP",
        nilai_kontrak: "Nilai Kontrak",
        jumlah_unit: "Jumlah Unit",
        type: "Tipe",
        kecamatan: "Kecamatan",
        kelurahan: "Kelurahan",
        dusun: "Dusun",
        tanggal_mulai: "Tanggal Mulai",
        tanggal_selesai: "Tanggal Selesai",
        waktu_kerja: "Waktu Kerja (hari)",
        pengawas_lapangan: "Pengawas Lapangan",
        kontraktor_pelaksana: "Kontraktor Pelaksana",
      },
    };
  },
  mounted() {
    this.fetchData();
  },
  methods: {
    getDateFromFilename(path) {
      const filename = path.split("/").pop();
      const raw = filename?.split("_")[0];
      if (!raw || raw.length < 12) return "-";
      const y = raw.slice(0, 4), m = raw.slice(4, 6), d = raw.slice(6, 8);
      const h = raw.slice(8, 10), min = raw.slice(10, 12);
      return `${d}-${m}-${y} ${h}:${min}`;
    },
    formatDateField(key, value) {
      if (key.includes("tanggal") && value?.includes("T")) {
        const [date] = value.split("T");
        return date.split("-").reverse().join("-");
      }
      return value;
    },
    showPreviewModal(url) {
      Swal.fire({
        imageUrl: url,
        imageAlt: "Preview Foto",
        showConfirmButton: false,
        footer: `<small>Diunggah pada: ${this.getDateFromFilename(url)}</small>`
      });
    },
    async fetchData() {
      const ASSET_BASE = import.meta.env.VITE_ASSET_BASE_URL || "http://localhost:8000";
      this.loading = true;

      try {
        const res = await this.$axios.get(`/perumahan/${this.uid}`);
        const data = res.data.data;

        this.formData = {
          ...data,
          photos: [],
          pdf_urls: (data.pdfs || []).map(f => `${ASSET_BASE}/storage/${f}`),
          photo_urls: (data.photos || []).map(f => `${ASSET_BASE}/storage/${f}`),
        };
      } catch {
        Swal.fire("Gagal", "Data gagal dimuat", "error");
      } finally {
        this.loading = false;
      }
    },
    async handlePhotoUpload(event) {
      const file = event.target.files[0];
      if (!file || this.formData.photos.length >= 10) return;

      this.isCompressing = true;
      try {
        const compressed = await imageCompression(file, {
          maxSizeMB: 1,
          maxWidthOrHeight: 1920,
          useWebWorker: true,
        });
        const blobUrl = URL.createObjectURL(compressed);
        const uploadTime = new Date().toLocaleString("id-ID");

        this.formData.photos.push(compressed);
        this.previewPhotos.push({ url: blobUrl, time: uploadTime });
      } catch {
        Swal.fire("Error", "Gagal mengompres foto", "error");
      } finally {
        this.isCompressing = false;
      }
    },
    removePhoto(index) {
      this.formData.photos.splice(index, 1);
      this.previewPhotos.splice(index, 1);
    },
    async submitPhotos() {
      if (this.formData.photos.length < 5) {
        Swal.fire("Validasi", "Minimal upload 5 foto", "warning");
        return;
      }

      const payload = new FormData();
      this.formData.photos.forEach(f => payload.append("photos[]", f));

      try {
        const res = await this.$axios.post(
          `/perumahan/update-photos/${this.uid}`,
          payload
        );

        if (res.data.success) {
          Swal.fire("Sukses", "Foto berhasil diupload", "success");
          this.formData.photos = [];
          this.previewPhotos = [];
          this.fetchData();
        } else {
          Swal.fire("Gagal", "Foto gagal diupload", "error");
        }
      } catch {
        Swal.fire("Error", "Terjadi kesalahan server", "error");
      }
    },
  },
};
</script>

<style scoped>
.spinner-border {
  width: 3rem;
  height: 3rem;
}
</style>
