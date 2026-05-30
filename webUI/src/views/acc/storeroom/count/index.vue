<template>
  <v-toolbar color="toolbar" title="انبارگردانی">
    <template v-slot:prepend>
      <v-tooltip text="بازگشت" location="bottom">
        <template v-slot:activator="{ props }">
          <v-btn v-bind="props" @click="$router.back()" class="d-none d-sm-flex" variant="text" icon="mdi-arrow-right" />
        </template>
      </v-tooltip>
    </template>
    <v-spacer />
    <v-btn color="primary" prepend-icon="mdi-plus" @click="newDialog = true">انبارگردانی جدید</v-btn>
  </v-toolbar>

  <v-container fluid class="pa-4">
    <v-data-table
      :headers="headers"
      :items="items"
      :loading="loading"
      density="compact"
      class="elevation-1 text-center"
    >
      <template v-slot:item.status="{ item }">
        <v-chip :color="statusColor(item.status)" size="small">{{ statusLabel(item.status) }}</v-chip>
      </template>
      <template v-slot:item.actions="{ item }">
        <v-btn size="small" icon="mdi-eye" color="primary" variant="text" @click="viewCount(item)" />
        <v-btn
          v-if="item.status === 'open'"
          size="small"
          icon="mdi-check"
          color="success"
          variant="text"
          @click="approveCount(item.id)"
        />
        <v-btn
          v-if="item.status !== 'approved'"
          size="small"
          icon="mdi-delete"
          color="error"
          variant="text"
          @click="confirmDelete(item.id)"
        />
      </template>
      <template v-slot:empty>
        <div class="text-center pa-4 text-grey">انبارگردانی وجود ندارد</div>
      </template>
    </v-data-table>
  </v-container>

  <!-- دیالوگ ایجاد انبارگردانی جدید -->
  <v-dialog v-model="newDialog" max-width="500">
    <v-card>
      <v-toolbar color="toolbar" title="انبارگردانی جدید">
        <v-spacer />
        <v-btn icon="mdi-close" variant="text" @click="newDialog = false" />
      </v-toolbar>
      <v-card-text>
        <v-row>
          <v-col cols="12">
            <v-text-field v-model="newForm.storeroomId" label="شناسه انبار" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="12">
            <v-text-field v-model="newForm.date" label="تاریخ (مثال: 1403/01/01)" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="12">
            <v-textarea v-model="newForm.note" label="یادداشت" variant="outlined" density="compact" rows="2" />
          </v-col>
        </v-row>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="newDialog = false">انصراف</v-btn>
        <v-btn color="primary" variant="elevated" @click="startCount" :loading="saving">شروع</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- دیالوگ مشاهده انبارگردانی -->
  <v-dialog v-model="viewDialog" max-width="900">
    <v-card>
      <v-toolbar color="toolbar" :title="'جزئیات انبارگردانی #' + (selectedCount?.id || '')">
        <v-spacer />
        <v-btn icon="mdi-close" variant="text" @click="viewDialog = false" />
      </v-toolbar>
      <v-card-text>
        <v-data-table
          :headers="itemHeaders"
          :items="countItems"
          :loading="loadingItems"
          density="compact"
          class="elevation-0"
        >
          <template v-slot:item.diff="{ item }">
            <span :class="item.diff < 0 ? 'text-error font-weight-bold' : ''">{{ item.diff }}</span>
          </template>
          <template v-slot:empty>
            <div class="text-center pa-4 text-grey">آیتمی وجود ندارد</div>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>
  </v-dialog>

  <!-- دیالوگ حذف -->
  <v-dialog v-model="deleteDialog.show" max-width="400">
    <v-card>
      <v-card-title>تأیید حذف</v-card-title>
      <v-card-text>آیا از حذف این انبارگردانی مطمئن هستید؟</v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="deleteDialog.show = false">خیر</v-btn>
        <v-btn color="error" variant="text" @click="deleteCount">بله</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- اسنک‌بار -->
  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">
    {{ snackbar.message }}
  </v-snackbar>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const loading = ref(false)
const saving = ref(false)
const loadingItems = ref(false)
const newDialog = ref(false)
const viewDialog = ref(false)
const items = ref([])
const countItems = ref([])
const selectedCount = ref(null)
const deleteDialog = ref({ show: false, id: null })
const snackbar = ref({ show: false, message: '', color: 'success' })

const newForm = ref({ storeroomId: '', date: '', note: '' })

const headers = [
  { title: 'شماره', key: 'id', align: 'center' },
  { title: 'انبار', key: 'storeroomName', align: 'center' },
  { title: 'تاریخ', key: 'date', align: 'center' },
  { title: 'وضعیت', key: 'status', align: 'center' },
  { title: 'تاریخ بسته شدن', key: 'closedDate', align: 'center' },
  { title: 'تعداد اقلام', key: 'itemsCount', align: 'center' },
  { title: 'عملیات', key: 'actions', align: 'center', sortable: false }
]

const itemHeaders = [
  { title: 'کالا', key: 'commodityName', align: 'center' },
  { title: 'شماره لات', key: 'lotNo', align: 'center' },
  { title: 'انقضا', key: 'expiry', align: 'center' },
  { title: 'موجودی سیستم', key: 'systemQty', align: 'center' },
  { title: 'موجودی فیزیکی', key: 'physicalQty', align: 'center' },
  { title: 'اختلاف', key: 'diff', align: 'center' }
]

function statusColor(status) {
  const map = { open: 'blue', approved: 'success', cancelled: 'grey' }
  return map[status] || 'grey'
}

function statusLabel(status) {
  const map = { open: 'باز', approved: 'تأیید شده', cancelled: 'لغو شده' }
  return map[status] || status
}

function showSnack(message, color = 'success') {
  snackbar.value = { show: true, message, color }
}

async function loadData() {
  loading.value = true
  try {
    const res = await axios.get('/api/storeroom/count/list')
    items.value = res.data?.data || res.data || []
  } catch (e) {
    console.error(e)
    showSnack('خطا در بارگذاری داده‌ها', 'error')
  } finally {
    loading.value = false
  }
}

async function startCount() {
  saving.value = true
  try {
    await axios.post('/api/storeroom/count/start', newForm.value)
    newDialog.value = false
    newForm.value = { storeroomId: '', date: '', note: '' }
    showSnack('انبارگردانی با موفقیت شروع شد')
    await loadData()
  } catch (e) {
    console.error(e)
    showSnack('خطا در ثبت انبارگردانی', 'error')
  } finally {
    saving.value = false
  }
}

async function viewCount(item) {
  selectedCount.value = item
  viewDialog.value = true
  loadingItems.value = true
  try {
    const res = await axios.get(`/api/storeroom/count/info/${item.id}`)
    countItems.value = res.data?.items || res.data || []
  } catch (e) {
    console.error(e)
    showSnack('خطا در بارگذاری جزئیات', 'error')
  } finally {
    loadingItems.value = false
  }
}

async function approveCount(id) {
  try {
    await axios.post(`/api/storeroom/count/approve/${id}`)
    showSnack('انبارگردانی تأیید شد')
    await loadData()
  } catch (e) {
    console.error(e)
    showSnack('خطا در تأیید انبارگردانی', 'error')
  }
}

function confirmDelete(id) {
  deleteDialog.value = { show: true, id }
}

async function deleteCount() {
  const id = deleteDialog.value.id
  deleteDialog.value.show = false
  try {
    await axios.delete(`/api/storeroom/count/delete/${id}`)
    showSnack('انبارگردانی حذف شد')
    await loadData()
  } catch (e) {
    console.error(e)
    showSnack('خطا در حذف انبارگردانی', 'error')
  }
}

onMounted(loadData)
</script>

<style scoped>
:deep(.v-data-table-header th) { text-align: center !important; }
</style>
