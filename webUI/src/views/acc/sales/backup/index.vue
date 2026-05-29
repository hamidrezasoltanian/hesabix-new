<template>
  <v-container fluid>
    <v-row class="mb-2"><v-col><h2 class="text-h6">پشتیبان‌گیری و بازیابی</h2></v-col></v-row>

    <!-- Stats -->
    <v-row class="mb-4">
      <v-col v-for="s in stats" :key="s.label" cols="6" sm="4" md="2">
        <v-card class="text-center pa-3">
          <div class="text-h5 font-weight-bold text-primary">{{ s.value }}</div>
          <div class="text-caption text-medium-emphasis">{{ s.label }}</div>
        </v-card>
      </v-col>
    </v-row>

    <v-row>
      <!-- Export -->
      <v-col cols="12" md="6">
        <v-card>
          <v-card-title>دانلود پشتیبان</v-card-title>
          <v-card-text>
            <p class="text-body-2 mb-3">انتخاب کنید چه بخش‌هایی در پشتیبان باشند:</p>
            <v-checkbox v-model="modules" value="crm" label="CRM (مراکز، فعالیت‌ها، تقویم، KPI)" hide-details/>
            <v-checkbox v-model="modules" value="persons" label="اشخاص (مشتریان، تأمین‌کنندگان)" hide-details/>
            <v-checkbox v-model="modules" value="accounting_summary" label="خلاصه حسابداری" hide-details/>
            <v-btn color="primary" class="mt-3" prepend-icon="mdi-download" :loading="exporting" @click="doExport" :disabled="modules.length===0">
              دانلود فایل JSON
            </v-btn>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Import -->
      <v-col cols="12" md="6">
        <v-card>
          <v-card-title>بازیابی از پشتیبان</v-card-title>
          <v-card-text>
            <v-alert type="warning" variant="tonal" class="mb-3" density="compact">
              داده‌های موجود ادغام می‌شوند (حذف نمی‌شوند). مراکز یا اشخاص موجود بروزرسانی می‌شوند.
            </v-alert>
            <v-file-input v-model="importFile" label="فایل JSON پشتیبان" accept=".json" prepend-icon="mdi-file-code" show-size class="mb-3"/>
            <v-btn color="warning" prepend-icon="mdi-upload" :loading="restoring" :disabled="!importFile" @click="doImport">
              بازیابی
            </v-btn>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-alert v-if="importResult" :type="importResult.success ? 'success' : 'error'" class="mt-4" closable>
      <span v-if="importResult.success">
        بازیابی موفق: {{ importResult.stats?.crm_centers ?? 0 }} مرکز،
        {{ importResult.stats?.persons ?? 0 }} شخص ایجاد/بروز شد.
      </span>
      <span v-else>{{ importResult.msg }}</span>
    </v-alert>
  </v-container>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { useApplicationStore } from '../../../../stores/applicationStore'

const appStore = useApplicationStore()
function apiHeaders() { return { activeBid: appStore.activeBid, activeYear: appStore.activeYear, activeMoney: appStore.activeMoney } }

const modules = ref<string[]>(['crm', 'persons'])
const importFile = ref<File | File[] | null>(null)
const exporting = ref(false)
const restoring = ref(false)
const importResult = ref<any>(null)
const statsData = ref<any>({})

const stats = ref([
  { label: 'مراکز CRM', value: 0 }, { label: 'برچسب‌ها', value: 0 },
  { label: 'فعالیت‌ها', value: 0 }, { label: 'رویداد تقویم', value: 0 },
  { label: 'آیتم چک‌لیست', value: 0 }, { label: 'اشخاص', value: 0 },
])

async function loadInfo() {
  const { data } = await axios.post('/api/acc/backup/info', {}, { headers: apiHeaders() })
  stats.value[0].value = data.centers ?? 0
  stats.value[1].value = data.tags ?? 0
  stats.value[2].value = data.activities ?? 0
  stats.value[3].value = data.calendarEvents ?? 0
  stats.value[4].value = data.checklistItems ?? 0
  stats.value[5].value = data.persons ?? 0
}

async function doExport() {
  exporting.value = true
  try {
    const resp = await fetch('/api/acc/backup/export', {
      method: 'POST',
      headers: { ...apiHeaders() as any, 'Content-Type': 'application/json' },
      body: JSON.stringify({ modules: modules.value }),
    })
    const blob = await resp.blob()
    const cd = resp.headers.get('Content-Disposition') ?? ''
    const m = cd.match(/filename="([^"]+)"/)
    const filename = m ? m[1] : 'backup.json'
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url; a.download = filename; a.click()
    URL.revokeObjectURL(url)
  } finally { exporting.value = false }
}

async function doImport() {
  if (!importFile.value) return
  restoring.value = true
  importResult.value = null
  const fd = new FormData()
  const f = Array.isArray(importFile.value) ? importFile.value[0] : importFile.value
  fd.append('file', f)
  try {
    const { data } = await axios.post('/api/acc/backup/import', fd, { headers: { ...apiHeaders(), 'Content-Type': 'multipart/form-data' } })
    if (data.result === 1) {
      importResult.value = { success: true, stats: data.stats }
      loadInfo()
    } else {
      importResult.value = { success: false, msg: data.msg ?? 'خطا در بازیابی' }
    }
  } finally { restoring.value = false }
}

onMounted(loadInfo)
</script>
