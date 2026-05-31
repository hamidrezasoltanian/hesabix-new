<template>
  <v-container fluid>
    <v-row class="mb-3" align="center">
      <v-col><h2 class="text-h6">مسئول استان و مشتری</h2></v-col>
    </v-row>

    <v-row>
      <!-- Province managers -->
      <v-col cols="12" md="5">
        <v-card>
          <v-card-title class="text-subtitle-1">مسئول هر استان</v-card-title>
          <v-card-text>
            <v-row v-for="prov in provinces" :key="prov" align="center" class="mb-1">
              <v-col cols="5" class="text-body-2">{{ prov }}</v-col>
              <v-col cols="7">
                <v-select
                  :model-value="getProvinceUser(prov)"
                  @update:model-value="(v) => saveProvinceManager(prov, v)"
                  :items="users"
                  item-title="label"
                  item-value="id"
                  density="compact"
                  variant="outlined"
                  clearable
                  placeholder="انتخاب مسئول"
                  hide-details
                />
              </v-col>
            </v-row>
            <v-alert v-if="provinces.length === 0" type="info" variant="tonal" class="mt-2">
              هنوز استانی در اطلاعات مشتریان ثبت نشده است
            </v-alert>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Person responsible -->
      <v-col cols="12" md="7">
        <v-card>
          <v-card-title class="text-subtitle-1 d-flex align-center gap-2">
            مسئول هر مشتری
            <v-spacer/>
            <v-text-field v-model="search" density="compact" variant="outlined" placeholder="جستجو..." prepend-inner-icon="mdi-magnify" hide-details style="max-width:200px"/>
          </v-card-title>
          <v-card-text class="pa-0">
            <v-data-table
              :headers="headers"
              :items="filteredPersons"
              :loading="loadingPersons"
              density="compact"
              item-value="id"
              height="450"
            >
              <template #item.responsible="{ item }">
                <v-select
                  :model-value="item.userId || null"
                  @update:model-value="(v) => savePersonResponsible(item.id, v)"
                  :items="[{id: null, label: 'بدون مسئول'}, ...users]"
                  item-title="label"
                  item-value="id"
                  density="compact"
                  variant="plain"
                  hide-details
                  style="min-width:150px"
                />
              </template>
            </v-data-table>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const provinces = ref<string[]>([])
const provinceManagers = ref<any[]>([])
const persons = ref<any[]>([])
const users = ref<any[]>([])
const search = ref('')
const loadingPersons = ref(false)

const headers = [
  { title: 'نام', key: 'nikename' },
  { title: 'استان', key: 'ostan' },
  { title: 'مسئول', key: 'responsible', sortable: false },
]

const filteredPersons = computed(() =>
  persons.value.filter(p =>
    !search.value || p.nikename?.includes(search.value) || p.ostan?.includes(search.value)
  )
)

function getProvinceUser(province: string) {
  return provinceManagers.value.find(m => m.province === province)?.user?.id ?? null
}

async function saveProvinceManager(province: string, userId: number | null) {
  await axios.post('/api/acc/province-manager/save', { province, userId }, )
  await loadProvinceManagers()
}

async function savePersonResponsible(personId: number, userId: number | null) {
  await axios.post('/api/acc/person/set-responsible', { personId, userId }, )
  const p = persons.value.find(x => x.id === personId)
  if (p) {
    const u = users.value.find(x => x.id === userId)
    p.userId = userId || null
    p.userName = u?.label ?? null
  }
}

async function loadProvinceManagers() {
  const { data } = await axios.get('/api/acc/province-manager/list', )
  provinceManagers.value = Array.isArray(data) ? data : []
}

onMounted(async () => {
  // Load province list from persons
  const prov = await axios.get('/api/acc/province-manager/provinces', )
  provinces.value = Array.isArray(prov.data) ? prov.data : []

  await loadProvinceManagers()

  // Load users in business
  const crm = await axios.get('/api/acc/crm/users', )
  users.value = (Array.isArray(crm.data) ? crm.data : []).map((u: any) => ({
    id: u.id,
    label: u.name ?? u.mobile,
  }))

  // Load persons with their responsible
  loadingPersons.value = true
  const pr = await axios.post('/api/acc/person/responsible-list', {}, )
  persons.value = Array.isArray(pr.data) ? pr.data : []
  loadingPersons.value = false
})
</script>
