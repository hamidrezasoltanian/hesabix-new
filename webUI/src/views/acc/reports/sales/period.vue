<template>
  <v-container fluid>
    <v-row class="mb-3" align="center">
      <v-col><h2 class="text-h6">گزارش دوره‌ای فروش</h2></v-col>
    </v-row>

    <v-card class="mb-4">
      <v-card-text>
        <v-row align="center">
          <v-col cols="12" sm="3">
            <v-text-field v-model="form.from" label="از تاریخ (YYYY/MM/DD)" density="compact" variant="outlined" hide-details/>
          </v-col>
          <v-col cols="12" sm="3">
            <v-text-field v-model="form.to" label="تا تاریخ (YYYY/MM/DD)" density="compact" variant="outlined" hide-details/>
          </v-col>
          <v-col cols="12" sm="3">
            <v-select v-model="form.groupBy" :items="groupByOptions" label="گروه‌بندی" density="compact" variant="outlined" hide-details/>
          </v-col>
          <v-col cols="12" sm="3">
            <v-btn color="primary" :loading="loading" @click="load" block>نمایش گزارش</v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Summary chips -->
    <v-row class="mb-3" v-if="rows.length > 0">
      <v-col cols="auto">
        <v-chip color="success" variant="tonal">جمع فروش: {{ totalSell.toLocaleString('fa-IR') }} ریال</v-chip>
      </v-col>
      <v-col cols="auto">
        <v-chip color="warning" variant="tonal">جمع برگشت: {{ totalRf.toLocaleString('fa-IR') }} ریال</v-chip>
      </v-col>
      <v-col cols="auto">
        <v-chip color="primary" variant="tonal">خالص: {{ totalNet.toLocaleString('fa-IR') }} ریال</v-chip>
      </v-col>
    </v-row>

    <!-- Chart -->
    <v-card class="mb-4" v-if="rows.length > 0">
      <v-card-text>
        <apexchart type="bar" height="250" :options="chartOptions" :series="chartSeries"/>
      </v-card-text>
    </v-card>

    <!-- Table -->
    <v-card>
      <v-data-table
        :headers="headers"
        :items="rows"
        :loading="loading"
        density="compact"
        item-value="period"
      >
        <template #item.sellAmount="{ item }">{{ Number(item.sellAmount).toLocaleString('fa-IR') }}</template>
        <template #item.rfAmount="{ item }">{{ Number(item.rfAmount).toLocaleString('fa-IR') }}</template>
        <template #item.netAmount="{ item }">
          <span :class="Number(item.netAmount) < 0 ? 'text-error' : 'text-success'">
            {{ Number(item.netAmount).toLocaleString('fa-IR') }}
          </span>
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
const form = ref({ from: '', to: '', groupBy: 'month' })
const groupByOptions = [
  { title: 'ماهانه', value: 'month' },
  { title: 'سالانه', value: 'year' },
]

const headers = [
  { title: 'دوره', key: 'period' },
  { title: 'تعداد فاکتور', key: 'sellCount' },
  { title: 'مبلغ فروش', key: 'sellAmount' },
  { title: 'برگشت از فروش', key: 'rfAmount' },
  { title: 'خالص', key: 'netAmount' },
]

const totalSell = computed(() => rows.value.reduce((s, r) => s + Number(r.sellAmount), 0))
const totalRf = computed(() => rows.value.reduce((s, r) => s + Number(r.rfAmount), 0))
const totalNet = computed(() => totalSell.value - totalRf.value)

const chartOptions = computed(() => ({
  chart: { type: 'bar', fontFamily: 'Tahoma', toolbar: { show: false } },
  plotOptions: { bar: { horizontal: false, borderRadius: 4 } },
  dataLabels: { enabled: false },
  xaxis: { categories: rows.value.map(r => r.period) },
  colors: ['#4CAF50', '#F44336'],
  legend: { position: 'top' },
}))

const chartSeries = computed(() => [
  { name: 'فروش', data: rows.value.map(r => Number(r.sellAmount)) },
  { name: 'برگشت', data: rows.value.map(r => Number(r.rfAmount)) },
])

async function load() {
  loading.value = true
  try {
    const { data } = await axios.post('/api/acc/report/sales/period', form.value, )
    rows.value = Array.isArray(data) ? data : []
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
