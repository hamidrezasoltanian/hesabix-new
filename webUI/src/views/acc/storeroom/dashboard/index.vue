<template>
  <v-toolbar color="toolbar" title="داشبورد انبار">
    <template v-slot:prepend>
      <v-tooltip text="بازگشت" location="bottom">
        <template v-slot:activator="{ props }">
          <v-btn v-bind="props" @click="$router.back()" class="d-none d-sm-flex" variant="text" icon="mdi-arrow-right" />
        </template>
      </v-tooltip>
    </template>
    <v-spacer />
    <v-btn icon="mdi-refresh" color="primary" @click="loadAll" :loading="loading" />
  </v-toolbar>

  <v-container fluid class="pa-4">
    <!-- کارت‌های آماری -->
    <v-row class="mb-4">
      <v-col cols="12" sm="6" md="3">
        <v-card color="blue-darken-1" theme="dark" rounded="lg">
          <v-card-text class="text-center pa-6">
            <v-icon size="40" class="mb-2">mdi-package-variant-closed</v-icon>
            <div class="text-h4 font-weight-bold">{{ stats.totalItems ?? '-' }}</div>
            <div class="text-subtitle-1 mt-1">موجودی اقلام</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card color="orange-darken-2" theme="dark" rounded="lg">
          <v-card-text class="text-center pa-6">
            <v-icon size="40" class="mb-2">mdi-alert-circle</v-icon>
            <div class="text-h4 font-weight-bold">{{ stats.lowStockCount ?? '-' }}</div>
            <div class="text-subtitle-1 mt-1">هشدار کم‌موجودی</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card color="red-darken-2" theme="dark" rounded="lg">
          <v-card-text class="text-center pa-6">
            <v-icon size="40" class="mb-2">mdi-calendar-alert</v-icon>
            <div class="text-h4 font-weight-bold">{{ stats.nearExpiryCount ?? '-' }}</div>
            <div class="text-subtitle-1 mt-1">هشدار انقضا</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card color="green-darken-2" theme="dark" rounded="lg">
          <v-card-text class="text-center pa-6">
            <v-icon size="40" class="mb-2">mdi-swap-horizontal</v-icon>
            <div class="text-h4 font-weight-bold">{{ stats.todayTransactions ?? '-' }}</div>
            <div class="text-subtitle-1 mt-1">تراکنش‌های امروز</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- خلاصه هشدارها -->
    <v-row class="mb-4" v-if="stats.lowStockCount > 0 || stats.nearExpiryCount > 0">
      <v-col cols="12">
        <v-card rounded="lg">
          <v-card-title class="text-subtitle-1">خلاصه هشدارها</v-card-title>
          <v-card-text>
            <v-chip
              v-if="stats.lowStockCount > 0"
              color="orange"
              class="ma-1"
              prepend-icon="mdi-alert-circle"
            >
              {{ stats.lowStockCount }} کالای کم‌موجودی
            </v-chip>
            <v-chip
              v-if="stats.nearExpiryCount > 0"
              color="red"
              class="ma-1"
              prepend-icon="mdi-calendar-alert"
            >
              {{ stats.nearExpiryCount }} کالای نزدیک انقضا
            </v-chip>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- تراکنش‌های اخیر -->
    <v-row>
      <v-col cols="12">
        <v-card rounded="lg">
          <v-card-title class="text-subtitle-1 pa-4">تراکنش‌های اخیر</v-card-title>
          <v-data-table
            :headers="recentHeaders"
            :items="recentItems"
            :loading="loadingRecent"
            density="compact"
            class="elevation-0"
          >
            <template v-slot:item.type="{ item }">
              <v-chip
                :color="typeColor(item.type)"
                size="small"
              >{{ typeLabel(item.type) }}</v-chip>
            </template>
            <template v-slot:item.status="{ item }">
              <v-chip
                :color="item.status === 'confirmed' ? 'success' : 'warning'"
                size="small"
              >{{ item.status === 'confirmed' ? 'تأیید شده' : 'در انتظار' }}</v-chip>
            </template>
            <template v-slot:empty>
              <div class="text-center pa-4 text-grey">تراکنشی وجود ندارد</div>
            </template>
          </v-data-table>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const loading = ref(false)
const loadingRecent = ref(false)

const stats = ref({
  totalItems: 0,
  lowStockCount: 0,
  nearExpiryCount: 0,
  todayTransactions: 0
})

const recentItems = ref([])

const recentHeaders = [
  { title: 'کد', key: 'code', align: 'center' },
  { title: 'نوع', key: 'type', align: 'center' },
  { title: 'تعداد اقلام', key: 'commodityCount', align: 'center' },
  { title: 'طرف حساب', key: 'person', align: 'center' },
  { title: 'انبار', key: 'storeroom', align: 'center' },
  { title: 'تاریخ', key: 'date', align: 'center' },
  { title: 'وضعیت', key: 'status', align: 'center' }
]

function typeColor(type) {
  const map = { entry: 'success', exit: 'error', transfer: 'blue', count: 'purple' }
  return map[type] || 'grey'
}

function typeLabel(type) {
  const map = { entry: 'ورود', exit: 'خروج', transfer: 'انتقال', count: 'انبارگردانی' }
  return map[type] || type
}

async function loadStats() {
  loading.value = true
  try {
    const res = await axios.get('/api/storeroom/dashboard/stats')
    stats.value = res.data || {}
  } catch (e) {
    console.error('Error loading stats:', e)
  } finally {
    loading.value = false
  }
}

async function loadRecent() {
  loadingRecent.value = true
  try {
    const res = await axios.post('/api/storeroom/dashboard/recent')
    recentItems.value = res.data?.data || res.data || []
  } catch (e) {
    console.error('Error loading recent:', e)
  } finally {
    loadingRecent.value = false
  }
}

async function loadAll() {
  await Promise.all([loadStats(), loadRecent()])
}

onMounted(() => {
  loadAll()
})
</script>

<style scoped>
:deep(.v-data-table-header th) {
  text-align: center !important;
}
</style>
