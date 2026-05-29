<template>
  <v-container fluid>
    <v-row class="mb-2" align="center">
      <v-col><h2 class="text-h6">مرور مدیریتی تیم</h2></v-col>
      <v-col cols="auto">
        <v-text-field v-model="month" label="ماه (YYYY/MM)" density="compact" style="max-width:150px" @change="load"/>
      </v-col>
    </v-row>

    <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-3"/>

    <v-row v-if="teamKpi.length">
      <v-col v-for="member in teamKpi" :key="member.user?.id" cols="12" md="6" lg="4">
        <v-card class="mb-3" elevation="2">
          <v-card-title class="text-subtitle-1 d-flex align-center">
            <v-avatar color="primary" size="32" class="me-2">
              <span class="text-caption text-white">{{ initials(member.user) }}</span>
            </v-avatar>
            {{ member.user?.name ?? member.user?.mobile }}
            <v-spacer/>
            <v-chip :color="scoreColor(memberScore(member))" size="small">{{ memberScore(member) }}%</v-chip>
          </v-card-title>
          <v-card-text>
            <v-progress-linear :model-value="memberScore(member)" :color="scoreColor(memberScore(member))" height="8" rounded class="mb-3"/>
            <v-row dense>
              <v-col v-for="k in member.kpis" :key="k.key" cols="6">
                <div class="d-flex align-center">
                  <v-icon size="14" :color="kpiColor(k)" class="me-1">{{ kpiIcon(k.key) }}</v-icon>
                  <span class="text-caption">{{ k.label }}: </span>
                  <span class="text-caption font-weight-bold ms-1">{{ k.actual }}/{{ k.target }}</span>
                </div>
              </v-col>
            </v-row>
          </v-card-text>
          <v-card-actions>
            <v-btn variant="text" size="small" color="primary" :to="`/acc/sales/kpi?user=${member.user?.id}`">جزئیات</v-btn>
          </v-card-actions>
        </v-card>
      </v-col>
    </v-row>

    <div v-if="!loading && teamKpi.length === 0" class="text-center pa-8 text-medium-emphasis">
      <v-icon size="48" class="mb-2">mdi-account-group</v-icon>
      <p>هیچ کارشناسی یافت نشد</p>
    </div>

    <!-- Team summary table -->
    <v-card v-if="teamKpi.length" class="mt-4">
      <v-card-title class="text-subtitle-1">جدول مقایسه‌ای تیم</v-card-title>
      <v-table density="compact">
        <thead>
          <tr>
            <th>کارشناس</th>
            <th v-for="k in kpiLabels" :key="k.key">{{ k.label }}</th>
            <th>امتیاز کل</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="member in teamKpi" :key="member.user?.id">
            <td>{{ member.user?.name ?? member.user?.mobile }}</td>
            <td v-for="k in member.kpis" :key="k.key">
              <span :class="kpiPct(k) >= 100 ? 'text-success' : kpiPct(k) >= 70 ? 'text-warning' : 'text-error'">
                {{ k.actual }}<span class="text-caption text-medium-emphasis">/{{ k.target }}</span>
              </span>
            </td>
            <td><v-chip :color="scoreColor(memberScore(member))" size="x-small">{{ memberScore(member) }}%</v-chip></td>
          </tr>
        </tbody>
      </v-table>
    </v-card>
  </v-container>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useApplicationStore } from '../../../../stores/applicationStore'

const appStore = useApplicationStore()
function apiHeaders() { return { activeBid: appStore.activeBid, activeYear: appStore.activeYear, activeMoney: appStore.activeMoney } }

const month = ref('')
const teamKpi = ref<any[]>([])
const loading = ref(false)

const kpiLabels = computed(() => teamKpi.value[0]?.kpis ?? [])

function initials(user: any) {
  const n = user?.name ?? user?.mobile ?? '?'
  return n.substring(0, 2)
}

function kpiPct(k: any) {
  if (!k.target || k.target === 0) return k.actual > 0 ? 100 : 0
  return Math.round(k.actual / k.target * 100)
}

function memberScore(member: any) {
  if (!member?.kpis) return 0
  let weighted = 0, totalWeight = 0
  for (const k of member.kpis) {
    weighted += Math.min(kpiPct(k), 100) * k.weight
    totalWeight += k.weight
  }
  return totalWeight > 0 ? Math.round(weighted / totalWeight) : 0
}

function scoreColor(score: number) { return score >= 80 ? 'success' : score >= 60 ? 'warning' : 'error' }

function kpiColor(k: any) {
  const pct = kpiPct(k)
  return pct >= 100 ? 'success' : pct >= 70 ? 'warning' : 'error'
}

function kpiIcon(key: string) {
  const icons: Record<string, string> = { call: 'mdi-phone', visit: 'mdi-map-marker', sale: 'mdi-handshake', saleAmount: 'mdi-currency-usd', mission: 'mdi-briefcase', cashPct: 'mdi-cash', conversion: 'mdi-trending-up' }
  return icons[key] ?? 'mdi-chart-bar'
}

async function load() {
  loading.value = true
  try {
    const { data } = await axios.post('/api/acc/kpi/team', { month: month.value }, { headers: apiHeaders() })
    teamKpi.value = Array.isArray(data) ? data : []
  } finally { loading.value = false }
}

onMounted(() => {
  axios.get('/api/general/get/time', { headers: apiHeaders() }).then(({ data }) => {
    if (data.timeNow) month.value = data.timeNow.substring(0, 7)
    load()
  }).catch(() => load())
})
</script>
