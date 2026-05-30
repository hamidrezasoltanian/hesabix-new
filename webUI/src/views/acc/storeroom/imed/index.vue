<template>
  <v-toolbar color="toolbar" title="انبار مجازی IMED">
    <template v-slot:prepend>
      <v-tooltip text="بازگشت" location="bottom">
        <template v-slot:activator="{ props }">
          <v-btn v-bind="props" @click="$router.back()" class="d-none d-sm-flex" variant="text" icon="mdi-arrow-right" />
        </template>
      </v-tooltip>
    </template>
    <v-spacer />
    <v-tooltip text="وب‌سایت IMED" location="bottom">
      <template v-slot:activator="{ props }">
        <v-btn v-bind="props" icon="mdi-open-in-new" href="https://imed.ir" target="_blank" variant="text" />
      </template>
    </v-tooltip>
    <v-btn color="warning" prepend-icon="mdi-check-all" @click="bulkMarkDialog = true" class="me-2">ثبت گروهی</v-btn>
    <v-btn icon="mdi-refresh" color="primary" @click="loadAll" :loading="loading" />
  </v-toolbar>

  <v-container fluid class="pa-4">
    <!-- کارت‌های آماری -->
    <v-row class="mb-4">
      <v-col cols="12" sm="4">
        <v-card color="success" theme="dark" rounded="lg">
          <v-card-text class="text-center pa-4">
            <v-icon size="32" class="mb-1">mdi-check-circle</v-icon>
            <div class="text-h5 font-weight-bold">{{ stats.registered ?? '-' }}</div>
            <div class="text-body-2">ثبت شده</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="4">
        <v-card color="orange" theme="dark" rounded="lg">
          <v-card-text class="text-center pa-4">
            <v-icon size="32" class="mb-1">mdi-clock-outline</v-icon>
            <div class="text-h5 font-weight-bold">{{ stats.pending ?? '-' }}</div>
            <div class="text-body-2">در انتظار</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="4">
        <v-card color="error" theme="dark" rounded="lg">
          <v-card-text class="text-center pa-4">
            <v-icon size="32" class="mb-1">mdi-close-circle</v-icon>
            <div class="text-h5 font-weight-bold">{{ stats.notRegistered ?? '-' }}</div>
            <div class="text-body-2">ثبت نشده</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- لیست اقلام -->
    <v-card rounded="lg">
      <v-card-title class="pa-4 text-subtitle-1">لیست اقلام IMED</v-card-title>
      <v-data-table
        :headers="headers"
        :items="items"
        :loading="loading"
        density="compact"
        class="elevation-0 text-center"
      >
        <template v-slot:item.imedStatus="{ item }">
          <v-chip :color="imedStatusColor(item.imedStatus)" size="small">{{ imedStatusLabel(item.imedStatus) }}</v-chip>
        </template>
        <template v-slot:item.actions="{ item }">
          <v-btn
            v-if="item.imedStatus !== 'registered'"
            size="small"
            color="success"
            variant="text"
            icon="mdi-check"
            @click="openMarkDialog(item)"
          />
        </template>
        <template v-slot:empty>
          <div class="text-center pa-4 text-grey">آیتمی وجود ندارد</div>
        </template>
      </v-data-table>
    </v-card>
  </v-container>

  <!-- دیالوگ ثبت تکی -->
  <v-dialog v-model="markDialog" max-width="400">
    <v-card>
      <v-toolbar color="toolbar" title="ثبت در IMED">
        <v-spacer />
        <v-btn icon="mdi-close" variant="text" @click="markDialog = false" />
      </v-toolbar>
      <v-card-text>
        <v-text-field
          v-model="markForm.imedRef"
          label="شماره مرجع IMED"
          variant="outlined"
          density="compact"
          placeholder="شماره مرجع را وارد کنید"
        />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="markDialog = false">انصراف</v-btn>
        <v-btn color="success" variant="elevated" @click="markRegistered" :loading="saving">ثبت</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- دیالوگ ثبت گروهی -->
  <v-dialog v-model="bulkMarkDialog" max-width="400">
    <v-card>
      <v-toolbar color="toolbar" title="ثبت گروهی در IMED">
        <v-spacer />
        <v-btn icon="mdi-close" variant="text" @click="bulkMarkDialog = false" />
      </v-toolbar>
      <v-card-text>
        <p class="text-body-2 mb-3">آیا می‌خواهید همه اقلام ثبت نشده را به صورت گروهی علامت‌گذاری کنید؟</p>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="bulkMarkDialog = false">انصراف</v-btn>
        <v-btn color="warning" variant="elevated" @click="bulkMark" :loading="saving">ثبت گروهی</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">
    {{ snackbar.message }}
  </v-snackbar>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const loading = ref(false)
const saving = ref(false)
const markDialog = ref(false)
const bulkMarkDialog = ref(false)
const items = ref([])
const stats = ref({ registered: 0, pending: 0, notRegistered: 0 })
const selectedItem = ref(null)
const snackbar = ref({ show: false, message: '', color: 'success' })
const markForm = ref({ imedRef: '' })

const headers = [
  { title: 'کالا', key: 'commodityName', align: 'center' },
  { title: 'شماره لات', key: 'lotNo', align: 'center' },
  { title: 'کد حواله', key: 'ticketCode', align: 'center' },
  { title: 'تاریخ', key: 'date', align: 'center' },
  { title: 'وضعیت IMED', key: 'imedStatus', align: 'center' },
  { title: 'مرجع IMED', key: 'imedRef', align: 'center' },
  { title: 'عملیات', key: 'actions', align: 'center', sortable: false }
]

function imedStatusColor(status) {
  const map = { not_registered: 'error', pending: 'orange', registered: 'success' }
  return map[status] || 'grey'
}

function imedStatusLabel(status) {
  const map = { not_registered: 'ثبت نشده', pending: 'در انتظار', registered: 'ثبت شده' }
  return map[status] || status
}

function showSnack(message, color = 'success') {
  snackbar.value = { show: true, message, color }
}

function openMarkDialog(item) {
  selectedItem.value = item
  markForm.value = { imedRef: '' }
  markDialog.value = true
}

async function loadStats() {
  try {
    const res = await axios.get('/api/storeroom/imed/stats')
    stats.value = res.data || {}
  } catch (e) {
    console.error(e)
  }
}

async function loadItems() {
  loading.value = true
  try {
    const res = await axios.get('/api/storeroom/imed/list')
    items.value = res.data?.data || res.data || []
  } catch (e) {
    console.error(e)
    showSnack('خطا در بارگذاری', 'error')
  } finally {
    loading.value = false
  }
}

async function loadAll() {
  await Promise.all([loadStats(), loadItems()])
}

async function markRegistered() {
  saving.value = true
  try {
    await axios.post(`/api/storeroom/imed/mark/${selectedItem.value.id}`, { imedRef: markForm.value.imedRef })
    markDialog.value = false
    showSnack('در IMED ثبت شد')
    await loadAll()
  } catch (e) {
    console.error(e)
    showSnack('خطا در ثبت', 'error')
  } finally {
    saving.value = false
  }
}

async function bulkMark() {
  saving.value = true
  try {
    await axios.post('/api/storeroom/imed/bulk-mark')
    bulkMarkDialog.value = false
    showSnack('ثبت گروهی انجام شد')
    await loadAll()
  } catch (e) {
    console.error(e)
    showSnack('خطا در ثبت گروهی', 'error')
  } finally {
    saving.value = false
  }
}

onMounted(loadAll)
</script>

<style scoped>
:deep(.v-data-table-header th) { text-align: center !important; }
</style>
