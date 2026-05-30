<template>
  <v-toolbar color="toolbar" title="گزارش‌های تخصصی انبار">
    <template v-slot:prepend>
      <v-tooltip text="بازگشت" location="bottom">
        <template v-slot:activator="{ props }">
          <v-btn v-bind="props" @click="$router.back()" class="d-none d-sm-flex" variant="text" icon="mdi-arrow-right" />
        </template>
      </v-tooltip>
    </template>
    <v-spacer />
    <v-btn color="primary" prepend-icon="mdi-download" @click="exportReport" variant="outlined">خروجی Excel</v-btn>
  </v-toolbar>

  <v-container fluid class="pa-4">
    <v-tabs v-model="activeTab" color="primary" class="mb-4">
      <v-tab value="inventory">
        <v-icon start>mdi-package-variant</v-icon>
        موجودی
      </v-tab>
      <v-tab value="movement">
        <v-icon start>mdi-swap-horizontal</v-icon>
        گردش کالا
      </v-tab>
      <v-tab value="period">
        <v-icon start>mdi-compare</v-icon>
        مقایسه دوره‌ای
      </v-tab>
      <v-tab value="imed" @click="$router.push('/acc/storeroom/imed')">
        <v-icon start>mdi-web</v-icon>
        IMED
      </v-tab>
    </v-tabs>

    <v-window v-model="activeTab">
      <!-- تب موجودی -->
      <v-window-item value="inventory">
        <v-row class="mb-4">
          <v-col cols="12" sm="6" md="4">
            <v-text-field
              v-model="inventoryFilter.storeroomId"
              label="شناسه انبار"
              variant="outlined"
              density="compact"
              prepend-inner-icon="mdi-store"
              @keyup.enter="loadInventory"
            />
          </v-col>
          <v-col cols="12" sm="4" md="2">
            <v-btn color="primary" @click="loadInventory" :loading="loading.inventory" block>جستجو</v-btn>
          </v-col>
        </v-row>

        <v-card rounded="lg">
          <v-data-table
            :headers="inventoryHeaders"
            :items="inventoryItems"
            :loading="loading.inventory"
            density="compact"
            class="elevation-0 text-center"
          >
            <template v-slot:item.value="{ item }">{{ formatNumber(item.value) }}</template>
            <template v-slot:empty>
              <div class="text-center pa-4 text-grey">داده‌ای وجود ندارد</div>
            </template>
          </v-data-table>
        </v-card>
      </v-window-item>

      <!-- تب گردش کالا -->
      <v-window-item value="movement">
        <v-row class="mb-4">
          <v-col cols="12" sm="4">
            <v-text-field v-model="movementFilter.dateFrom" label="از تاریخ" variant="outlined" density="compact" placeholder="1403/01/01" />
          </v-col>
          <v-col cols="12" sm="4">
            <v-text-field v-model="movementFilter.dateTo" label="تا تاریخ" variant="outlined" density="compact" placeholder="1403/12/29" />
          </v-col>
          <v-col cols="12" sm="4">
            <v-text-field v-model="movementFilter.commodity" label="کالا" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="12" sm="2">
            <v-btn color="primary" @click="loadMovement" :loading="loading.movement" block>جستجو</v-btn>
          </v-col>
        </v-row>

        <v-card rounded="lg">
          <v-data-table
            :headers="movementHeaders"
            :items="movementItems"
            :loading="loading.movement"
            density="compact"
            class="elevation-0 text-center"
          >
            <template v-slot:item.type="{ item }">
              <v-chip :color="item.type === 'entry' ? 'success' : 'error'" size="small">
                {{ item.type === 'entry' ? 'ورود' : 'خروج' }}
              </v-chip>
            </template>
            <template v-slot:empty>
              <div class="text-center pa-4 text-grey">داده‌ای وجود ندارد</div>
            </template>
          </v-data-table>
        </v-card>
      </v-window-item>

      <!-- تب مقایسه دوره‌ای -->
      <v-window-item value="period">
        <v-row class="mb-4">
          <v-col cols="12" sm="4">
            <v-text-field v-model="periodFilter.month1" label="دوره اول (YYYY/MM)" variant="outlined" density="compact" placeholder="1402/01" />
          </v-col>
          <v-col cols="12" sm="4">
            <v-text-field v-model="periodFilter.month2" label="دوره دوم (YYYY/MM)" variant="outlined" density="compact" placeholder="1403/01" />
          </v-col>
          <v-col cols="12" sm="2">
            <v-btn color="primary" @click="loadPeriodComparison" :loading="loading.period" block>مقایسه</v-btn>
          </v-col>
        </v-row>

        <v-card rounded="lg">
          <v-data-table
            :headers="periodHeaders"
            :items="periodItems"
            :loading="loading.period"
            density="compact"
            class="elevation-0 text-center"
          >
            <template v-slot:item.diffCount="{ item }">
              <span :class="item.diffCount > 0 ? 'text-success' : item.diffCount < 0 ? 'text-error' : ''">
                {{ item.diffCount > 0 ? '+' : '' }}{{ item.diffCount }}
              </span>
            </template>
            <template v-slot:empty>
              <div class="text-center pa-4 text-grey">داده‌ای وجود ندارد</div>
            </template>
          </v-data-table>
        </v-card>
      </v-window-item>

      <!-- تب IMED -->
      <v-window-item value="imed">
        <v-card rounded="lg" class="text-center pa-8">
          <v-icon size="64" color="primary" class="mb-4">mdi-web</v-icon>
          <div class="text-h6 mb-2">گزارش IMED</div>
          <div class="text-body-2 text-grey mb-4">برای مشاهده گزارش IMED به صفحه اختصاصی مراجعه کنید</div>
          <v-btn color="primary" to="/acc/storeroom/imed">رفتن به انبار مجازی IMED</v-btn>
        </v-card>
      </v-window-item>
    </v-window>
  </v-container>

  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">
    {{ snackbar.message }}
  </v-snackbar>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const activeTab = ref('inventory')
const snackbar = ref({ show: false, message: '', color: 'success' })

const loading = ref({ inventory: false, movement: false, period: false })
const inventoryItems = ref([])
const movementItems = ref([])
const periodItems = ref([])

const inventoryFilter = ref({ storeroomId: '' })
const movementFilter = ref({ dateFrom: '', dateTo: '', commodity: '' })
const periodFilter = ref({ month1: '', month2: '' })

const inventoryHeaders = [
  { title: 'کالا', key: 'commodityName', align: 'center' },
  { title: 'شماره لات', key: 'lotNo', align: 'center' },
  { title: 'موجودی', key: 'qty', align: 'center' },
  { title: 'ارزش', key: 'value', align: 'center' }
]

const movementHeaders = [
  { title: 'تاریخ', key: 'date', align: 'center' },
  { title: 'کالا', key: 'commodityName', align: 'center' },
  { title: 'نوع', key: 'type', align: 'center' },
  { title: 'تعداد', key: 'qty', align: 'center' },
  { title: 'کد حواله', key: 'ticketCode', align: 'center' }
]

const periodHeaders = [
  { title: 'کالا', key: 'commodityName', align: 'center' },
  { title: 'تراکنش دوره ۱', key: 'count1', align: 'center' },
  { title: 'حجم دوره ۱', key: 'volume1', align: 'center' },
  { title: 'تراکنش دوره ۲', key: 'count2', align: 'center' },
  { title: 'حجم دوره ۲', key: 'volume2', align: 'center' },
  { title: 'تفاوت تراکنش', key: 'diffCount', align: 'center' }
]

function formatNumber(value) {
  if (!value) return '0'
  return Number(value).toLocaleString('fa-IR')
}

function showSnack(message, color = 'success') {
  snackbar.value = { show: true, message, color }
}

function exportReport() {
  showSnack('در حال توسعه', 'info')
}

async function loadInventory() {
  loading.value.inventory = true
  try {
    const sid = inventoryFilter.value.storeroomId || 'all'
    const res = await axios.get(`/api/storeroom/commodity/list/${sid}`)
    inventoryItems.value = res.data?.data || res.data || []
  } catch (e) {
    console.error(e)
    showSnack('خطا در بارگذاری موجودی', 'error')
  } finally {
    loading.value.inventory = false
  }
}

async function loadMovement() {
  loading.value.movement = true
  try {
    const res = await axios.post('/api/storeroom/tickets/list/all', movementFilter.value)
    movementItems.value = res.data?.data || res.data || []
  } catch (e) {
    console.error(e)
    showSnack('خطا در بارگذاری گردش', 'error')
  } finally {
    loading.value.movement = false
  }
}

async function loadPeriodComparison() {
  loading.value.period = true
  try {
    const res = await axios.post('/api/storeroom/analytics/period-compare', periodFilter.value)
    periodItems.value = res.data?.data || res.data || []
  } catch (e) {
    console.error(e)
    showSnack('خطا در بارگذاری مقایسه', 'error')
  } finally {
    loading.value.period = false
  }
}

onMounted(loadInventory)
</script>

<style scoped>
:deep(.v-data-table-header th) { text-align: center !important; }
</style>
