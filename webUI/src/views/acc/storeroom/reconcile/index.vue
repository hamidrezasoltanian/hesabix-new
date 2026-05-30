<template>
  <v-toolbar color="toolbar" title="تطبیق سه‌گانه موجودی">
    <template v-slot:prepend>
      <v-tooltip text="بازگشت" location="bottom">
        <template v-slot:activator="{ props }">
          <v-btn v-bind="props" @click="$router.back()" class="d-none d-sm-flex" variant="text" icon="mdi-arrow-right" />
        </template>
      </v-tooltip>
    </template>
    <v-spacer />
    <v-btn icon="mdi-refresh" color="primary" @click="loadData" :loading="loading" />
  </v-toolbar>

  <v-container fluid class="pa-4">
    <v-card class="mb-4" rounded="lg">
      <v-card-title class="text-subtitle-1">
        <v-icon class="me-2" color="info">mdi-information</v-icon>
        درباره تطبیق سه‌گانه
      </v-card-title>
      <v-card-text>
        <p>
          تطبیق سه‌گانه موجودی، مقایسه‌ای است بین سه منبع مختلف موجودی کالا:
        </p>
        <v-list density="compact">
          <v-list-item prepend-icon="mdi-clipboard-check" color="blue">
            <v-list-item-title>موجودی فیزیکی</v-list-item-title>
            <v-list-item-subtitle>از آخرین انبارگردانی تأیید شده</v-list-item-subtitle>
          </v-list-item>
          <v-list-item prepend-icon="mdi-database" color="green">
            <v-list-item-title>موجودی سیستم</v-list-item-title>
            <v-list-item-subtitle>بر اساس حواله‌های ثبت شده در سیستم</v-list-item-subtitle>
          </v-list-item>
          <v-list-item prepend-icon="mdi-web" color="orange">
            <v-list-item-title>موجودی ثبت شده IMED</v-list-item-title>
            <v-list-item-subtitle>از سامانه IMED (imed.ir)</v-list-item-subtitle>
          </v-list-item>
        </v-list>
      </v-card-text>
    </v-card>

    <v-card rounded="lg">
      <v-card-title class="text-subtitle-1 pa-4">جدول مقایسه موجودی</v-card-title>
      <v-data-table
        :headers="headers"
        :items="reconcileItems"
        :loading="loading"
        density="compact"
        class="elevation-0 text-center"
      >
        <template v-slot:item.physicalQty="{ item }">
          <span :class="item.physicalQty === null ? 'text-grey' : ''">
            {{ item.physicalQty ?? 'ندارد' }}
          </span>
        </template>
        <template v-slot:item.systemQty="{ item }">
          {{ item.systemQty ?? 0 }}
        </template>
        <template v-slot:item.imedQty="{ item }">
          <span :class="item.imedQty === null ? 'text-grey' : ''">
            {{ item.imedQty ?? 'ندارد' }}
          </span>
        </template>
        <template v-slot:item.discrepancy="{ item }">
          <v-chip
            v-if="item.discrepancy"
            color="error"
            size="small"
          >اختلاف</v-chip>
          <v-chip v-else color="success" size="small">تطابق</v-chip>
        </template>
        <template v-slot:empty>
          <div class="text-center pa-4 text-grey">
            {{ loading ? 'در حال بارگذاری...' : 'داده‌ای برای نمایش وجود ندارد' }}
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
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'

const loading = ref(false)
const countList = ref([])
const imedStats = ref({})
const snackbar = ref({ show: false, message: '', color: 'success' })

const headers = [
  { title: 'کالا', key: 'commodityName', align: 'center' },
  { title: 'موجودی فیزیکی', key: 'physicalQty', align: 'center' },
  { title: 'موجودی سیستم', key: 'systemQty', align: 'center' },
  { title: 'موجودی IMED', key: 'imedQty', align: 'center' },
  { title: 'وضعیت', key: 'discrepancy', align: 'center' }
]

const reconcileItems = computed(() => {
  // ساخت لیست تطبیق از داده‌های دریافتی
  const commodityMap = {}

  // اضافه کردن موجودی فیزیکی از آخرین انبارگردانی تأیید شده
  const approvedCounts = countList.value.filter(c => c.status === 'approved')
  if (approvedCounts.length > 0) {
    const latest = approvedCounts[0]
    if (latest.items) {
      latest.items.forEach(item => {
        const key = item.commodityId || item.commodityName
        if (!commodityMap[key]) {
          commodityMap[key] = {
            commodityName: item.commodityName,
            physicalQty: 0,
            systemQty: 0,
            imedQty: null
          }
        }
        commodityMap[key].physicalQty = item.physicalQty
        commodityMap[key].systemQty = item.systemQty
      })
    }
  }

  return Object.values(commodityMap).map(item => ({
    ...item,
    discrepancy: item.physicalQty !== item.systemQty || (item.imedQty !== null && item.imedQty !== item.systemQty)
  }))
})

function showSnack(message, color = 'success') {
  snackbar.value = { show: true, message, color }
}

async function loadData() {
  loading.value = true
  try {
    const [countRes, imedRes] = await Promise.all([
      axios.get('/api/storeroom/count/list'),
      axios.get('/api/storeroom/imed/stats')
    ])
    countList.value = countRes.data?.data || countRes.data || []
    imedStats.value = imedRes.data || {}
  } catch (e) {
    console.error(e)
    showSnack('خطا در بارگذاری داده‌ها', 'error')
  } finally {
    loading.value = false
  }
}

onMounted(loadData)
</script>

<style scoped>
:deep(.v-data-table-header th) { text-align: center !important; }
</style>
