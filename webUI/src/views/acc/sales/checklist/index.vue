<template>
  <v-container fluid>
    <v-row class="mb-2" align="center">
      <v-col><h2 class="text-h6">چک‌لیست روزانه</h2></v-col>
      <v-col cols="auto">
        <v-text-field v-model="date" label="تاریخ" density="compact" style="max-width:150px" @change="load"/>
      </v-col>
      <v-col cols="auto">
        <v-switch v-model="isManager" label="نمایش موارد مدیریتی" density="compact" hide-details @update:modelValue="load"/>
      </v-col>
    </v-row>

    <v-row class="mb-3">
      <v-col cols="12" sm="6" md="4">
        <v-card color="primary" variant="flat" class="text-white">
          <v-card-text class="text-center">
            <div class="text-h3 font-weight-bold">{{ checklist.pct ?? 0 }}%</div>
            <div class="text-body-2 mt-1 opacity-80">{{ checklist.score ?? 0 }} از {{ checklist.maxScore ?? 0 }} امتیاز</div>
            <v-progress-linear :model-value="checklist.pct ?? 0" color="white" bg-color="rgba(255,255,255,0.3)" class="mt-2" height="8" rounded/>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <div v-if="!loading && (checklist.items?.length ?? 0) === 0" class="text-center pa-8">
      <v-icon size="64" color="grey-lighten-2" class="mb-3">mdi-clipboard-check-outline</v-icon>
      <p class="text-medium-emphasis mb-4">هنوز آیتمی برای این کسب‌وکار تعریف نشده.</p>
      <v-btn color="primary" @click="seed">ایجاد آیتم‌های پیش‌فرض (۲۷ آیتم)</v-btn>
    </div>

    <v-row v-else>
      <v-col v-for="cat in categories" :key="cat" cols="12" md="6">
        <v-card class="mb-3" elevation="1">
          <v-card-title class="text-subtitle-1 bg-grey-lighten-4 py-2 px-3">{{ cat }}</v-card-title>
          <v-list density="compact" lines="one">
            <v-list-item v-for="item in itemsByCategory(cat)" :key="item.id"
              :class="item.done ? 'bg-green-lighten-5' : ''" class="px-3">
              <template #prepend>
                <v-checkbox-btn :model-value="item.done" color="success" :loading="toggling === item.id" @update:modelValue="toggle(item)"/>
              </template>
              <v-list-item-title :class="item.done ? 'text-decoration-line-through text-medium-emphasis' : ''">
                {{ item.name }}
                <v-chip v-if="item.managerOnly" size="x-small" color="purple" variant="tonal" class="ms-1">مدیر</v-chip>
              </v-list-item-title>
              <template #append>
                <v-chip size="x-small" color="grey" variant="tonal">{{ item.score }} امتیاز</v-chip>
              </template>
            </v-list-item>
          </v-list>
        </v-card>
      </v-col>
    </v-row>

    <v-card class="mt-4" v-if="history.length > 0">
      <v-card-title class="text-subtitle-1">تاریخچه (۳۰ روز اخیر)</v-card-title>
      <v-card-text>
        <v-table density="compact">
          <thead><tr><th>تاریخ</th><th>انجام شده</th><th>کل</th><th>درصد</th></tr></thead>
          <tbody>
            <tr v-for="h in history" :key="h.date">
              <td>{{ h.date }}</td>
              <td>{{ h.done }}</td>
              <td>{{ h.total }}</td>
              <td style="min-width:120px">
                <v-progress-linear
                  :model-value="h.total > 0 ? Math.round(Number(h.done) / Number(h.total) * 100) : 0"
                  :color="h.total > 0 && Number(h.done)/Number(h.total) >= 0.8 ? 'success' : 'warning'"
                  height="16" rounded>
                  <template #default>{{ h.total > 0 ? Math.round(Number(h.done)/Number(h.total)*100) : 0 }}%</template>
                </v-progress-linear>
              </td>
            </tr>
          </tbody>
        </v-table>
      </v-card-text>
    </v-card>
  </v-container>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useApplicationStore } from '../../../../stores/applicationStore'

const appStore = useApplicationStore()
function apiHeaders() { return { activeBid: appStore.activeBid, activeYear: appStore.activeYear, activeMoney: appStore.activeMoney } }

const date = ref('')
const checklist = ref<any>({ items: [], score: 0, maxScore: 0, pct: 0 })
const history = ref<any[]>([])
const loading = ref(false)
const isManager = ref(false)
const toggling = ref<number | null>(null)

const categories = computed(() => {
  const cats: string[] = []
  for (const item of checklist.value.items ?? []) {
    if (!cats.includes(item.category)) cats.push(item.category)
  }
  return cats
})
function itemsByCategory(cat: string) { return (checklist.value.items ?? []).filter((i: any) => i.category === cat) }

async function load() {
  loading.value = true
  try {
    const { data } = await axios.post('/api/acc/crm/checklist/today', { date: date.value, isManager: isManager.value }, { headers: apiHeaders() })
    checklist.value = data
  } finally { loading.value = false }
}

async function toggle(item: any) {
  toggling.value = item.id
  await axios.post('/api/acc/crm/checklist/toggle', { itemId: item.id, date: date.value }, { headers: apiHeaders() })
  toggling.value = null
  load()
}

async function seed() {
  await axios.post('/api/acc/crm/checklist/seed', {}, { headers: apiHeaders() })
  load()
}

async function loadHistory() {
  const { data } = await axios.post('/api/acc/crm/checklist/history', {}, { headers: apiHeaders() })
  history.value = Array.isArray(data) ? data : []
}

onMounted(() => {
  axios.get('/api/general/get/time', { headers: apiHeaders() }).then(({ data }) => {
    date.value = data.timeNow ?? ''
    load()
    loadHistory()
  }).catch(() => { load(); loadHistory() })
})
</script>
