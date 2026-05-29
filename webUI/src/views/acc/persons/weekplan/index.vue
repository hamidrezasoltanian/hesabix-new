<template>
  <v-toolbar color="toolbar" title="برنامه‌ریزی هفتگی فروش">
    <template v-slot:prepend>
      <v-btn @click="$router.back()" variant="text" icon="mdi-arrow-right" />
    </template>
    <v-spacer />
    <v-btn color="primary" prepend-icon="mdi-plus" @click="openAddDialog(null)">افزودن برنامه</v-btn>
  </v-toolbar>

  <v-container fluid class="pa-2">
    <!-- هدر هفته + ناوبری -->
    <v-card class="mb-3" variant="outlined">
      <v-card-text class="py-2">
        <v-row align="center" no-gutters>
          <v-col cols="auto">
            <v-btn icon="mdi-chevron-right" variant="text" @click="changeWeek(-1)" />
          </v-col>
          <v-col class="text-center">
            <span class="text-subtitle-1 font-weight-bold">
              {{ weekData.weekStr }} تا {{ weekData.weekEnd }}
            </span>
          </v-col>
          <v-col cols="auto">
            <v-btn icon="mdi-chevron-left" variant="text" @click="changeWeek(1)" />
          </v-col>
          <v-col cols="auto" class="ms-2">
            <v-btn size="small" variant="tonal" @click="goToday">این هفته</v-btn>
          </v-col>
          <v-spacer />
          <!-- فیلتر مسئول -->
          <v-col cols="12" sm="3" class="ps-2">
            <v-select
              v-model="filterOwner"
              :items="ownerOptions"
              item-title="label"
              item-value="value"
              label="فیلتر مسئول"
              variant="outlined"
              density="compact"
              clearable
              hide-details
            />
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- نوار پیشرفت -->
    <v-card class="mb-3" variant="outlined" v-if="stats.total > 0">
      <v-card-text class="py-2">
        <div class="d-flex align-center gap-3">
          <span class="text-body-2">{{ stats.done }} از {{ stats.total }} انجام شده</span>
          <v-progress-linear
            :model-value="stats.percent"
            color="success"
            height="10"
            rounded
            class="flex-grow-1"
          />
          <span class="text-body-2 font-weight-bold">{{ stats.percent }}%</span>
          <v-chip size="small" color="warning" v-if="stats.pending > 0">{{ stats.pending }} باقیمانده</v-chip>
        </div>
      </v-card-text>
    </v-card>

    <!-- گرید ۷ روزه -->
    <v-row v-if="!loading" no-gutters class="week-grid">
      <v-col
        v-for="(dayDate, idx) in dayDates"
        :key="dayDate"
        cols="12"
        sm="6"
        md="4"
        lg=""
        class="pa-1"
        style="min-width: 0; flex: 1 1 0;"
      >
        <v-card
          :variant="isToday(dayDate) ? 'tonal' : 'outlined'"
          :color="isToday(dayDate) ? 'primary' : undefined"
          height="100%"
          class="day-column"
        >
          <v-card-title class="py-2 px-3 d-flex align-center justify-space-between">
            <span class="text-body-2 font-weight-bold">{{ dayNames[idx] }}</span>
            <span class="text-caption text-medium-emphasis">{{ dayDate }}</span>
            <v-btn
              icon="mdi-plus"
              size="x-small"
              variant="text"
              color="primary"
              @click="openAddDialog(dayDate)"
              class="ms-1"
            />
          </v-card-title>
          <v-divider />
          <v-card-text class="pa-1 day-body">
            <div v-if="filteredDayPlans(dayDate).length === 0" class="text-caption text-center text-disabled pa-3">
              بدون برنامه
            </div>
            <v-card
              v-for="plan in filteredDayPlans(dayDate)"
              :key="plan.id"
              class="mb-1 plan-card"
              :variant="plan.done ? 'tonal' : 'outlined'"
              :color="plan.done ? 'success' : 'default'"
              density="compact"
            >
              <v-card-text class="pa-2">
                <div class="d-flex align-center justify-space-between mb-1">
                  <span class="text-body-2 font-weight-medium">{{ plan.center.name }}</span>
                  <v-chip
                    size="x-small"
                    :color="plan.actionType === 'visit' ? 'blue' : 'orange'"
                    variant="tonal"
                  >
                    {{ plan.actionType === 'visit' ? 'ویزیت' : 'تماس' }}
                  </v-chip>
                </div>
                <div v-if="plan.des" class="text-caption text-medium-emphasis mb-1">{{ plan.des }}</div>
                <div v-if="plan.owner" class="text-caption text-medium-emphasis mb-1">
                  <v-icon size="10">mdi-account</v-icon> {{ plan.owner.mobile }}
                </div>
                <div class="d-flex align-center gap-1 mt-1">
                  <v-btn
                    :icon="plan.done ? 'mdi-check-circle' : 'mdi-circle-outline'"
                    :color="plan.done ? 'success' : 'default'"
                    size="x-small"
                    variant="text"
                    @click="toggleDone(plan)"
                  />
                  <v-btn
                    icon="mdi-delete-outline"
                    color="error"
                    size="x-small"
                    variant="text"
                    @click="deletePlan(plan)"
                  />
                  <span v-if="plan.done && plan.doneDate" class="text-caption text-success ms-auto">
                    ✓ {{ plan.doneDate }}
                  </span>
                </div>
              </v-card-text>
            </v-card>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <div v-if="loading" class="d-flex justify-center pa-8">
      <v-progress-circular indeterminate color="primary" />
    </div>
  </v-container>

  <!-- دیالوگ افزودن برنامه -->
  <v-dialog v-model="addDialog" max-width="500" persistent>
    <v-card>
      <v-card-title class="pa-4">
        <span>افزودن برنامه ویزیت/تماس</span>
        <v-spacer />
        <v-btn icon="mdi-close" variant="text" @click="addDialog = false" />
      </v-card-title>
      <v-divider />
      <v-card-text class="pa-4">
        <v-row dense>
          <v-col cols="12">
            <v-select
              v-model="form.centerId"
              :items="weekData.centers || []"
              item-title="name"
              item-value="id"
              label="مرکز فروش *"
              variant="outlined"
              density="compact"
              :rules="[v => !!v || 'انتخاب مرکز الزامی است']"
            />
          </v-col>
          <v-col cols="12">
            <v-text-field
              v-model="form.scheduledDate"
              label="تاریخ برنامه *"
              variant="outlined"
              density="compact"
              placeholder="1403/01/15"
              :rules="[v => !!v || 'تاریخ الزامی است']"
            />
          </v-col>
          <v-col cols="12">
            <v-select
              v-model="form.actionType"
              :items="actionTypeOptions"
              item-title="label"
              item-value="value"
              label="نوع فعالیت"
              variant="outlined"
              density="compact"
            />
          </v-col>
          <v-col cols="12">
            <v-textarea
              v-model="form.des"
              label="توضیحات"
              variant="outlined"
              density="compact"
              rows="2"
              auto-grow
            />
          </v-col>
        </v-row>
      </v-card-text>
      <v-divider />
      <v-card-actions class="pa-3">
        <v-btn color="secondary" variant="text" @click="addDialog = false">انصراف</v-btn>
        <v-spacer />
        <v-btn color="primary" variant="flat" :loading="saving" @click="savePlan">ذخیره</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- دیالوگ مدیریت مراکز فروش -->
  <v-dialog v-model="centerDialog" max-width="700">
    <v-card>
      <v-card-title class="pa-4 d-flex align-center">
        <span>مراکز فروش</span>
        <v-spacer />
        <v-btn color="primary" size="small" prepend-icon="mdi-plus" @click="openCenterForm(null)">مرکز جدید</v-btn>
        <v-btn icon="mdi-close" variant="text" @click="centerDialog = false" class="ms-1" />
      </v-card-title>
      <v-divider />
      <v-card-text class="pa-2">
        <v-data-table
          :items="allCenters"
          :headers="centerHeaders"
          density="compact"
          :loading="centersLoading"
        >
          <template v-slot:item.active="{ item }">
            <v-icon :color="item.active ? 'success' : 'error'">
              {{ item.active ? 'mdi-check-circle' : 'mdi-close-circle' }}
            </v-icon>
          </template>
          <template v-slot:item.actions="{ item }">
            <v-btn icon="mdi-pencil" size="x-small" variant="text" @click="openCenterForm(item)" />
            <v-btn icon="mdi-delete" size="x-small" variant="text" color="error" @click="deleteCenter(item)" />
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>
  </v-dialog>

  <!-- فرم مرکز فروش -->
  <v-dialog v-model="centerFormDialog" max-width="500" persistent>
    <v-card>
      <v-card-title class="pa-4">{{ centerForm.id ? 'ویرایش مرکز' : 'مرکز جدید' }}</v-card-title>
      <v-divider />
      <v-card-text class="pa-4">
        <v-row dense>
          <v-col cols="12">
            <v-text-field v-model="centerForm.name" label="نام مرکز *" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="6">
            <v-text-field v-model="centerForm.province" label="استان" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="6">
            <v-text-field v-model="centerForm.city" label="شهر" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="6">
            <v-select
              v-model="centerForm.type"
              :items="centerTypeOptions"
              item-title="label"
              item-value="value"
              label="نوع"
              variant="outlined"
              density="compact"
            />
          </v-col>
          <v-col cols="6">
            <v-select
              v-model="centerForm.potential"
              :items="[{label:'خیلی زیاد',value:4},{label:'زیاد',value:3},{label:'متوسط',value:2},{label:'کم',value:1}]"
              item-title="label"
              item-value="value"
              label="پتانسیل"
              variant="outlined"
              density="compact"
              clearable
            />
          </v-col>
          <v-col cols="6">
            <v-text-field v-model="centerForm.tel" label="تلفن" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="6">
            <v-switch v-model="centerForm.active" label="فعال" color="primary" density="compact" />
          </v-col>
          <v-col cols="12">
            <v-text-field v-model="centerForm.address" label="آدرس" variant="outlined" density="compact" />
          </v-col>
        </v-row>
      </v-card-text>
      <v-divider />
      <v-card-actions class="pa-3">
        <v-btn variant="text" @click="centerFormDialog = false">انصراف</v-btn>
        <v-spacer />
        <v-btn color="primary" variant="flat" :loading="centerSaving" @click="saveCenter">ذخیره</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import { useApplicationStore } from '../../../../stores/applicationStore'

const appStore = useApplicationStore()

interface Center {
  id: number
  name: string
  province: string | null
  city: string | null
  type: string | null
  potential: number | null
  tel: string | null
  address: string | null
  active: boolean
  owner: { id: number; mobile: string } | null
}

interface Plan {
  id: number
  scheduledDate: string
  actionType: string
  done: boolean
  doneDate: string | null
  des: string | null
  center: Center
  owner: { id: number; mobile: string } | null
}

interface WeekData {
  weekStr: string
  weekEnd: string
  days: Record<string, Plan[]>
  centers: Center[]
  prevWeek: string
  nextWeek: string
}

interface Stats {
  total: number
  done: number
  pending: number
  percent: number
}

const loading = ref(false)
const saving = ref(false)
const centersLoading = ref(false)
const centerSaving = ref(false)

const currentWeekStr = ref<string | null>(null)
const weekData = ref<WeekData>({
  weekStr: '',
  weekEnd: '',
  days: {},
  centers: [],
  prevWeek: '',
  nextWeek: '',
})
const stats = ref<Stats>({ total: 0, done: 0, pending: 0, percent: 0 })
const allCenters = ref<Center[]>([])
const filterOwner = ref<string | null>(null)

const addDialog = ref(false)
const centerDialog = ref(false)
const centerFormDialog = ref(false)

const form = ref({ centerId: null as number | null, scheduledDate: '', actionType: 'visit', des: '' })
const centerForm = ref({ id: null as number | null, name: '', province: '', city: '', type: 'customer', potential: null as number | null, tel: '', address: '', active: true })

const dayNames = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه']

const actionTypeOptions = [
  { label: 'ویزیت', value: 'visit' },
  { label: 'تماس', value: 'call' },
]
const centerTypeOptions = [
  { label: 'مشتری', value: 'customer' },
  { label: 'مشتری بالقوه', value: 'prospect' },
  { label: 'سرنخ', value: 'lead' },
]
const centerHeaders = [
  { title: 'نام', key: 'name' },
  { title: 'شهر', key: 'city' },
  { title: 'نوع', key: 'type' },
  { title: 'فعال', key: 'active' },
  { title: '', key: 'actions', sortable: false },
]

const dayDates = computed(() => {
  if (!weekData.value.weekStr) return []
  return Object.keys(weekData.value.days)
})

const ownerOptions = computed(() => {
  const all: { label: string; value: string }[] = []
  const seen = new Set<string>()
  Object.values(weekData.value.days).flat().forEach(plan => {
    if (plan.owner && !seen.has(plan.owner.mobile)) {
      seen.add(plan.owner.mobile)
      all.push({ label: plan.owner.mobile, value: plan.owner.mobile })
    }
  })
  return all
})

function filteredDayPlans(date: string): Plan[] {
  const plans = weekData.value.days[date] ?? []
  if (!filterOwner.value) return plans
  return plans.filter(p => p.owner?.mobile === filterOwner.value)
}

function isToday(_date: string): boolean {
  return false
}

function apiHeaders() {
  return {
    activeBid: appStore.activeBid,
    activeYear: appStore.activeYear,
    activeMoney: appStore.activeMoney,
  }
}

async function loadWeek() {
  loading.value = true
  try {
    const body = currentWeekStr.value ? { weekStr: currentWeekStr.value } : {}
    const res = await axios.post('/api/acc/weekplan/week', body, { headers: apiHeaders() })
    weekData.value = res.data
    if (!currentWeekStr.value) currentWeekStr.value = res.data.weekStr
    await loadStats()
  } catch {
    // ignore
  } finally {
    loading.value = false
  }
}

async function loadStats() {
  try {
    const res = await axios.post('/api/acc/weekplan/stats', { weekStr: currentWeekStr.value }, { headers: apiHeaders() })
    stats.value = res.data
  } catch {
    // ignore
  }
}

function changeWeek(dir: number) {
  currentWeekStr.value = dir < 0 ? weekData.value.prevWeek : weekData.value.nextWeek
  loadWeek()
}

function goToday() {
  currentWeekStr.value = null
  loadWeek()
}

function openAddDialog(date: string | null) {
  form.value = { centerId: null, scheduledDate: date ?? '', actionType: 'visit', des: '' }
  addDialog.value = true
}

async function savePlan() {
  if (!form.value.centerId || !form.value.scheduledDate) return
  saving.value = true
  try {
    await axios.post('/api/acc/weekplan/add', form.value, { headers: apiHeaders() })
    addDialog.value = false
    await loadWeek()
  } catch {
    // ignore
  } finally {
    saving.value = false
  }
}

async function toggleDone(plan: Plan) {
  try {
    await axios.post(`/api/acc/weekplan/done/${plan.id}`, {}, { headers: apiHeaders() })
    await loadWeek()
  } catch {
    // ignore
  }
}

async function deletePlan(plan: Plan) {
  const result = await Swal.fire({
    title: 'حذف برنامه',
    text: `آیا برنامه ویزیت "${plan.center.name}" حذف شود؟`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'بله، حذف',
    cancelButtonText: 'انصراف',
    confirmButtonColor: '#d32f2f',
  })
  if (!result.isConfirmed) return
  try {
    await axios.post(`/api/acc/weekplan/del/${plan.id}`, {}, { headers: apiHeaders() })
    await loadWeek()
  } catch {
    // ignore
  }
}

async function loadAllCenters() {
  centersLoading.value = true
  try {
    const res = await axios.post('/api/acc/salescenter/list', {}, { headers: apiHeaders() })
    allCenters.value = res.data
  } catch {
    // ignore
  } finally {
    centersLoading.value = false
  }
}

function openCenterForm(center: Center | null) {
  if (center) {
    centerForm.value = { ...center }
  } else {
    centerForm.value = { id: null, name: '', province: '', city: '', type: 'customer', potential: null, tel: '', address: '', active: true }
  }
  centerFormDialog.value = true
}

async function saveCenter() {
  if (!centerForm.value.name) return
  centerSaving.value = true
  try {
    await axios.post('/api/acc/salescenter/mod', centerForm.value, { headers: apiHeaders() })
    centerFormDialog.value = false
    await loadAllCenters()
    await loadWeek()
  } catch {
    // ignore
  } finally {
    centerSaving.value = false
  }
}

async function deleteCenter(center: Center) {
  const result = await Swal.fire({
    title: 'غیرفعال کردن مرکز',
    text: `مرکز "${center.name}" غیرفعال شود؟`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'بله',
    cancelButtonText: 'انصراف',
    confirmButtonColor: '#d32f2f',
  })
  if (!result.isConfirmed) return
  try {
    await axios.post(`/api/acc/salescenter/del/${center.id}`, {}, { headers: apiHeaders() })
    await loadAllCenters()
    await loadWeek()
  } catch {
    // ignore
  }
}

onMounted(() => {
  loadWeek()
  loadAllCenters()
})
</script>

<style scoped>
.week-grid {
  min-height: 400px;
}
.day-column {
  min-height: 200px;
}
.day-body {
  min-height: 150px;
  overflow-y: auto;
  max-height: 500px;
}
.plan-card {
  cursor: default;
}
</style>
