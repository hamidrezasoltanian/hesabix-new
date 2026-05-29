<template>
  <v-toolbar color="toolbar">
    <v-toolbar-title>لاگ فعالیت‌ها</v-toolbar-title>
    <v-spacer />
    <v-btn color="primary" prepend-icon="mdi-plus" @click="openDialog">ثبت فعالیت</v-btn>
  </v-toolbar>

  <v-container fluid class="pa-2">

    <!-- summary chips -->
    <div v-if="summary" class="d-flex flex-wrap gap-2 mb-3">
      <v-chip size="small" color="blue" variant="tonal" prepend-icon="mdi-phone">
        تماس: {{ summary.call }}
      </v-chip>
      <v-chip size="small" color="indigo" variant="tonal" prepend-icon="mdi-map-marker">
        ویزیت: {{ summary.visit }}
      </v-chip>
      <v-chip size="small" color="success" variant="tonal" prepend-icon="mdi-handshake">
        فروش: {{ summary.sale }} — {{ Number(summary.salesAmount || 0).toLocaleString('fa') }} ریال
      </v-chip>
      <v-chip size="small" color="orange" variant="tonal" prepend-icon="mdi-briefcase-outline">
        ماموریت: {{ summary.mission }}
      </v-chip>
    </div>

    <!-- filters -->
    <v-row dense class="mb-3">
      <v-col cols="6" sm="2">
        <v-select v-model="filters.type"
          :items="[{value:'',label:'همه انواع'},...typeOptions]"
          item-title="label" item-value="value"
          label="نوع" variant="outlined" density="compact" hide-details />
      </v-col>
      <v-col cols="6" sm="2">
        <v-text-field v-model="filters.from" label="از تاریخ" variant="outlined" density="compact"
          placeholder="1403/01/01" hide-details clearable />
      </v-col>
      <v-col cols="6" sm="2">
        <v-text-field v-model="filters.to" label="تا تاریخ" variant="outlined" density="compact"
          placeholder="1403/12/29" hide-details clearable />
      </v-col>
      <v-col cols="6" sm="2">
        <v-btn color="primary" variant="tonal" size="small" @click="load" prepend-icon="mdi-magnify" class="mt-1">
          جستجو
        </v-btn>
      </v-col>
    </v-row>

    <div v-if="loading" class="d-flex justify-center pa-8"><v-progress-circular indeterminate /></div>

    <v-card v-else-if="items.length === 0" variant="outlined" class="pa-8 text-center text-disabled">
      هیچ فعالیتی یافت نشد
    </v-card>

    <template v-else>
      <!-- group by date -->
      <template v-for="group in grouped" :key="group.date">
        <div class="text-caption text-medium-emphasis font-weight-bold mb-1 mt-3">{{ group.date }}</div>
        <v-card variant="outlined" class="mb-2">
          <v-list density="compact">
            <v-list-item
              v-for="a in group.items" :key="a.id"
              :prepend-icon="activityIcon(a.type)"
            >
              <template v-slot:title>
                <v-chip size="x-small" :color="activityColor(a.type)" variant="tonal" class="me-2">
                  {{ activityLabel(a.type) }}
                </v-chip>
                <span v-if="a.center" class="text-body-2 font-weight-medium me-2">{{ a.center.name }}</span>
                <span v-if="a.type === 'sale' && a.amount" class="text-body-2">
                  {{ Number(a.amount).toLocaleString('fa') }} ریال
                </span>
                <v-chip v-if="a.type === 'sale' && a.cashSale" size="x-small" color="green" variant="tonal" class="ms-1">نقدی</v-chip>
                <v-chip v-if="a.type === 'mission'" size="x-small" :color="a.done ? 'success' : 'warning'" variant="tonal" class="ms-1">
                  {{ a.done ? 'انجام شد' : 'در دست اقدام' }}
                </v-chip>
              </template>
              <template v-slot:subtitle>
                <span v-if="a.note" class="text-caption">{{ a.note }}</span>
              </template>
              <template v-slot:append>
                <v-btn icon="mdi-delete" size="x-small" variant="text" color="error" @click="del(a)" />
              </template>
            </v-list-item>
          </v-list>
        </v-card>
      </template>
    </template>
  </v-container>

  <!-- ════ Add Dialog ════ -->
  <v-dialog v-model="dialog" max-width="500" persistent>
    <v-card>
      <v-card-title class="pa-4">ثبت فعالیت جدید</v-card-title>
      <v-divider />
      <v-card-text class="pa-4">
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-select v-model="form.type" :items="typeOptions" item-title="label" item-value="value"
              label="نوع فعالیت *" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.date" label="تاریخ *" variant="outlined" density="compact"
              placeholder="1403/01/15" />
          </v-col>
          <v-col cols="12">
            <v-autocomplete v-model="form.centerId" :items="centers" item-title="name" item-value="id"
              label="مرکز فروش (اختیاری)" variant="outlined" density="compact" clearable />
          </v-col>
          <v-col cols="12" v-if="form.type === 'sale'">
            <v-text-field v-model="form.amount" label="مبلغ فروش (ریال)" variant="outlined" density="compact" type="number" />
          </v-col>
          <v-row class="ma-0" v-if="form.type === 'sale' || form.type === 'mission'">
            <v-col cols="6" v-if="form.type === 'sale'">
              <v-switch v-model="form.cashSale" label="فروش نقدی" color="success" density="compact" />
            </v-col>
            <v-col cols="6" v-if="form.type === 'mission'">
              <v-switch v-model="form.done" label="انجام شد" color="success" density="compact" />
            </v-col>
          </v-row>
          <v-col cols="12">
            <v-textarea v-model="form.note" label="توضیحات" variant="outlined" density="compact" rows="2" auto-grow />
          </v-col>
        </v-row>
      </v-card-text>
      <v-divider />
      <v-card-actions class="pa-3">
        <v-btn variant="text" @click="dialog = false">انصراف</v-btn>
        <v-spacer />
        <v-btn color="primary" variant="flat" :loading="saving" @click="save">ذخیره</v-btn>
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

interface Activity {
  id: number; type: string; date: string; amount: string | null
  cashSale: boolean; done: boolean; note: string | null
  center: { id: number; name: string } | null
  user: { id: number; mobile: string }
}
interface Center { id: number; name: string }

const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const items = ref<Activity[]>([])
const centers = ref<Center[]>([])
const summary = ref<{ call: number; visit: number; sale: number; mission: number; salesAmount: string } | null>(null)
const filters = ref({ type: '', from: '', to: '' })
const form = ref({ type: 'call', date: '', centerId: null as number | null, amount: '', cashSale: false, done: false, note: '' })

const typeOptions = [
  { value: 'call',    label: 'تماس تلفنی' },
  { value: 'visit',   label: 'ویزیت حضوری' },
  { value: 'sale',    label: 'فروش / قرارداد' },
  { value: 'mission', label: 'ماموریت' },
]

function apiHeaders() {
  return { activeBid: appStore.activeBid, activeYear: appStore.activeYear, activeMoney: appStore.activeMoney }
}
function activityIcon(t: string) {
  return ({ call: 'mdi-phone', visit: 'mdi-map-marker', sale: 'mdi-handshake', mission: 'mdi-briefcase-outline' } as Record<string, string>)[t] ?? 'mdi-circle'
}
function activityLabel(t: string) { return typeOptions.find(x => x.value === t)?.label ?? t }
function activityColor(t: string) {
  return ({ call: 'blue', visit: 'indigo', sale: 'success', mission: 'orange' } as Record<string, string>)[t] ?? 'default'
}

const grouped = computed(() => {
  const map = new Map<string, Activity[]>()
  for (const a of items.value) {
    if (!map.has(a.date)) map.set(a.date, [])
    map.get(a.date)!.push(a)
  }
  return [...map.entries()].map(([date, items]) => ({ date, items }))
})

async function load() {
  loading.value = true
  try {
    const payload: Record<string, string> = {}
    if (filters.value.type) payload.type = filters.value.type
    if (filters.value.from) payload.from = filters.value.from
    if (filters.value.to)   payload.to = filters.value.to

    const [listRes, summaryRes] = await Promise.all([
      axios.post('/api/acc/activitylog/list', payload, { headers: apiHeaders() }),
      axios.post('/api/acc/activitylog/summary', payload, { headers: apiHeaders() }),
    ])
    items.value = listRes.data

    // build summary from rows
    const rows: { type: string; cnt: string }[] = summaryRes.data.rows
    const get = (t: string) => rows.filter(r => r.type === t).reduce((s, r) => s + Number(r.cnt), 0)
    summary.value = {
      call: get('call'), visit: get('visit'),
      sale: get('sale'), mission: get('mission'),
      salesAmount: summaryRes.data.totalSales ?? '0',
    }
  } finally { loading.value = false }
}

async function loadCenters() {
  const res = await axios.post('/api/acc/salescenter/list', {}, { headers: apiHeaders() })
  centers.value = res.data
}

function openDialog() {
  form.value = { type: 'call', date: '', centerId: null, amount: '', cashSale: false, done: false, note: '' }
  dialog.value = true
}

async function save() {
  if (!form.value.type || !form.value.date) return
  saving.value = true
  try {
    await axios.post('/api/acc/activitylog/add', form.value, { headers: apiHeaders() })
    dialog.value = false
    await load()
  } finally { saving.value = false }
}

async function del(a: Activity) {
  const res = await Swal.fire({ title: 'حذف فعالیت', icon: 'warning', showCancelButton: true, confirmButtonText: 'حذف', cancelButtonText: 'انصراف', confirmButtonColor: '#d32f2f' })
  if (!res.isConfirmed) return
  await axios.post(`/api/acc/activitylog/del/${a.id}`, {}, { headers: apiHeaders() })
  await load()
}

onMounted(() => { load(); loadCenters() })
</script>
