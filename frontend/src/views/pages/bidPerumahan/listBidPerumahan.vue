<script>
import Layout from "../../layouts/main.vue";
import PageHeader from "@/components/page-header.vue";
import kelurahanList from "@/data/KecKelurahanList";
import Swal from "sweetalert2";

export default {
  name: "listbidperumahan",
  components: {
    Layout,
    PageHeader,
  },
  data() {
    return {
      title: "Bidang Perumahan",
      items: [
        { text: "Menu" },
        { text: "Bidang Perumahan", active: true },
      ],
      selectedKecamatan: "All",
      selectedKelurahan: "All",
      dataList: [],
      totalRows: 1,
      tabIndex: 1,
      currentPage: 1,
      perPage: 10,
      pageOptions: [10, 25, 50, 100],
      filter: null,
      sortKey: null,
      sortAsc: true,
      fields: [
        { key: "nama_cpcl", sortable: true, label: "Nama CPCL" },
        { key: "kontraktor_pelaksana", sortable: true, label: "Kontraktor" },
        { key: "kegiatan", sortable: true, label: "Kegiatan" },
        { key: "kecamatan", sortable: true, label: "Kecamatan" },
        { key: "kelurahan", sortable: true, label: "Kelurahan" },
        { key: "tanggal_selesai_raw", sortable: true, label: "Tanggal Selesai" },
        { key: "photo_count", sortable: true, label: "Jumlah Foto" },
        { key: "pdf_count", sortable: true, label: "Jumlah Dokumen" },
        { key: "action", label: "Action" },
      ],
    };
  },
  computed: {
    rows() {
      return this.filteredList.length;
    },
    uniqueKecamatan() {
      return ["All", ...Object.keys(kelurahanList)];
    },
    uniqueKelurahan() {
      if (!this.selectedKecamatan || this.selectedKecamatan === "All") {
        return [];
      }
      return ["All", ...(kelurahanList[this.selectedKecamatan] || [])];
    },
    filteredList() {
      let filtered = [...this.dataList].filter((item) =>
        (!this.selectedKecamatan || this.selectedKecamatan === "All" || item.kecamatan === this.selectedKecamatan) &&
        (!this.selectedKelurahan || this.selectedKelurahan === "All" || item.kelurahan === this.selectedKelurahan) &&
        (!this.filter || Object.values(item).some((val) =>
          String(val).toLowerCase().includes(this.filter.toLowerCase())
        ))
      );
      if (this.sortKey) {
        filtered.sort((a, b) => {
          const valA = a[this.sortKey];
          const valB = b[this.sortKey];
          return this.sortAsc
            ? String(valA).localeCompare(String(valB))
            : String(valB).localeCompare(String(valA));
        });
      }
      return filtered;
    },
    computedFilteredDataList() {
      return this.filteredList.slice(
        (this.currentPage - 1) * this.perPage,
        this.currentPage * this.perPage
      );
    },
  },
  watch: {
    selectedKecamatan() {
      this.selectedKelurahan = "All";
    },
  },
  mounted() {
    this.fetchData();
  },
  methods: {
    goToEdit(id) {
      this.$router.push({ name: "editDataPerumahan", params: { id } });
    },
    scrollPagination(direction) {
      const container = this.$el.querySelector(".pagination-scroll");
      const scrollAmount = 100;
      container.scrollBy({
        left: direction === "left" ? -scrollAmount : scrollAmount,
        behavior: "smooth",
      });
    },
    async fetchData() {
      try {
        const response = await this.$axios.get("/perumahan/bidangPerumahan");
        if (response.data.success) {
          this.dataList = response.data.data.map((item) => ({
            ...item,
            tanggal_selesai_raw: item.tanggal_selesai,
            tanggal_selesai: this.formatDate(item.tanggal_selesai),
          }));
          this.totalRows = this.dataList.length;
        }
      } catch (error) {
        console.error("Failed to fetch data:", error);
      }
    },
    async deleteEntry(id) {
      const confirm = await Swal.fire({
        title: "Hapus Data?",
        text: "Data yang dihapus tidak bisa dikembalikan.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Ya, hapus!",
        cancelButtonText: "Batal",
        reverseButtons: true,
      });

      if (confirm.isConfirmed) {
        try {
          const response = await this.$axios.delete(`/perumahan/${id}`);
          if (response.data.success) {
            Swal.fire("Berhasil!", "Data berhasil dihapus.", "success");
            this.fetchData();
          } else {
            Swal.fire("Gagal", "Data gagal dihapus.", "error");
          }
        } catch (error) {
          Swal.fire("Error", "Terjadi kesalahan saat menghapus data.", "error");
        }
      }
    },
    onAddData() {
      this.$router.push({ name: "addDataPerumahan" });
    },
    resetFilters() {
      this.selectedKecamatan = "All";
      this.selectedKelurahan = "All";
      this.filter = null;
      this.currentPage = 1;
    },
    formatDate(dateStr) {
      const options = { year: "numeric", month: "long", day: "numeric" };
      return new Date(dateStr).toLocaleDateString("en-GB", options);
    },
    setSort(field) {
      if (this.sortKey === field) {
        this.sortAsc = !this.sortAsc;
      } else {
        this.sortKey = field;
        this.sortAsc = true;
      }
    },
  },
};
</script><template>
  <Layout>
    <PageHeader :title="title" :items="items" />
    <div class="row">
      <div class="col-lg-12">
        <div class="card">
          <div class="card-body pt-1">
            <BTabs v-model="tabIndex" nav-class="nav-tabs-custom">
              <BTab>
                <template v-slot:title>
                  <a class="font-weight-bold active">Data</a>
                </template>

                <!-- FILTERS -->
                <div class="row mt-4 mb-2 align-items-end">
                  <div class="col-md-4">
                    <label><strong>Filter Kecamatan:</strong></label>
                    <BFormSelect
                      v-model="selectedKecamatan"
                      :options="uniqueKecamatan"
                      placeholder="-- Pilih Kecamatan --"
                      class="form-control"
                    />
                  </div>
                  <div class="col-md-4">
                    <label><strong>Filter Kelurahan:</strong></label>
                    <BFormSelect
                      v-model="selectedKelurahan"
                      :options="uniqueKelurahan"
                      :disabled="!selectedKecamatan"
                      placeholder="-- Pilih Kelurahan --"
                      class="form-control"
                    />
                  </div>
                  <div class="col-md-4">
                    <button class="btn btn-sm btn-secondary mt-1" @click="resetFilters">
                      <i class="mdi mdi-refresh me-1"></i> Reset Filter
                    </button>
                  </div>
                </div>

                <!-- PAGINATION + SEARCH -->
                <div class="row mt-1">
                  <div class="col-md-12 text-end">
                    <button class="btn btn-sm btn-primary" @click="onAddData">
                      <i class="mdi mdi-plus me-1"></i> Tambah Data
                    </button>
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-12 col-md-6">
                    <label class="d-inline-flex align-items-center">
                      Show&nbsp;
                      <BFormSelect v-model="perPage" size="sm" :options="pageOptions" />&nbsp;entries
                    </label>
                    <span class="ms-3"><strong>Total:</strong> {{ rows }}</span>
                  </div>
                  <div class="col-sm-12 col-md-6 text-md-end">
                    <label class="d-inline-flex align-items-center">
                      Search:
                      <BFormInput v-model="filter" type="search" class="form-control form-control-sm ms-2" />
                    </label>
                  </div>
                </div>

                <!-- TABLE -->
                <div class="table-responsive mt-3">
                  <BTableSimple class="table-centered datatable dt-responsive nowrap">
                    <BThead class="table-light">
                      <BTr>
                        <BTh v-for="field in fields" :key="field.key">
                          {{ field.label }}
                          <span v-if="field.sortable" @click="setSort(field.key)" class="cursor-pointer ms-1">
                            <i class="mdi" :class="sortKey === field.key ? (sortAsc ? 'mdi-arrow-up' : 'mdi-arrow-down') : 'mdi-swap-vertical'" />
                          </span>
                        </BTh>
                      </BTr>
                    </BThead>
                    <BTbody>
                      <BTr v-for="(entry, index) in computedFilteredDataList" :key="index">
                        <BTd>{{ entry.nama_cpcl }}</BTd>
                        <BTd>{{ entry.kontraktor_pelaksana }}</BTd>
                        <BTd>{{ entry.kegiatan }}</BTd>
                        <BTd>{{ entry.kecamatan }}</BTd>
                        <BTd>{{ entry.kelurahan }}</BTd>
                        <BTd>{{ entry.tanggal_selesai }}</BTd>
                        <BTd>{{ entry.photo_count }}</BTd>
                        <BTd>{{ entry.pdf_count }}</BTd>
                        <BTd>
                          <button
                            type="button"
                            class="btn btn-link text-primary p-0 me-2"
                            title="Edit"
                            @click="goToEdit(entry.id)"
                          >
                            <i class="mdi mdi-pencil font-size-18"></i>
                          </button>

                          <button
                            type="button"
                            class="btn btn-link text-danger p-0"
                            title="Delete"
                            @click="deleteEntry(entry.id)"
                          >
                            <i class="mdi mdi-trash-can font-size-18"></i>
                          </button>
                        </BTd>
                      </BTr>
                    </BTbody>
                  </BTableSimple>
                </div>

                <!-- PAGINATION -->
                <div class="col-12 text-center">
                  <div class="pagination-wrapper d-flex align-items-center justify-content-center">
                    <button class="scroll-btn me-2" @click="scrollPagination('left')">
                      ‹
                    </button>

                    <div class="pagination-scroll overflow-auto">
                      <ul class="pagination pagination-rounded mb-0 d-flex justify-content-center">
                        <BPagination
                          v-model="currentPage"
                          :total-rows="rows"
                          :per-page="perPage"
                          size="md"
                          class="pagination-rounded"
                        />
                      </ul>
                    </div>

                    <button class="scroll-btn ms-2" @click="scrollPagination('right')">
                      ›
                    </button>
                  </div>
                </div>
              </BTab>
            </BTabs>
          </div>
        </div>
      </div>
    </div>
  </Layout>
</template>

<style scoped>
.pagination-scroll {
  max-width: 90vw;
  overflow-x: auto;
  white-space: nowrap;
  -webkit-overflow-scrolling: touch;
  scroll-behavior: smooth;
}

.scroll-btn {
  background: transparent;
  border: none;
  font-size: 1.5rem;
  color: #333;
  cursor: pointer;
}

@media (max-width: 768px) {
  .scroll-btn {
    display: none;
  }
}
</style>
