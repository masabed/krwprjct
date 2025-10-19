<script>
import simplebar from 'simplebar-vue';
/**
 * Recent activity component
 */
export default {
  data() {
    return {
      activityData: [
        {
          icon: "ri-edit-2-fill",
          date: "28 Apr, 2024 ",
          time: "12:07 am",
          title: "Responded to need “Volunteer Activities”"
        },
        {
          icon: "ri-user-2-fill",
          date: "21 Apr, 2024 ",
          time: "08:01 pm",
          title: "Added an interest “Volunteer Activities”"
        },
        {
          icon: "ri-bar-chart-fill",
          date: "17 Apr, 2024 ",
          time: "05:10 pm",
          title: "Responded to need “In-Kind Opportunity”"
        },
        {
          icon: "ri-calendar-2-fill",
          date: "07 Apr, 2024",
          time: "12:47 pm",
          title: "Created need “Volunteer Activities”"
        },
        {
          icon: "ri-edit-2-fill",
          date: "05 Apr, 2024 ",
          time: "03:09 pm",
          title: "Attending the event “Some New Event”"
        },
        {
          icon: "ri-user-2-fill",
          date: "02 Apr, 2024 ",
          time: "12:07 am",
          title: "Responded to need “In-Kind Opportunity”"
        }
      ]
    };
  },
  components: {
    simplebar
  }
};
</script>

<template>
  <div class="card">
    <div class="card-body">
      <BDropdown v-model="show" dropleft toggle-class="arrow-none card-drop text-dark font-size-20 px-0" variant="white" size="sm" menu-class="dropdown-menu-end p-0" class="float-end" >
        <template #button-content>  <i class="mdi mdi-dots-vertical"></i> </template>
        <BDropdownItem href="#">Sales Report</BDropdownItem>
        <BDropdownItem href="#">Export Report</BDropdownItem>
        <BDropdownItem href="#">Profit</BDropdownItem>
        <BDropdownItem href="#">Action</BDropdownItem>
      </BDropdown>

      <h4 class="card-title mb-4">Recent Activity Feed</h4>

      <simplebar style="max-height: 330px;">
        <ul class="list-unstyled activity-wid">
          <li class="activity-list" v-for="(data, index) in activityData" :key="index">
            <div class="activity-icon avatar-xs">
              <span class="avatar-title bg-soft-primary text-primary rounded-circle">
                <i :class="`${data.icon}`"></i>
              </span>
            </div>
            <div>
              <div>
                <h5 class="font-size-13">
                  {{data.date}}
                  <small class="text-muted">{{data.time}}</small>
                </h5>
              </div>

              <div>
                <p class="text-muted mb-0">{{data.title}}</p>
              </div>
            </div>
          </li>
        </ul>
      </simplebar>
    </div>
  </div>
</template>