<template>
  <v-container fluid>
    <v-row class="mb-2" align="center">
      <v-col><h2 class="text-h6">مراکز بر اساس استان</h2></v-col>
    </v-row>

    <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-3"/>

    <v-row>
      <v-col v-for="prov in provinces" :key="prov.name" cols="12" sm="6" md="4" lg="3">
        <v-card @click="selectedProvince = (selectedProvince === prov.name ? null : prov.name)" :color="selectedProvince === prov.name ? 'primary' : ''" :variant="selectedProvince === prov.name ? 'flat' : 'elevated'" style="cursor:pointer">
          <v-card-title class="text-subtitle-1 d-flex align-center" :class="selectedProvince === prov.name ? 'text-white' : ''">
            <v-icon class="me-2" :color="selectedProvince === prov.name ? 'white' : 'primary'">mdi-map-marker</v-icon>
            {{ prov.name }}
            <v-spacer/>
            <v-chip :color="selectedProvince === prov.name ? 'white' : 'primary'" :text-color="selectedProvince === prov.name ? 'primary' : 'white'" size="small">{{ prov.count }}</v-chip>
          </v-card-title>
          <v-card-text v-if="selectedProvince !== prov.name">
            <v-row dense>
              <v-col v-for="st in Object.keys(prov.statuses)" :key="st" cols="auto">
                <v-chip size="x-small" :color="statusColor(st)" variant="tonal">{{ statusLabel(st) }}: {{ prov.statuses[st] }}</v-chip>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Detail panel -->
    <v-card v-if="selectedProvince" class="mt-4">
      <v-card-title class="text-subtitle-1">مراکز استان {{ selectedProvince }}</v-card-title>
      <v-table density="compact">
        <thead><tr><th>نام</th><th>شهر</th><th>نوع</th><th>وضعیت CRM</th><th>پتانسیل</th><th>پیگیری</th></tr></thead>
        <tbody>
          <tr v-for="c in provinceCenters" :key="c.id" @click="$router.push(`/acc/sales/center/${c.id}`)" style="cursor:pointer">
            <td>{{ c.name }}</td>
            <td>{{ c.city }}</td>
            <td>{{ c.type }}</td>
            <td><v-chip size="x-small" :color="statusColor(c.crmStatus)" variant="tonal">{{ statusLabel(c.crmStatus) }}</v-chip></td>
            <td><v-icon v-for="i in c.potential" :key="i" size="12" color="amber">mdi-star</v-icon></td>
            <td><span :class="c.overdue ? 'text-error' : ''">{{ c.followupDate }}</span></td>
          </tr>
        </tbody>
      </v-table>
    </v-card>
  </v-container>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useRouter } from 'vue-router'
import { useApplicationStore } from '../../../../stores/applicationStore'

const appStore = useApplicationStore()
const router = useRouter()
function apiHeaders() { return { activeBid: appStore.activeBid, activeYear: appStore.activeYear, activeMoney: appStore.activeMoney } }

const centers = ref<any[]>([])
const loading = ref(false)
const selectedProvince = ref<string | null>(null)

const statusLabels: Record<string, string> = { no_contact: 'بدون تماس', initial_contact: 'تماس اولیه', meeting_done: 'جلسه داشته', proposal_sent: 'پیشنهاد ارسال', contract_closed: 'قرارداد', inactive: 'غیرفعال' }
const statusColors: Record<string, string> = { no_contact: 'grey', initial_contact: 'blue', meeting_done: 'purple', proposal_sent: 'orange', contract_closed: 'success', inactive: 'error' }

function statusLabel(s: string) { return statusLabels[s] ?? s }
function statusColor(s: string) { return statusColors[s] ?? 'grey' }

const provinces = computed(() => {
  const map = new Map<string, { count: number; statuses: Record<string, number> }>()
  for (const c of centers.value) {
    const p = c.province || 'نامشخص'
    if (!map.has(p)) map.set(p, { count: 0, statuses: {} })
    const entry = map.get(p)!
    entry.count++
    const st = c.crmStatus ?? 'no_contact'
    entry.statuses[st] = (entry.statuses[st] ?? 0) + 1
  }
  return [...map.entries()].map(([name, data]) => ({ name, ...data })).sort((a, b) => b.count - a.count)
})

const provinceCenters = computed(() => {
  if (!selectedProvince.value) return []
  return centers.value.filter(c => (c.province || 'نامشخص') === selectedProvince.value)
})

async function load() {
  loading.value = true
  try {
    const { data } = await axios.post('/api/acc/salescenter/list', {}, { headers: apiHeaders() })
    centers.value = Array.isArray(data) ? data : []
  } finally { loading.value = false }
}

onMounted(load)
</script>
