<template>
  <v-container fluid>
    <v-row class="mb-3" align="center">
      <v-col><h2 class="text-h6">عملکرد فروش (به تفکیک کاربر)</h2></v-col>
    </v-row>

    <v-card class="mb-4">
      <v-card-text>
        <v-row align="center">
          <v-col cols="12" sm="4">
            <v-text-field v-model="from" label="از تاریخ (YYYY/MM/DD)" density="compact" variant="outlined" hide-details/>
          </v-col>
          <v-col cols="12" sm="4">
            <v-text-field v-model="to" label="تا تاریخ (YYYY/MM/DD)" density="compact" variant="outlined" hide-details/>
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
        <apexchart type="bar" height="250" :options="chartOptions" :series="chartSeries"/>
      </v-card-text>
    </v-card>

    <v-card>
      <v-data-table
        :headers="headers"
        :items="rows"
        :loading="loading"
        density="compact"
        item-value="userId"
      >
        <template #item.rank="{ index }">
          <v-chip :color="index === 0 ? 'amber' : index === 1 ? 'blue-grey' : 'brown-lighten-2'" size="x-small">
            {{ index + 1 }}
          </v-chip>
        </template>
        <template #item.totalAmount="{ item }">{{ Number(item.totalAmount).toLocaleString('fa-IR') }}</template>
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

const headers = [
  { title: 'رتبه', key: 'rank' },
  { title: 'نام کاربر', key: 'userName' },
  { title: 'تعداد فاکتور', key: 'count' },
  { title: 'مجموع فروش', key: 'totalAmount' },
]

const chartOptions = computed(() => ({
  chart: { type: 'bar', fontFamily: 'Tahoma', toolbar: { show: false } },
  plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
  dataLabels: { enabled: false },
  xaxis: { categories: rows.value.map(r => r.userName) },
}))

const chartSeries = computed(() => [{ name: 'فروش (ریال)', data: rows.value.map(r => Number(r.totalAmount)) }])

async function load() {
  loading.value = true
  try {
    const { data } = await axios.post('/api/acc/report/sales/performance', { from: from.value, to: to.value }, )
    rows.value = Array.isArray(data) ? data : []
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
