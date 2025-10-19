<script>
import FullCalendar from "@fullcalendar/vue3";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import interactionPlugin from "@fullcalendar/interaction";
import bootstrapPlugin from "@fullcalendar/bootstrap";
import listPlugin from "@fullcalendar/list";

import Swal from "sweetalert2";

import Layout from "../../layouts/main.vue";
import PageHeader from "@/components/page-header.vue";

import { calendarEvents, categories } from "./data-calendar";

/**
 * Calendar component
 */
export default {
  page: {
    title: "Calendar",
    meta: [{ name: "description" }],
  },
  components: { FullCalendar, Layout, PageHeader },
  data() {
    return {
      title: "Calendar",
      items: [
        {
          text: "Nazox",
        },
        {
          text: "Calendar",
          active: true,
        },
      ],
      calendarEvents: calendarEvents,
      calendarOptions: {
        headerToolbar: {
          left: "prev,next today",
          center: "title",
          right: "dayGridMonth,timeGridWeek,timeGridDay,listWeek",
        },
        plugins: [
          dayGridPlugin,
          timeGridPlugin,
          interactionPlugin,
          bootstrapPlugin,
          listPlugin,
        ],
        initialView: "dayGridMonth",
        themeSystem: "bootstrap",
        initialEvents: calendarEvents,
        editable: true,
        droppable: true,
        eventResizableFromStart: true,
        dateClick: this.dateClicked,
        eventClick: this.editEvent,
        eventsSet: this.handleEvents,
        weekends: true,
        selectable: true,
        selectMirror: true,
        dayMaxEvents: true,
      },
      currentEvents: [],
      showModal: false,
      eventModal: false,
      categories: categories,
      submitted: false,
      submit: false,
      newEventData: {},
      edit: {},
      deleteId: {},
      event: {
        title: "",
        category: "",
      },
      editevent: {
        editTitle: "",
        editcategory: "",
      },
    };
  },
  methods: {
    /**
     * Modal form submit
     */
    // eslint-disable-next-line no-unused-vars
    handleSubmit(e) {
      this.submitted = true;

      // stop here if form is invalid
      this.$touch;
      if (this.$invalid) {
        return;
      } else {
        const title = this.event.title;
        const category = this.event.category;
        let calendarApi = this.newEventData.view.calendar;

        this.currentEvents = calendarApi.addEvent({
          id: this.newEventData.length + 1,
          title,
          start: this.newEventData.date,
          end: this.newEventData.date,
          classNames: [category],
        });
        this.successmsg();
        this.showModal = false;
        this.newEventData = {};
      }
      this.submitted = false;
      this.event = {};
    },
    // eslint-disable-next-line no-unused-vars
    hideModal(e) {
      this.submitted = false;
      this.showModal = false;
      this.event = {};
    },
    /**
     * Edit event modal submit
     */
    // eslint-disable-next-line no-unused-vars
    editSubmit(e) {
      this.submit = true;
      const editTitle = this.editevent.editTitle;
      const editcategory = this.editevent.editcategory;

      this.edit.setProp("title", editTitle);
      this.edit.setProp("classNames", editcategory);
      this.successmsg();
      this.eventModal = false;
    },

    /**
     * Delete event
     */
    deleteEvent() {
      this.edit.remove();
      this.eventModal = false;
    },
    /**
     * Modal open for add event
     */
    dateClicked(info) {
      this.newEventData = info;
      this.showModal = true;
    },
    /**
     * Modal open for edit event
     */
    editEvent(info) {
      this.edit = info.event;
      this.editevent.editTitle = this.edit.title;
      this.editevent.editcategory = this.edit.classNames[0];
      this.eventModal = true;
    },

    closeModal() {
      this.eventModal = false;
    },

    confirm() {
      Swal.fire({
        title: "Are you sure?",
        text: "You won't be able to delete this!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#34c38f",
        cancelButtonColor: "#f46a6a",
        confirmButtonText: "Yes, delete it!",
      }).then((result) => {
        if (result.value) {
          this.deleteEvent();
          Swal.fire("Deleted!", "Event has been deleted.", "success");
        }
      });
    },

    /**
     * Show list of events
     */
    handleEvents(events) {
      this.currentEvents = events;
    },

    /**
     * Show successfull Save Dialog
     */
    successmsg() {
      Swal.fire({
        position: "center",
        icon: "success",
        title: "Event has been saved",
        showConfirmButton: false,
        timer: 1000,
      });
    },
  },
};
</script>

<template>
  <Layout>
    <PageHeader :title="title" :items="items" />
    <div class="row mb-4">
      <div class="col-xl-3">
        <div class="card h-100">
          <div class="card-body">
            <button type="button" class="btn font-16 btn-primary waves-effect waves-light w-100" id="btn-new-event" @click="showModal = !showModal">
              Create New Event
            </button>

            <div id="external-events">
              <br>
              <p class="text-muted">Drag and drop your event or click in the calendar</p>
              <div class="external-event fc-event bg-success text-white" data-class="bg-success">
                <i class="mdi mdi-checkbox-blank-circle font-size-11 me-2"></i>New Event
                Planning
              </div>
              <div class="external-event fc-event bg-info text-white" data-class="bg-info">
                <i class="mdi mdi-checkbox-blank-circle font-size-11 me-2"></i>Meeting
              </div>
              <div class="external-event fc-event bg-warning text-white" data-class="bg-warning">
                <i class="mdi mdi-checkbox-blank-circle font-size-11 me-2"></i>Generating
                Reports
              </div>
              <div class="external-event fc-event bg-danger text-white" data-class="bg-danger">
                <i class="mdi mdi-checkbox-blank-circle font-size-11 me-2"></i>Create
                New theme
              </div>
            </div>

          </div>
        </div>
      </div> <!-- end col-->
      <div class="col-xl-9">
        <div class="card mb-0">
          <div class="card-body">
            <FullCalendar ref="fullCalendar" :options="calendarOptions"></FullCalendar>
          </div>
        </div>
      </div>
    </div>
    <BModal v-model="showModal" title="Add New Event" centered title-class="text-black font-18" body-class="p-3" hide-footer>
      <form @submit.prevent="handleSubmit">
        <div class="row">
          <div class="col-12">
            <div class="mb-3">
              <label for="name">Event Name</label>
              <input id="name" v-model="event.title" type="text" class="form-control" placeholder="Insert Event name" />
            </div>
          </div>
          <div class="col-12">
            <div class="mb-3">
              <label class="control-label">Category</label>
              <select v-model="event.category" class="form-control" name="category">
                <option v-for="option in categories" :key="option.backgroundColor" :value="`${option.value}`">
                  {{ option.name }}
                </option>
              </select>
            </div>
          </div>
        </div>
        <div class="row mt-2">
          <div class="col-6">
            <BButton type="button" variant="danger" id="btn-delete-event">Delete</BButton>
          </div> <!-- end col-->
          <div class="col-6 text-end">
            <BButton variant="light" @click="hideModal">Close</BButton>
            <BButton type="submit" variant="success" class="ms-1">Save</BButton>
          </div> <!-- end col-->
        </div>
        
      </form>
    </BModal>

    <!-- Edit Modal -->
    <BModal v-model="eventModal" title="Edit Event" title-class="text-black font-18" hide-footer body-class="p-0">
      <form @submit.prevent="editSubmit">
        <div class="p-3">
          <label>Change event name</label>
          <div class="input-group m-b-15">
            <input v-model="editevent.editTitle" class="form-control" type="text" />
            <span class="input-group-append">
              <button type="submit" class="btn btn-success btn-md">
                <i class="fa fa-check"></i> Save
              </button>
            </span>
          </div>
        </div>
        <div class="text-end p-3">
          <BButton variant="light" @click="closeModal">Close</BButton>
          <BButton class="ms-1" variant="danger" @click="deleteEvent">Delete</BButton>
        </div>
      </form>
    </BModal>
  </Layout>
</template>
