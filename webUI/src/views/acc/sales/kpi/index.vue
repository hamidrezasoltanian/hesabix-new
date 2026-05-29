<template>
  <v-container fluid>
    <v-row class="mb-2" align="center">
      <v-col><h2 class="text-h6">داشبورد KPI</h2></v-col>
      <v-col cols="auto">
        <v-text-field v-model="month" label="ماه (YYYY/MM)" density="compact" style="max-width:150px" @change="load"/>
      </v-col>
      <v-col cols="auto">
        <v-btn icon @click="showTargets=true" title="تنظیم اهداف"><v-icon>mdi-target</v-icon></v-btn>
      </v-col>
    </v-row>

    <!-- Overall score -->
    <v-row class="mb-3" v-if="kpi">
      <v-col cols="12" md="4">
        <v-card color="primary" variant="flat" class="text-white">
          <v-card-text class="text-center">
            <div class="text-h2 font-weight-bold">{{ overallScore }}%</div>
            <div class="text-body-2 mt-1 opacity-80">امتیاز کلی ماه {{ kpi.month }}</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" md="8">
        <v-card>
          <v-card-text>
            <div class="text-body-2 text-medium-emphasis mb-1">{{ kpi.user?.name ?? kpi.user?.mobile }}</div>
            <v-progress-linear :model-value="overallScore" :color="scoreColor" height="20" rounded>
              <template #default>{{ overallScore }}%</template>
            </v-progress-linear>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- KPI cards -->
    <v-row v-if="kpi">
      <v-col v-for="k in kpi.kpis" :key="k.key" cols="12" sm="6" md="4" lg="3">
        <v-card :color="kpiPct(k) >= 100 ? 'success' : kpiPct(k) >= 70 ? 'warning' : 'error'" variant="tonal">
          <v-card-title class="text-subtitle-2 py-2">{{ k.label }}</v-card-title>
          <v-card-text>
            <div class="d-flex align-center mb-1">
              <span class="text-h5 font-weight-bold me-1">{{ k.actual }}</span>
              <span class="text-caption text-medium-emphasis">{{ k.unit }} / هدف: {{ k.target }}</span>
            </div>
            <v-progress-linear :model-value="Math.min(kpiPct(k), 100)"
              :color="kpiPct(k) >= 100 ? 'success' : kpiPct(k) >= 70 ? 'warning' : 'error'"
              height="10" rounded/>
            <div class="text-caption text-end mt-1">{{ kpiPct(k) }}% — وزن: {{ k.weight }}%</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <div v-if="!loading && !kpi" class="text-center pa-8 text-medium-emphasis">
      <v-icon size="48" class="mb-2">mdi-chart-bar</v-icon>
      <p>اطلاعاتی یافت نشد</p>
    </div>

    <!-- Targets dialog -->
    <v-dialog v-model="showTargets" max-width="500">
      <v-card>
        <v-card-title>تنظیم اهداف ماه {{ month }}</v-card-title>
        <v-card-text>
          <v-text-field v-model.number="targets.callDailyTarget" label="هدف تماس روزانه" type="number" class="mb-2"/>
          <v-text-field v-model.number="targets.visitWeeklyTarget" label="هدف ویزیت هفتگی" type="number" class="mb-2"/>
          <v-text-field v-model.number="targets.saleMonthlyTarget" label="هدف تعداد فروش ماهانه" type="number" class="mb-2"/>
          <v-text-field v-model="targets.saleAmountTarget" label="هدف مبلغ فروش (ریال)" class="mb-2"/>
          <v-text-field v-model.number="targets.missionMonthlyTarget" label="هدف ماموریت ماهانه" type="number" class="mb-2"/>
          <v-text-field v-model.number="targets.cashPctTarget" label="هدف درصد فروش نقدی" type="number" class="mb-2"/>
        </v-card-text>
        <v-card-actions>
          <v-spacer/>
          <v-btn @click="showTargets=false">انصراف</v-btn>
          <v-btn color="primary" @click="saveTargets">ذخیره</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useApplicationStore } from '../../../../stores/applicationStore'

const appStore = useApplicationStore()
function apiHeaders() { return { activeBid: appStore.activeBid, activeYear: appStore.activeYear, activeMoney: appStore.activeMoney } }

const month = ref('')
const kpi = ref<any>(null)
const loading = ref(false)
const showTargets = ref(false)
const targets = ref({ callDailyTarget: 10, visitWeeklyTarget: 5, saleMonthlyTarget: 2, saleAmountTarget: '0', missionMonthlyTarget: 2, cashPctTarget: 30 })

function kpiPct(k: any) {
  if (!k.target || k.target === 0) return k.actual > 0 ? 100 : 0
  return Math.round(k.actual / k.target * 100)
}

const overallScore = computed(() => {
  if (!kpi.value?.kpis) return 0
  let weighted = 0, totalWeight = 0
  for (const k of kpi.value.kpis) {
    weighted += Math.min(kpiPct(k), 100) * k.weight
    totalWeight += k.weight
  }
  return totalWeight > 0 ? Math.round(weighted / totalWeight) : 0
})

const scoreColor = computed(() => overallScore.value >= 80 ? 'success' : overallScore.value >= 60 ? 'warning' : 'error')

async function load() {
  loading.value = true
  try {
    const { data } = await axios.post('/api/acc/kpi/dashboard', { month: month.value }, { headers: apiHeaders() })
    kpi.value = data
  } finally { loading.value = false }
}

async function loadTargets() {
  const { data } = await axios.post('/api/acc/kpi/targets/get', { month: month.value }, { headers: apiHeaders() })
  targets.value = { ...targets.value, ...data }
}

async function saveTargets() {
  await axios.post('/api/acc/kpi/targets/save', { month: month.value, ...targets.value }, { headers: apiHeaders() })
  showTargets.value = false
  load()
}

onMounted(() => {
  const d = new Date()
  month.value = `${1403}/${String(1).padStart(2,'0')}` // placeholder; real app would compute jalali month
  axios.get('/api/general/get/time', { headers: apiHeaders() }).then(({ data }) => {
    if (data.timeNow) month.value = data.timeNow.substring(0, 7)
    load(); loadTargets()
  }).catch(() => { load(); loadTargets() })
})
</script>
