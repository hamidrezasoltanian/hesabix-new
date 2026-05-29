<template>
  <v-container fluid>
    <v-row class="mb-2" align="center">
      <v-col><h2 class="text-h6">ایمپورت مراکز از فایل</h2></v-col>
      <v-col cols="auto">
        <v-btn variant="outlined" color="primary" prepend-icon="mdi-download" @click="downloadTemplate">دانلود قالب Excel</v-btn>
      </v-col>
    </v-row>

    <v-card class="mb-4">
      <v-card-title class="text-subtitle-1">راهنما</v-card-title>
      <v-card-text>
        <p class="text-body-2 mb-2">فایل Excel یا CSV با ستون‌های زیر آماده کنید:</p>
        <v-chip v-for="col in columns" :key="col" size="small" class="me-1 mb-1">{{ col }}</v-chip>
        <p class="text-body-2 mt-2 text-medium-emphasis">* برچسب‌ها با کاما از هم جدا شوند. وضعیت CRM: no_contact، initial_contact، meeting_done، proposal_sent، contract_closed، inactive</p>
      </v-card-text>
    </v-card>

    <v-card>
      <v-card-text>
        <v-file-input v-model="file" label="انتخاب فایل (xlsx, csv)" accept=".xlsx,.csv" class="mb-3" prepend-icon="mdi-file-excel" show-size/>

        <v-radio-group v-model="mode" inline label="در صورت وجود مرکز با همین نام:">
          <v-radio label="رد کردن (Skip)" value="skip"/>
          <v-radio label="بروزرسانی" value="update"/>
        </v-radio-group>

        <v-row class="mt-2">
          <v-col cols="auto">
            <v-btn color="secondary" :disabled="!file" :loading="previewing" @click="preview">پیش‌نمایش</v-btn>
          </v-col>
          <v-col cols="auto">
            <v-btn color="primary" :disabled="!file || !previewDone" :loading="importing" @click="runImport">اجرای ایمپورت</v-btn>
          </v-col>
        </v-row>

        <!-- Preview table -->
        <div v-if="previewData.length > 0" class="mt-4">
          <div class="text-subtitle-2 mb-2">پیش‌نمایش (۵ ردیف اول از {{ totalRows }} ردیف)</div>
          <v-table density="compact">
            <thead><tr><th>نام</th><th>استان</th><th>شهر</th><th>نوع</th><th>پتانسیل</th><th>وضعیت</th><th>برچسب‌ها</th></tr></thead>
            <tbody>
              <tr v-for="(r, i) in previewData" :key="i">
                <td>{{ r.name }}</td><td>{{ r.province }}</td><td>{{ r.city }}</td>
                <td>{{ r.type }}</td><td>{{ r.potential }}</td><td>{{ r.crmStatus }}</td><td>{{ r.tags }}</td>
              </tr>
            </tbody>
          </v-table>
        </div>

        <!-- Result -->
        <v-alert v-if="result" :type="result.error ? 'error' : 'success'" class="mt-4" closable>
          <div v-if="!result.error">
            ایجاد شده: <strong>{{ result.created }}</strong> |
            بروزرسانی شده: <strong>{{ result.updated }}</strong> |
            رد شده: <strong>{{ result.skipped }}</strong>
          </div>
          <div v-else>{{ result.msg }}</div>
        </v-alert>
      </v-card-text>
    </v-card>
  </v-container>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import axios from 'axios'
import { useApplicationStore } from '../../../../stores/applicationStore'

const appStore = useApplicationStore()
function apiHeaders() { return { activeBid: appStore.activeBid, activeYear: appStore.activeYear, activeMoney: appStore.activeMoney } }

const columns = ['نام مرکز *', 'استان', 'شهر', 'نوع مرکز', 'پتانسیل(1-5)', 'دسته‌بندی لید', 'وضعیت CRM', 'تلفن', 'آدرس', 'تاریخ پیگیری', 'برچسب‌ها']

const file = ref<File | File[] | null>(null)
const mode = ref('skip')
const previewing = ref(false)
const importing = ref(false)
const previewData = ref<any[]>([])
const totalRows = ref(0)
const previewDone = ref(false)
const result = ref<any>(null)

function downloadTemplate() {
  const a = document.createElement('a')
  a.href = '/api/acc/crm/import/template'
  a.download = 'centers_template.xlsx'
  // Add headers via fetch
  fetch('/api/acc/crm/import/template', { headers: apiHeaders() as any })
    .then(r => r.blob()).then(blob => {
      const url = URL.createObjectURL(blob)
      a.href = url
      a.click()
      URL.revokeObjectURL(url)
    })
}

async function preview() {
  if (!file.value) return
  previewing.value = true
  previewDone.value = false
  result.value = null
  const fd = new FormData()
  const f = Array.isArray(file.value) ? file.value[0] : file.value
  fd.append('file', f)
  try {
    const { data } = await axios.post('/api/acc/crm/import/preview', fd, { headers: { ...apiHeaders(), 'Content-Type': 'multipart/form-data' } })
    if (data.result === 1) {
      previewData.value = data.preview ?? []
      totalRows.value = data.total ?? 0
      previewDone.value = true
    } else {
      result.value = { error: true, msg: data.msg ?? 'خطا در پیش‌نمایش' }
    }
  } finally { previewing.value = false }
}

async function runImport() {
  if (!file.value) return
  importing.value = true
  result.value = null
  const fd = new FormData()
  const f = Array.isArray(file.value) ? file.value[0] : file.value
  fd.append('file', f)
  fd.append('mode', mode.value)
  try {
    const { data } = await axios.post('/api/acc/crm/import/run', fd, { headers: { ...apiHeaders(), 'Content-Type': 'multipart/form-data' } })
    if (data.result === 1) {
      result.value = { created: data.created, updated: data.updated, skipped: data.skipped }
    } else {
      result.value = { error: true, msg: data.msg ?? 'خطا در ایمپورت' }
    }
  } finally { importing.value = false }
}
</script>
