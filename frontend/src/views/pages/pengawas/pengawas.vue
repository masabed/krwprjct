<template>
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

                <!-- FILTER -->
                <div class="row mt-4 mb-2 align-items-end">
                  <div class="col-md-4">
                    <label><strong>Filter Bidang:</strong></label>
                    <BFormSelect v-model="selectedBidang" :options="['All', 'Perumahan', 'Permukiman']" class="form-control" />
                  </div>
                  <div class="col-md-4">
                    <label><strong>Filter Kecamatan:</strong></label>
                    <BFormSelect v-model="selectedKecamatan" :options="uniqueKecamatan" class="form-control" />
                  </div>
                  <div class="col-md-4">
                    <label><strong>Filter Kelurahan:</strong></label>
                    <BFormSelect
                      v-model="selectedKelurahan"
                      :options="uniqueKelurahan"
                      :disabled="!selectedKecamatan"
                      class="form-control"
                    />
                  </div>
                </div>

                <div class="mb-3 text-end">
                  <button class="btn btn-sm btn-secondary" @click="resetFilters">
                    <i class="mdi mdi-refresh me-1"></i> Reset Filter
                  </button>
                </div>

                <!-- SEARCH & PERPAGE + TOTAL -->
                <div class="row mt-2 align-items-end">
                  <div class="col-sm-12 col-md-6 d-flex align-items-center">
                    <label class="d-inline-flex align-items-center mb-0">
                      Show&nbsp;
                      <BFormSelect v-model="perPage" size="sm" :options="pageOptions" />&nbsp;entries
                    </label>
                    <span class="ms-3">Total data: <strong>{{ rows }}</strong></span>
                  </div>
                  <div class="col-sm-12 col-md-6 text-md-end">
                    <label class="d-inline-flex align-items-center mb-0">
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
                        <BTd>{{ entry.bidang }}</BTd>
                        <BTd>{{ entry.kegiatan }}</BTd>
                        <BTd>{{ entry.kecamatan }}</BTd>
                        <BTd>{{ entry.kelurahan }}</BTd>
                        <BTd>{{ entry.tanggal_selesai }}</BTd>
                        <BTd>{{ entry.photo_count }}</BTd>
                        <BTd>{{ entry.updated_at }}</BTd>
                        <BTd>
                          <button type="button" class="btn btn-sm btn-outline-primary" @click="goToEdit(entry.id)">
                            <i class="mdi mdi-camera"></i> Photo
                          </button>
                        </BTd>
                      </BTr>
                    </BTbody>
                  </BTableSimple>
                </div>

                <!-- PAGINATION -->
                <div class="col-12 text-center mt-3">
                  <div class="pagination-wrapper d-flex align-items-center justify-content-center">
                    <button class="scroll-btn me-2" @click="scrollPagination('left')">‹</button>
                    <div class="pagination-scroll overflow-auto">
                      <ul class="pagination pagination-rounded mb-0 d-flex justify-content-center">
                        <BPagination
                          v-model="currentPage"
                          :total-rows="rows"
                          :per-page="perPage"
                          size="md"
                        />
                      </ul>
                    </div>
                    <button class="scroll-btn ms-2" @click="scrollPagination('right')">›</button>
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
  
<script>
import Layout from "@/views/layouts/main.vue";
import PageHeader from "@/components/page-header.vue";
import kelurahanList from "@/data/KecKelurahanList";

export default {
  name: "pengawas",
  components: { Layout, PageHeader },
  data() {
    return {
      title: "Data Pengawasan",
      items: [{ text: "Menu" }, { text: "Pengawasan", active: true }],
      selectedBidang: "All",
      selectedKecamatan: "All",
      selectedKelurahan: "All",
      dataList: [],
      tabIndex: 1,
      currentPage: 1,
      perPage: 10,
      pageOptions: [10, 25, 50, 100],
      filter: null,
      sortKey: null,
      sortAsc: true,
      fields: [
        { key: "nama_cpcl", sortable: true, label: "Nama CPCL" },
        { key: "bidang", sortable: true, label: "Bidang" },
        { key: "kegiatan", sortable: true, label: "Kegiatan" },
        { key: "kecamatan", sortable: true, label: "Kecamatan" },
        { key: "kelurahan", sortable: true, label: "Kelurahan" },
        { key: "tanggal_selesai_raw", sortable: true, label: "Tanggal Selesai" },
        { key: "photo_count", sortable: true, label: "Jumlah Foto" },
        { key: "updated_at", sortable: true, label: "Update Terakhir" },
        { key: "action", label: "Aksi" },
      ],
    };
  },
  computed: {
    uniqueKecamatan() {
      return ["All", ...Object.keys(kelurahanList)];
    },
    uniqueKelurahan() {
      if (!this.selectedKecamatan || this.selectedKecamatan === "All") return [];
      return ["All", ...(kelurahanList[this.selectedKecamatan] || [])];
    },
    filteredList() {
      let result = this.dataList.filter(item =>
        (!this.selectedBidang || this.selectedBidang === "All" || item.bidang === this.selectedBidang) &&
        (!this.selectedKecamatan || this.selectedKecamatan === "All" || item.kecamatan === this.selectedKecamatan) &&
        (!this.selectedKelurahan || this.selectedKelurahan === "All" || item.kelurahan === this.selectedKelurahan) &&
        (!this.filter || Object.values(item).some(v => String(v).toLowerCase().includes(this.filter.toLowerCase())))
      );

      if (this.sortKey) {
        result.sort((a, b) => {
          const valA = a[this.sortKey];
          const valB = b[this.sortKey];
          return this.sortAsc ? (valA > valB ? 1 : -1) : (valA < valB ? 1 : -1);
        });
      }

      return result;
    },
    computedFilteredDataList() {
      return this.filteredList.slice((this.currentPage - 1) * this.perPage, this.currentPage * this.perPage);
    },
    rows() {
      return this.filteredList.length;
    },
  },
  mounted() {
    this.fetchData();
  },
  watch: {
    selectedKecamatan() {
      this.selectedKelurahan = "All";
    },
  },
  methods: {
    scrollPagination(dir) {
      const container = this.$el.querySelector(".pagination-scroll");
      container.scrollBy({ left: dir === "left" ? -100 : 100, behavior: "smooth" });
    },
    resetFilters() {
      this.selectedBidang = "All";
      this.selectedKecamatan = "All";
      this.selectedKelurahan = "All";
      this.filter = null;
      this.currentPage = 1;
    },
    async fetchData() {
      try {
        const res = await this.$axios.get("/perumahan/bidangPerumahan");
        if (res.data.success) {
          this.dataList = res.data.data.map(item => ({
            ...item,
            tanggal_selesai_raw: item.tanggal_selesai,
            tanggal_selesai: this.formatDate(item.tanggal_selesai),
            updated_at: this.formatDateTime(item.updated_at),
          }));
        }
      } catch (e) {
        console.error("Fetch error:", e);
      }
    },
    formatDate(date) {
      const opt = { year: "numeric", month: "long", day: "numeric" };
      return new Date(date).toLocaleDateString("id-ID", opt);
    },
    formatDateTime(dateStr) {
      if (!dateStr) return "-";
      const date = new Date(dateStr);
      if (isNaN(date)) return "Invalid Date";
      return date.toLocaleString("id-ID", {
        year: "numeric",
        month: "long",
        day: "numeric",
      });
    },
    goToEdit(id) {
      this.$router.push({ name: "editDataPengawas", params: { id } });
    },
    setSort(field) {
      if (this.sortKey === field) this.sortAsc = !this.sortAsc;
      else {
        this.sortKey = field;
        this.sortAsc = true;
      }
    },
  },
};
</script>

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
