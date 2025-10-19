<script>
/**
 * Earning Reports component
 */
export default {
  data() {
    return {
      WeeklyEarning: {
        series: [72],
        chartOptions: {
          chart: {
            type: "radialBar",
            wight: 60,
            height: 60,
            sparkline: {
              enabled: true
            }
          },
          dataLabels: {
            enabled: false
          },
          colors: ["#5664d2"],
          stroke: {
            lineCap: "round"
          },
          plotOptions: {
            radialBar: {
              hollow: {
                margin: 0,
                size: "70%"
              },
              track: {
                margin: 0
              },
              dataLabels: {
                show: false
              }
            }
          }
        }
      },
      monthlyEarning: {
        series: [65],
        chartOptions: {
          chart: {
            type: "radialBar",
            wight: 60,
            height: 60,
            sparkline: {
              enabled: true
            }
          },
          dataLabels: {
            enabled: false
          },
          colors: ["#1cbb8c"],
          stroke: {
            lineCap: "round"
          },
          plotOptions: {
            radialBar: {
              hollow: {
                margin: 0,
                size: "70%"
              },
              track: {
                margin: 0
              },

              dataLabels: {
                show: false
              }
            }
          }
        }
      }
    };
  }
};
</script>

<template>
  <div class="card">
    <div class="card-body">
      <BDropdown v-model="show" dropend toggle-class="arrow-none card-drop text-dark font-size-20 px-0" variant="white" size="sm" menu-class="dropdown-menu-end p-0" class="float-end" >
        <template #button-content>  <i class="mdi mdi-dots-vertical"></i> </template>
        <BDropdownItem href="#">Sales Report</BDropdownItem>
        <BDropdownItem href="#">Export Report</BDropdownItem>
        <BDropdownItem href="#">Profit</BDropdownItem>
        <BDropdownItem href="#">Action</BDropdownItem>
      </BDropdown>

      <h4 class="card-title mb-4">Earning Reports</h4>
      <div class="text-center">
        <div class="row">
          <div class="col-sm-6">
            <div>
              <div class="mb-3">
                <apexchart class="apex-charts" height="60" wight="60" dir="ltr" :series="WeeklyEarning.series" :options="WeeklyEarning.chartOptions"></apexchart>
              </div>
              <p class="text-muted text-truncate mb-2">Weekly Earnings</p>
              <h5 class="mb-0">$2,523</h5>
            </div>
          </div>

          <div class="col-sm-6">
            <div class="mt-5 mt-sm-0">
              <div class="mb-3">
                <apexchart class="apex-charts" height="60" wight="60" dir="ltr" :series="monthlyEarning.series" :options="monthlyEarning.chartOptions"></apexchart>
              </div>

              <p class="text-muted text-truncate mb-2">Monthly Earnings</p>
              <h5 class="mb-0">$11,235</h5>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>