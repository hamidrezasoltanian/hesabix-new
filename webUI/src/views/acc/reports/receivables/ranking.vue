<template>
  <v-container fluid>
    <v-row class="mb-3" align="center">
      <v-col><h2 class="text-h6">رتبه‌بندی مطالبات و فروش</h2></v-col>
    </v-row>

    <v-card class="mb-4">
      <v-card-text>
        <v-row align="center">
          <v-col cols="12" sm="3">
            <v-text-field v-model="from" label="از تاریخ (YYYY/MM/DD)" density="compact" variant="outlined" hide-details/>
          </v-col>
          <v-col cols="12" sm="3">
            <v-text-field v-model="to" label="تا تاریخ (YYYY/MM/DD)" density="compact" variant="outlined" hide-details/>
          </v-col>
          <v-col cols="12" sm="2">
            <v-text-field v-model.number="limit" label="تعداد نفر" type="number" density="compact" variant="outlined" hide-details min="5" max="100"/>
          </v-col>
          <v-col cols="12" sm="4">
            <v-btn color="primary" :loading="loading" @click="load" block>نمایش گزارش</v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Chart -->
    <v-card class="mb-4" v-if="rows.length > 0">
      <v-card-text>
        <apexchart type="bar" height="280" :options="chartOptions" :series="chartSeries"/>
      </v-card-text>
    </v-card>

    <v-card>
      <v-data-table
        :headers="headers"
        :items="rows"
        :loading="loading"
        density="compact"
        item-value="personId"
      >
        <template #item.rank="{ index }">
          <v-icon v-if="index === 0" color="amber">mdi-trophy</v-icon>
          <span v-else>{{ index + 1 }}</span>
        </template>
        <template #item.totalSell="{ item }">{{ Number(item.totalSell).toLocaleString('fa-IR') }}</template>
      </v-data-table>
    </v-card>
  </v-container>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'


const rows = ref<any[]>([])
const loading = ref(false)
const from = ref('')
const to = ref('')
const limit = ref(20)

const headers = [
  { title: 'رتبه', key: 'rank', width: '70px' },
  { title: 'مشتری', key: 'personName' },
  { title: 'موبایل', key: 'personMobile' },
  { title: 'تعداد فاکتور', key: 'docCount' },
  { title: 'مجموع خرید (ریال)', key: 'totalSell' },
]

const chartOptions = computed(() => ({
  chart: { type: 'bar', fontFamily: 'Tahoma', toolbar: { show: false } },
  plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
  dataLabels: { enabled: false },
  xaxis: { categories: rows.value.map(r => r.personName) },
}))

const chartSeries = computed(() => [{ name: 'مجموع خرید', data: rows.value.map(r => Number(r.totalSell)) }])

async function load() {
  loading.value = true
  try {
    const { data } = await axios.post('/api/acc/report/receivables/ranking', { from: from.value, to: to.value, limit: limit.value }, )
    rows.value = Array.isArray(data) ? data : []
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
