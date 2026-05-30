<template>
  <v-toolbar color="toolbar" title="هشدارهای انبار">
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
    <!-- کم موجودی -->
    <v-card class="mb-4" rounded="lg">
      <v-card-title class="pa-4">
        <v-icon color="orange" class="me-2">mdi-alert-circle</v-icon>
        کم‌موجودی
        <v-chip size="small" color="orange" class="ms-2">{{ lowStockItems.length }}</v-chip>
      </v-card-title>

      <template v-if="lowStockItems.length === 0 && !loading">
        <v-card-text class="text-center text-grey pa-6">
          <v-icon size="48" color="success" class="mb-2">mdi-check-circle</v-icon>
          <div>هشداری وجود ندارد</div>
        </v-card-text>
      </template>

      <v-data-table
        v-else
        :headers="lowStockHeaders"
        :items="lowStockItems"
        :loading="loading"
        density="compact"
        class="elevation-0 text-center"
      >
        <template v-slot:item.currentStock="{ item }">
          <span :class="item.currentStock < 0 ? 'text-error font-weight-bold' : 'text-orange'">
            {{ item.currentStock }}
          </span>
        </template>
        <template v-slot:item.deficit="{ item }">
          <span class="text-error">{{ item.deficit }}</span>
        </template>
      </v-data-table>
    </v-card>

    <!-- نزدیک انقضا -->
    <v-card rounded="lg">
      <v-card-title class="pa-4">
        <v-icon color="red" class="me-2">mdi-calendar-alert</v-icon>
        نزدیک انقضا
        <v-chip size="small" color="red" class="ms-2">{{ nearExpiryItems.length }}</v-chip>
      </v-card-title>

      <template v-if="nearExpiryItems.length === 0 && !loading">
        <v-card-text class="text-center text-grey pa-6">
          <v-icon size="48" color="success" class="mb-2">mdi-check-circle</v-icon>
          <div>هشداری وجود ندارد</div>
        </v-card-text>
      </template>

      <v-data-table
        v-else
        :headers="nearExpiryHeaders"
        :items="nearExpiryItems"
        :loading="loading"
        density="compact"
        class="elevation-0 text-center"
      >
        <template v-slot:item.daysLeft="{ item }">
          <span :class="expiryClass(item.daysLeft)">{{ item.daysLeft }} روز</span>
        </template>
        <template v-slot:item.qty="{ item }">
          {{ item.qty }}
        </template>
      </v-data-table>
    </v-card>
  </v-container>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const loading = ref(false)
const alertsData = ref({ lowStock: [], nearExpiry: [] })
let refreshInterval = null

const lowStockHeaders = [
  { title: 'نام کالا', key: 'commodityName', align: 'center' },
  { title: 'موجودی جاری', key: 'currentStock', align: 'center' },
  { title: 'حداقل موجودی', key: 'orderPoint', align: 'center' },
  { title: 'کسری', key: 'deficit', align: 'center' }
]

const nearExpiryHeaders = [
  { title: 'کالا', key: 'commodityName', align: 'center' },
  { title: 'شماره لات', key: 'lotNo', align: 'center' },
  { title: 'تاریخ انقضا', key: 'expiry', align: 'center' },
  { title: 'روز باقیمانده', key: 'daysLeft', align: 'center' },
  { title: 'موجودی', key: 'qty', align: 'center' }
]

const lowStockItems = computed(() => alertsData.value.lowStock || [])
const nearExpiryItems = computed(() => alertsData.value.nearExpiry || [])

function expiryClass(daysLeft) {
  if (daysLeft < 7) return 'text-error font-weight-bold'
  if (daysLeft < 30) return 'text-orange font-weight-medium'
  if (daysLeft < 90) return 'text-yellow-darken-3'
  return ''
}

async function loadData() {
  loading.value = true
  try {
    const res = await axios.get('/api/storeroom/alerts/list')
    alertsData.value = res.data || { lowStock: [], nearExpiry: [] }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadData()
  refreshInterval = setInterval(loadData, 60000)
})

onUnmounted(() => {
  if (refreshInterval) clearInterval(refreshInterval)
})
</script>

<style scoped>
:deep(.v-data-table-header th) { text-align: center !important; }
</style>
