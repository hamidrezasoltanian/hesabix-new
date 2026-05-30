<template>
  <v-toolbar color="toolbar" title="تحلیل کسب‌وکار">
    <template v-slot:prepend>
      <v-tooltip text="بازگشت" location="bottom">
        <template v-slot:activator="{ props }">
          <v-btn v-bind="props" @click="$router.back()" class="d-none d-sm-flex" variant="text" icon="mdi-arrow-right" />
        </template>
      </v-tooltip>
    </template>
    <v-spacer />
    <v-btn icon="mdi-refresh" color="primary" @click="loadCurrentTab" :loading="loading" />
  </v-toolbar>

  <v-container fluid class="pa-4">
    <!-- انتخاب ماه -->
    <v-row class="mb-4">
      <v-col cols="12" sm="4">
        <v-text-field
          v-model="selectedMonth"
          label="ماه (YYYY/MM)"
          variant="outlined"
          density="compact"
          placeholder="1403/01"
          prepend-inner-icon="mdi-calendar"
          @change="loadCurrentTab"
        />
      </v-col>
    </v-row>

    <!-- تب‌ها -->
    <v-tabs v-model="activeTab" color="primary" class="mb-4">
      <v-tab value="profit">
        <v-icon start>mdi-chart-line</v-icon>
        سودآوری
      </v-tab>
      <v-tab value="velocity">
        <v-icon start>mdi-speedometer</v-icon>
        سرعت گردش
      </v-tab>
      <v-tab value="forecast">
        <v-icon start>mdi-chart-timeline-variant</v-icon>
        پیش‌بینی خرید
      </v-tab>
    </v-tabs>

    <v-window v-model="activeTab">
      <!-- تب سودآوری -->
      <v-window-item value="profit">
        <v-card rounded="lg">
          <v-card-title class="pa-4 text-subtitle-1">گزارش سودآوری</v-card-title>
          <v-data-table
            :headers="profitHeaders"
            :items="profitItems"
            :loading="loading"
            density="compact"
            class="elevation-0 text-center"
          >
            <template v-slot:item.margin="{ item }">
              <span :class="item.margin > 0 ? 'text-success font-weight-bold' : 'text-error font-weight-bold'">
                {{ item.margin }}%
              </span>
            </template>
            <template v-slot:item.profit="{ item }">
              <span :class="item.profit > 0 ? 'text-success' : 'text-error'">
                {{ formatNumber(item.profit) }}
              </span>
            </template>
            <template v-slot:item.revenue="{ item }">{{ formatNumber(item.revenue) }}</template>
            <template v-slot:item.cost="{ item }">{{ formatNumber(item.cost) }}</template>
            <template v-slot:empty>
              <div class="text-center pa-4 text-grey">داده‌ای وجود ندارد</div>
            </template>
          </v-data-table>
        </v-card>
      </v-window-item>

      <!-- تب سرعت گردش -->
      <v-window-item value="velocity">
        <v-card rounded="lg">
          <v-card-title class="pa-4 text-subtitle-1">سرعت گردش کالا</v-card-title>
          <v-data-table
            :headers="velocityHeaders"
            :items="velocityItems"
            :loading="loading"
            density="compact"
            class="elevation-0 text-center"
          >
            <template v-slot:item.daysOfStock="{ item }">
              <span :class="item.daysOfStock < 7 ? 'text-error font-weight-bold' : ''">
                {{ item.daysOfStock }} روز
              </span>
            </template>
            <template v-slot:empty>
              <div class="text-center pa-4 text-grey">داده‌ای وجود ندارد</div>
            </template>
          </v-data-table>
        </v-card>
      </v-window-item>

      <!-- تب پیش‌بینی خرید -->
      <v-window-item value="forecast">
        <v-card rounded="lg">
          <v-card-title class="pa-4 text-subtitle-1">پیش‌بینی خرید</v-card-title>
          <v-data-table
            :headers="forecastHeaders"
            :items="forecastItems"
            :loading="loading"
            density="compact"
            class="elevation-0 text-center"
          >
            <template v-slot:item="{ item }">
              <tr :class="item.suggestedOrderQty > 0 ? 'bg-yellow-lighten-5' : ''">
                <td class="text-center">{{ item.commodityName }}</td>
                <td class="text-center">{{ item.currentStock }}</td>
                <td class="text-center">{{ item.avgDailyUsage }}</td>
                <td class="text-center">
                  <span :class="item.suggestedOrderQty > 0 ? 'text-error font-weight-bold' : ''">
                    {{ item.suggestedOrderQty }}
                  </span>
                </td>
                <td class="text-center">{{ item.estimatedRunout }}</td>
              </tr>
            </template>
            <template v-slot:empty>
              <div class="text-center pa-4 text-grey">داده‌ای وجود ندارد</div>
            </template>
          </v-data-table>
        </v-card>
      </v-window-item>
    </v-window>
  </v-container>

  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">
    {{ snackbar.message }}
  </v-snackbar>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import axios from 'axios'

const loading = ref(false)
const activeTab = ref('profit')
const selectedMonth = ref('')
const profitItems = ref([])
const velocityItems = ref([])
const forecastItems = ref([])
const snackbar = ref({ show: false, message: '', color: 'success' })

const profitHeaders = [
  { title: 'کالا', key: 'commodityName', align: 'center' },
  { title: 'فروش', key: 'soldQty', align: 'center' },
  { title: 'درآمد', key: 'revenue', align: 'center' },
  { title: 'بهای تمام شده', key: 'cost', align: 'center' },
  { title: 'سود', key: 'profit', align: 'center' },
  { title: 'حاشیه سود', key: 'margin', align: 'center' }
]

const velocityHeaders = [
  { title: 'کالا', key: 'commodityName', align: 'center' },
  { title: 'موجودی جاری', key: 'currentStock', align: 'center' },
  { title: 'خروج ۳۰ روز', key: 'output30Days', align: 'center' },
  { title: 'امتیاز گردش', key: 'velocityScore', align: 'center' },
  { title: 'روز موجودی', key: 'daysOfStock', align: 'center' }
]

const forecastHeaders = [
  { title: 'کالا', key: 'commodityName', align: 'center' },
  { title: 'موجودی جاری', key: 'currentStock', align: 'center' },
  { title: 'مصرف روزانه', key: 'avgDailyUsage', align: 'center' },
  { title: 'پیشنهاد سفارش', key: 'suggestedOrderQty', align: 'center' },
  { title: 'تاریخ اتمام', key: 'estimatedRunout', align: 'center' }
]

function formatNumber(value) {
  if (!value) return '0'
  return Number(value).toLocaleString('fa-IR')
}

function showSnack(message, color = 'success') {
  snackbar.value = { show: true, message, color }
}

async function loadProfit() {
  loading.value = true
  try {
    const res = await axios.post('/api/storeroom/analytics/profit', { month: selectedMonth.value })
    profitItems.value = res.data?.data || res.data || []
  } catch (e) {
    console.error(e)
    showSnack('خطا در بارگذاری سودآوری', 'error')
  } finally {
    loading.value = false
  }
}

async function loadVelocity() {
  loading.value = true
  try {
    const res = await axios.post('/api/storeroom/analytics/velocity', { month: selectedMonth.value })
    velocityItems.value = res.data?.data || res.data || []
  } catch (e) {
    console.error(e)
    showSnack('خطا در بارگذاری سرعت گردش', 'error')
  } finally {
    loading.value = false
  }
}

async function loadForecast() {
  loading.value = true
  try {
    const res = await axios.post('/api/storeroom/analytics/forecast', { month: selectedMonth.value })
    forecastItems.value = res.data?.data || res.data || []
  } catch (e) {
    console.error(e)
    showSnack('خطا در بارگذاری پیش‌بینی', 'error')
  } finally {
    loading.value = false
  }
}

function loadCurrentTab() {
  if (activeTab.value === 'profit') loadProfit()
  else if (activeTab.value === 'velocity') loadVelocity()
  else if (activeTab.value === 'forecast') loadForecast()
}

watch(activeTab, loadCurrentTab)

onMounted(() => {
  // تنظیم ماه جاری به صورت پیش‌فرض
  const now = new Date()
  selectedMonth.value = `${now.getFullYear()}/${String(now.getMonth() + 1).padStart(2, '0')}`
  loadCurrentTab()
})
</script>

<style scoped>
:deep(.v-data-table-header th) { text-align: center !important; }
</style>
