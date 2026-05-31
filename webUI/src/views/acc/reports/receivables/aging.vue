<template>
  <v-container fluid>
    <v-row class="mb-3" align="center">
      <v-col><h2 class="text-h6">گزارش سنی مطالبات (Aging)</h2></v-col>
    </v-row>

    <v-card class="mb-4">
      <v-card-text>
        <v-btn color="primary" :loading="loading" @click="load">بارگذاری گزارش</v-btn>
      </v-card-text>
    </v-card>

    <!-- Bucket summary -->
    <v-row class="mb-3" v-if="rows.length > 0">
      <v-col v-for="b in bucketSummary" :key="b.bucket" cols="12" sm="3">
        <v-card variant="tonal" :color="b.color">
          <v-card-text class="text-center">
            <div class="text-caption">{{ b.bucket }}</div>
            <div class="text-h6">{{ b.count }} مشتری</div>
            <div class="text-body-2">{{ Number(b.total).toLocaleString('fa-IR') }} ریال</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Chart -->
    <v-card class="mb-4" v-if="rows.length > 0">
      <v-card-text>
        <apexchart type="donut" height="250" :options="chartOptions" :series="chartSeries"/>
      </v-card-text>
    </v-card>

    <v-card>
      <v-card-title class="text-subtitle-1 d-flex align-center gap-2">
        جزئیات مطالبات
        <v-spacer/>
        <v-text-field v-model="search" density="compact" variant="outlined" placeholder="جستجو..." prepend-inner-icon="mdi-magnify" hide-details style="max-width:200px"/>
      </v-card-title>
      <v-data-table
        :headers="headers"
        :items="filteredRows"
        :loading="loading"
        density="compact"
        item-value="personId"
      >
        <template #item.balance="{ item }">{{ Number(item.balance).toLocaleString('fa-IR') }}</template>
        <template #item.bucket="{ item }">
          <v-chip :color="bucketColor(item.bucket)" size="x-small">{{ item.bucket }}</v-chip>
        </template>
      </v-data-table>
    </v-card>
  </v-container>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'


const rows = ref<any[]>([])
const loading = ref(false)
const search = ref('')

const headers = [
  { title: 'مشتری', key: 'personName' },
  { title: 'مانده (ریال)', key: 'balance' },
  { title: 'سن (روز)', key: 'ageDays' },
  { title: 'دسته', key: 'bucket' },
]

const filteredRows = computed(() =>
  rows.value.filter(r => !search.value || r.personName?.includes(search.value))
)

const bucketSummary = computed(() => {
  const map: Record<string, { bucket: string; count: number; total: number; color: string }> = {}
  const colors: Record<string, string> = {
    '0-30 روز': 'success',
    '31-60 روز': 'warning',
    '61-90 روز': 'orange',
    'بیش از 90 روز': 'error',
  }
  for (const r of rows.value) {
    if (!map[r.bucket]) map[r.bucket] = { bucket: r.bucket, count: 0, total: 0, color: colors[r.bucket] ?? 'info' }
    map[r.bucket].count++
    map[r.bucket].total += Number(r.balance)
  }
  return Object.values(map)
})

function bucketColor(bucket: string) {
  const map: Record<string, string> = {
    '0-30 روز': 'success',
    '31-60 روز': 'warning',
    '61-90 روز': 'orange',
    'بیش از 90 روز': 'error',
  }
  return map[bucket] ?? 'info'
}

const chartOptions = computed(() => ({
  chart: { fontFamily: 'Tahoma' },
  labels: bucketSummary.value.map(b => b.bucket),
  colors: ['#4CAF50', '#FF9800', '#F44336', '#9C27B0'],
  legend: { position: 'bottom' },
}))

const chartSeries = computed(() => bucketSummary.value.map(b => b.total))

async function load() {
  loading.value = true
  try {
    const { data } = await axios.post('/api/acc/report/receivables/aging', {}, )
    rows.value = Array.isArray(data) ? data : []
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
