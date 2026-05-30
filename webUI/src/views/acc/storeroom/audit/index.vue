<template>
  <v-toolbar color="toolbar" title="لاگ فعالیت انبار">
    <template v-slot:prepend>
      <v-tooltip text="بازگشت" location="bottom">
        <template v-slot:activator="{ props }">
          <v-btn v-bind="props" @click="$router.back()" class="d-none d-sm-flex" variant="text" icon="mdi-arrow-right" />
        </template>
      </v-tooltip>
    </template>
    <v-spacer />
    <v-btn icon="mdi-export" color="primary" variant="text" @click="exportData">
      <v-icon>mdi-download</v-icon>
    </v-btn>
  </v-toolbar>

  <v-container fluid class="pa-4">
    <!-- فرم فیلتر -->
    <v-card class="mb-4" rounded="lg">
      <v-card-title class="pa-4 text-subtitle-1">فیلترها</v-card-title>
      <v-card-text>
        <v-row>
          <v-col cols="12" sm="6" md="3">
            <v-text-field
              v-model="filters.userMobile"
              label="موبایل کاربر"
              variant="outlined"
              density="compact"
              prepend-inner-icon="mdi-account"
            />
          </v-col>
          <v-col cols="12" sm="6" md="3">
            <v-select
              v-model="filters.actionType"
              :items="actionTypeOptions"
              label="نوع عملیات"
              variant="outlined"
              density="compact"
            />
          </v-col>
          <v-col cols="12" sm="6" md="3">
            <v-text-field
              v-model="filters.dateFrom"
              label="از تاریخ"
              variant="outlined"
              density="compact"
              placeholder="1403/01/01"
            />
          </v-col>
          <v-col cols="12" sm="6" md="3">
            <v-text-field
              v-model="filters.dateTo"
              label="تا تاریخ"
              variant="outlined"
              density="compact"
              placeholder="1403/12/29"
            />
          </v-col>
        </v-row>
        <v-row>
          <v-col cols="12" class="d-flex gap-2">
            <v-btn color="primary" prepend-icon="mdi-magnify" @click="loadData" :loading="loading">جستجو</v-btn>
            <v-btn variant="outlined" @click="resetFilters">پاک کردن</v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- جدول لاگ -->
    <v-card rounded="lg">
      <v-data-table
        :headers="headers"
        :items="items"
        :loading="loading"
        density="compact"
        class="elevation-0 text-center"
      >
        <template v-slot:item.actionType="{ item }">
          <v-chip :color="actionTypeColor(item.actionType)" size="small">{{ actionTypeLabel(item.actionType) }}</v-chip>
        </template>
        <template v-slot:empty>
          <div class="text-center pa-6 text-grey">
            <v-icon size="48" class="mb-2">mdi-clipboard-text-off</v-icon>
            <div>لاگی وجود ندارد</div>
          </div>
        </template>
      </v-data-table>
    </v-card>
  </v-container>

  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">
    {{ snackbar.message }}
  </v-snackbar>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const loading = ref(false)
const items = ref([])
const snackbar = ref({ show: false, message: '', color: 'success' })

const filters = ref({
  userMobile: '',
  actionType: 'all',
  dateFrom: '',
  dateTo: ''
})

const actionTypeOptions = [
  { title: 'همه', value: 'all' },
  { title: 'ورود', value: 'entry' },
  { title: 'خروج', value: 'exit' },
  { title: 'انبارگردانی', value: 'count' },
  { title: 'سفارش خرید', value: 'po' },
  { title: 'Recall', value: 'recall' }
]

const headers = [
  { title: 'زمان', key: 'timestamp', align: 'center' },
  { title: 'کاربر', key: 'user', align: 'center' },
  { title: 'نوع عملیات', key: 'actionType', align: 'center' },
  { title: 'موجودیت', key: 'entity', align: 'center' },
  { title: 'جزئیات', key: 'details', align: 'center' }
]

function actionTypeColor(type) {
  const map = { entry: 'success', exit: 'error', count: 'purple', po: 'blue', recall: 'orange' }
  return map[type] || 'grey'
}

function actionTypeLabel(type) {
  const map = { entry: 'ورود', exit: 'خروج', count: 'انبارگردانی', po: 'سفارش خرید', recall: 'Recall' }
  return map[type] || type
}

function showSnack(message, color = 'success') {
  snackbar.value = { show: true, message, color }
}

function resetFilters() {
  filters.value = { userMobile: '', actionType: 'all', dateFrom: '', dateTo: '' }
  loadData()
}

function exportData() {
  showSnack('در حال توسعه', 'info')
}

async function loadData() {
  loading.value = true
  try {
    const res = await axios.post('/api/storeroom/audit/list', filters.value)
    items.value = res.data?.data || res.data || []
  } catch (e) {
    console.error(e)
    showSnack('خطا در بارگذاری', 'error')
  } finally {
    loading.value = false
  }
}

onMounted(loadData)
</script>

<style scoped>
:deep(.v-data-table-header th) { text-align: center !important; }
</style>
