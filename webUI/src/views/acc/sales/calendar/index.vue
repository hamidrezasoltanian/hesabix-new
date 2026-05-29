<template>
  <v-container fluid>
    <v-row class="mb-2" align="center">
      <v-col><h2 class="text-h6">تقویم CRM</h2></v-col>
      <v-col cols="auto">
        <v-btn color="primary" prepend-icon="mdi-plus" @click="openAdd()">رویداد جدید</v-btn>
      </v-col>
    </v-row>

    <v-card>
      <v-card-text>
        <v-row align="center" class="mb-2">
          <v-col cols="auto">
            <v-btn icon @click="prevMonth"><v-icon>mdi-chevron-right</v-icon></v-btn>
          </v-col>
          <v-col class="text-center text-h6">{{ monthLabel }}</v-col>
          <v-col cols="auto">
            <v-btn icon @click="nextMonth"><v-icon>mdi-chevron-left</v-icon></v-btn>
          </v-col>
        </v-row>

        <v-row no-gutters class="mb-1">
          <v-col v-for="d in weekDays" :key="d" class="text-center text-caption font-weight-bold py-1 bg-grey-lighten-4">{{ d }}</v-col>
        </v-row>

        <v-row no-gutters v-for="(week, wi) in calendarWeeks" :key="wi">
          <v-col v-for="(day, di) in week" :key="di"
            class="pa-1"
            :class="day && day.isToday ? 'bg-blue-lighten-5' : ''"
            style="min-height:90px; border:1px solid #e0e0e0; cursor:pointer"
            @click="day && openAdd(day.date)">
            <div v-if="day">
              <div class="text-caption font-weight-bold mb-1" :class="day.isToday ? 'text-primary' : ''">{{ day.day }}</div>
              <v-chip v-for="ev in day.events" :key="ev.id" size="x-small"
                :color="ev.color" class="mb-1 d-block text-truncate" style="max-width:100%;cursor:pointer"
                @click.stop="openEdit(ev)">{{ ev.title }}</v-chip>
            </div>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <v-dialog v-model="dialog" max-width="500">
      <v-card>
        <v-card-title>{{ form.id ? 'ویرایش رویداد' : 'رویداد جدید' }}</v-card-title>
        <v-card-text>
          <v-text-field v-model="form.title" label="عنوان *" class="mb-2"/>
          <v-row>
            <v-col><v-text-field v-model="form.date" label="تاریخ شروع (YYYY/MM/DD)"/></v-col>
            <v-col><v-text-field v-model="form.endDate" label="تاریخ پایان (اختیاری)"/></v-col>
          </v-row>
          <v-row align="center">
            <v-col cols="6"><v-text-field v-model="form.color" label="رنگ" type="color"/></v-col>
            <v-col cols="6"><v-checkbox v-model="form.allDay" label="تمام روز"/></v-col>
          </v-row>
          <v-textarea v-model="form.des" label="توضیحات" rows="2"/>
        </v-card-text>
        <v-card-actions>
          <v-btn v-if="form.id" color="error" variant="text" @click="delEvent">حذف</v-btn>
          <v-spacer/>
          <v-btn @click="dialog=false">انصراف</v-btn>
          <v-btn color="primary" :loading="saving" @click="saveEvent">ذخیره</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useApplicationStore } from '../../../../stores/applicationStore'

const appStore = useApplicationStore()
function apiHeaders() { return { activeBid: appStore.activeBid, activeYear: appStore.activeYear, activeMoney: appStore.activeMoney } }

const events = ref<any[]>([])
const dialog = ref(false)
const saving = ref(false)
const form = ref<any>({ id: null, title: '', date: '', endDate: '', color: '#1976D2', allDay: false, des: '' })

const weekDays = ['شنبه','یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنجشنبه','جمعه']
const monthNames = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند']

const currentYear = ref(1403)
const currentMonth = ref(1)
const todayStr = ref('')

const monthLabel = computed(() => `${monthNames[currentMonth.value - 1]} ${currentYear.value}`)

function pad(n: number) { return n.toString().padStart(2, '0') }
function daysInMonth(y: number, m: number) {
  if (m <= 6) return 31
  if (m <= 11) return 30
  return 29
}

const calendarWeeks = computed(() => {
  const y = currentYear.value, m = currentMonth.value
  const total = daysInMonth(y, m)
  const cells: any[] = []
  for (let d = 1; d <= total; d++) {
    const dateStr = `${y}/${pad(m)}/${pad(d)}`
    cells.push({ date: dateStr, day: d, isToday: dateStr === todayStr.value, events: events.value.filter(e => e.date === dateStr) })
  }
  // Pad to full weeks of 7
  while (cells.length % 7 !== 0) cells.push(null)
  const weeks = []
  for (let i = 0; i < cells.length; i += 7) weeks.push(cells.slice(i, i + 7))
  return weeks
})

function prevMonth() {
  if (currentMonth.value === 1) { currentMonth.value = 12; currentYear.value-- } else currentMonth.value--
  loadEvents()
}
function nextMonth() {
  if (currentMonth.value === 12) { currentMonth.value = 1; currentYear.value++ } else currentMonth.value++
  loadEvents()
}

async function loadEvents() {
  const y = currentYear.value, m = currentMonth.value
  const from = `${y}/${pad(m)}/01`
  const nm = m === 12 ? 1 : m + 1, ny = m === 12 ? y + 1 : y
  const to = `${ny}/${pad(nm)}/01`
  const { data } = await axios.post('/api/acc/crm/calendar/list', { from, to }, { headers: apiHeaders() })
  events.value = Array.isArray(data) ? data : []
}

function openAdd(date?: string) {
  form.value = { id: null, title: '', date: date ?? todayStr.value, endDate: '', color: '#1976D2', allDay: false, des: '' }
  dialog.value = true
}
function openEdit(ev: any) {
  form.value = { id: ev.id, title: ev.title, date: ev.date, endDate: ev.endDate ?? '', color: ev.color, allDay: ev.allDay, des: ev.des ?? '' }
  dialog.value = true
}

async function saveEvent() {
  if (!form.value.title || !form.value.date) return
  saving.value = true
  await axios.post('/api/acc/crm/calendar/mod', form.value, { headers: apiHeaders() })
  saving.value = false
  dialog.value = false
  loadEvents()
}

async function delEvent() {
  await axios.post(`/api/acc/crm/calendar/del/${form.value.id}`, {}, { headers: apiHeaders() })
  dialog.value = false
  loadEvents()
}

onMounted(() => {
  // Get today from a simple API or default to current year
  todayStr.value = ''
  axios.get('/api/general/get/time', { headers: apiHeaders() }).then(({ data }) => {
    todayStr.value = data.timeNow ?? ''
    const parts = (data.timeNow ?? '').split('/')
    if (parts.length >= 2) { currentYear.value = parseInt(parts[0]); currentMonth.value = parseInt(parts[1]) }
    loadEvents()
  }).catch(() => {
    currentYear.value = 1403
    currentMonth.value = 1
    loadEvents()
  })
})
</script>
