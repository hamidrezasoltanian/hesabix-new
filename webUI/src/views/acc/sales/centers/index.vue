<template>
  <v-toolbar color="toolbar">
    <v-toolbar-title>مراکز فروش</v-toolbar-title>
    <v-spacer />
    <v-btn-toggle v-model="viewMode" density="compact" variant="outlined" class="me-2">
      <v-btn value="list" icon="mdi-format-list-bulleted" size="small" />
      <v-btn value="kanban" icon="mdi-view-column-outline" size="small" />
    </v-btn-toggle>
    <v-btn color="primary" prepend-icon="mdi-plus" @click="openDialog(null)">مرکز جدید</v-btn>
  </v-toolbar>

  <v-container fluid class="pa-2">

    <!-- overdue banner -->
    <v-alert v-if="overdueCount > 0" type="warning" variant="tonal" density="compact" class="mb-3">
      <strong>{{ overdueCount }}</strong> مرکز دارای پیگیری سررسید‌گذشته هستند
      <v-btn size="x-small" variant="tonal" class="ms-2" @click="filterOverdue = !filterOverdue">
        {{ filterOverdue ? 'نمایش همه' : 'فیلتر overdue' }}
      </v-btn>
    </v-alert>

    <!-- filters -->
    <v-row dense class="mb-3">
      <v-col cols="12" sm="3">
        <v-text-field v-model="filters.search" label="جستجو" variant="outlined" density="compact"
          prepend-inner-icon="mdi-magnify" clearable hide-details />
      </v-col>
      <v-col cols="6" sm="2">
        <v-select v-model="filters.crmStatus" :items="[{value:'',label:'همه وضعیت‌ها'},...crmStatusOptions]"
          item-title="label" item-value="value" label="وضعیت CRM" variant="outlined" density="compact" hide-details />
      </v-col>
      <v-col cols="6" sm="2">
        <v-select v-model="filters.lead" :items="[{value:'',label:'همه لیدها'},...leadOptions]"
          item-title="label" item-value="value" label="نوع لید" variant="outlined" density="compact" hide-details />
      </v-col>
      <v-col cols="6" sm="2">
        <v-select v-model="filters.potential"
          :items="[{v:'',l:'همه پتانسیل‌ها'},{v:'1',l:'★★★★'},{v:'2',l:'★★★☆'},{v:'3',l:'★★☆☆'},{v:'4',l:'★☆☆☆'}]"
          item-title="l" item-value="v" label="پتانسیل" variant="outlined" density="compact" hide-details />
      </v-col>
      <v-col cols="6" sm="2">
        <v-btn variant="tonal" size="small" @click="loadCenters" prepend-icon="mdi-refresh" class="mt-1">بروزرسانی</v-btn>
      </v-col>
    </v-row>

    <div v-if="loading" class="d-flex justify-center pa-8"><v-progress-circular indeterminate /></div>

    <!-- ══ LIST VIEW ══ -->
    <template v-else-if="viewMode === 'list'">
      <v-card variant="outlined">
        <v-data-table
          :headers="tableHeaders"
          :items="filteredCenters"
          :items-per-page="20"
          density="compact"
          hover
          @click:row="(_, {item}) => goToCenter(item.id)"
        >
          <template v-slot:item.name="{ item }">
            <span class="font-weight-medium">{{ item.name }}</span>
            <v-chip v-if="item.overdue" size="x-small" color="error" variant="tonal" class="ms-1">overdue</v-chip>
          </template>
          <template v-slot:item.crmStatus="{ item }">
            <v-chip size="x-small" :color="crmStatusColor(item.crmStatus)" variant="tonal">{{ crmStatusLabel(item.crmStatus) }}</v-chip>
          </template>
          <template v-slot:item.lead="{ item }">
            <span v-if="item.lead" class="text-caption">{{ leadLabel(item.lead) }}</span>
          </template>
          <template v-slot:item.potential="{ item }">
            <span v-if="item.potential">{{ '★'.repeat(item.potential) + '☆'.repeat(4 - item.potential) }}</span>
          </template>
          <template v-slot:item.tags="{ item }">
            <v-chip v-for="tag in item.tags" :key="tag.id" size="x-small"
              :style="{ backgroundColor: tag.color + '22', color: tag.color }" class="me-1">{{ tag.name }}</v-chip>
          </template>
          <template v-slot:item.followupDate="{ item }">
            <span :class="item.overdue ? 'text-error font-weight-bold' : ''">{{ item.followupDate ?? '—' }}</span>
          </template>
          <template v-slot:item.actions="{ item }">
            <v-btn icon="mdi-pencil" size="x-small" variant="text" @click.stop="openDialog(item)" />
            <v-btn icon="mdi-delete" size="x-small" variant="text" color="error" @click.stop="deleteCenter(item)" />
          </template>
        </v-data-table>
      </v-card>
    </template>

    <!-- ══ KANBAN VIEW ══ -->
    <template v-else>
      <div class="d-flex gap-3 overflow-x-auto pb-3" style="min-height:70vh">
        <div v-for="col in crmStatusOptions" :key="col.value" style="min-width:240px;max-width:280px;flex-shrink:0">
          <div class="text-caption font-weight-bold mb-2 d-flex align-center gap-1">
            <v-chip size="x-small" :color="col.color" variant="tonal">{{ col.label }}</v-chip>
            <span class="text-disabled">({{ colCenters(col.value).length }})</span>
          </div>
          <v-card
            v-for="c in colCenters(col.value)"
            :key="c.id"
            variant="outlined"
            class="mb-2 cursor-pointer"
            @click="goToCenter(c.id)"
          >
            <v-card-text class="pa-2">
              <div class="d-flex align-center justify-space-between">
                <span class="text-body-2 font-weight-medium">{{ c.name }}</span>
                <v-icon v-if="c.overdue" color="error" size="14">mdi-alert-circle</v-icon>
              </div>
              <div class="d-flex flex-wrap gap-1 mt-1">
                <v-chip v-if="c.lead" size="x-small" color="blue" variant="tonal">{{ leadLabel(c.lead) }}</v-chip>
                <v-chip v-if="c.potential" size="x-small" color="amber" variant="tonal">{{ '★'.repeat(c.potential) }}</v-chip>
                <v-chip v-for="tag in c.tags" :key="tag.id" size="x-small"
                  :style="{ backgroundColor: tag.color + '22', color: tag.color }">{{ tag.name }}</v-chip>
              </div>
              <div v-if="c.followupDate" class="text-caption mt-1" :class="c.overdue ? 'text-error' : 'text-disabled'">
                <v-icon size="10">mdi-calendar</v-icon> {{ c.followupDate }}
              </div>
              <div class="d-flex align-center justify-space-between mt-1">
                <span v-if="c.owner" class="text-caption text-disabled">{{ c.owner.mobile }}</span>
                <v-btn size="x-small" variant="text" @click.stop="openDialog(c)" icon="mdi-pencil" />
              </div>
            </v-card-text>
          </v-card>
          <v-btn variant="text" size="x-small" prepend-icon="mdi-plus" color="primary" @click="openDialogWithStatus(col.value)">
            افزودن
          </v-btn>
        </div>
      </div>
    </template>
  </v-container>

  <!-- ════ Add/Edit Dialog ════ -->
  <v-dialog v-model="dialog" max-width="600" persistent>
    <v-card>
      <v-card-title class="pa-4">{{ form.id ? 'ویرایش مرکز' : 'مرکز جدید' }}</v-card-title>
      <v-divider />
      <v-card-text class="pa-4">
        <v-row dense>
          <v-col cols="12">
            <v-text-field v-model="form.name" label="نام مرکز *" variant="outlined" density="compact" autofocus />
          </v-col>
          <v-col cols="12" sm="6">
            <v-select v-model="form.crmStatus" :items="crmStatusOptions" item-title="label" item-value="value"
              label="وضعیت CRM" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-select v-model="form.lead" :items="leadOptions" item-title="label" item-value="value"
              label="نوع لید" variant="outlined" density="compact" clearable />
          </v-col>
          <v-col cols="12" sm="4">
            <v-select v-model="form.potential"
              :items="[{v:null,l:'—'},{v:1,l:'★★★★'},{v:2,l:'★★★☆'},{v:3,l:'★★☆☆'},{v:4,l:'★☆☆☆'}]"
              item-title="l" item-value="v" label="پتانسیل" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="12" sm="4">
            <v-text-field v-model="form.tel" label="تلفن" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="12" sm="4">
            <v-text-field v-model="form.followupDate" label="تاریخ پیگیری" variant="outlined" density="compact" placeholder="1403/01/20" clearable />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.province" label="استان" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.city" label="شهر" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="12">
            <v-combobox v-model="form.selectedTags" :items="availableTags" item-title="name" item-value="id"
              label="تگ‌ها" variant="outlined" density="compact" multiple chips closable-chips return-object />
          </v-col>
          <v-col cols="12">
            <v-text-field v-model="form.address" label="آدرس" variant="outlined" density="compact" />
          </v-col>
        </v-row>
      </v-card-text>
      <v-divider />
      <v-card-actions class="pa-3">
        <v-btn variant="text" @click="dialog = false">انصراف</v-btn>
        <v-spacer />
        <v-btn color="primary" variant="flat" :loading="saving" @click="save">ذخیره</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import Swal from 'sweetalert2'
import { useApplicationStore } from '../../../../stores/applicationStore'

const appStore = useApplicationStore()
const router = useRouter()

interface Tag { id: number; name: string; color: string }
interface Center { id: number; name: string; province: string | null; city: string | null; type: string | null; potential: number | null; lead: string | null; crmStatus: string; followupDate: string | null; overdue: boolean; tel: string | null; address: string | null; owner: { id: number; mobile: string } | null; tags: Tag[] }

const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const viewMode = ref('list')
const filterOverdue = ref(false)
const centers = ref<Center[]>([])
const availableTags = ref<Tag[]>([])
const filters = ref({ search: '', crmStatus: '', lead: '', potential: '' })

const emptyForm = () => ({ id: null as number | null, name: '', province: '', city: '', type: '', potential: null as number | null, lead: null as string | null, crmStatus: 'no_contact', followupDate: null as string | null, tel: '', address: '', selectedTags: [] as Tag[] })
const form = ref(emptyForm())

const crmStatusOptions = [
  { value: 'no_contact',      label: 'بدون تماس',        color: 'grey' },
  { value: 'initial_contact', label: 'تماس اولیه',       color: 'blue' },
  { value: 'meeting_done',    label: 'ملاقات انجام شد',  color: 'indigo' },
  { value: 'proposal_sent',   label: 'پیشنهاد ارسال شد', color: 'orange' },
  { value: 'contract_closed', label: 'قرارداد بسته شد',  color: 'success' },
  { value: 'inactive',        label: 'غیرفعال',          color: 'red' },
]
const leadOptions = [
  { value: 'customer',    label: 'مشتری' },
  { value: 'lead',        label: 'لید' },
  { value: 'opportunity', label: 'فرصت' },
  { value: 'clue',        label: 'سرنخ' },
  { value: 'none',        label: 'ندارد' },
  { value: 'no_usage',    label: 'بدون مصرف' },
]
const tableHeaders = [
  { title: 'نام', key: 'name', sortable: true },
  { title: 'وضعیت', key: 'crmStatus', sortable: true },
  { title: 'لید', key: 'lead' },
  { title: 'پتانسیل', key: 'potential' },
  { title: 'تگ‌ها', key: 'tags', sortable: false },
  { title: 'پیگیری', key: 'followupDate' },
  { title: 'استان', key: 'province' },
  { title: '', key: 'actions', sortable: false, align: 'end' as const },
]

function apiHeaders() {
  return { activeBid: appStore.activeBid, activeYear: appStore.activeYear, activeMoney: appStore.activeMoney }
}
function crmStatusLabel(s: string) { return crmStatusOptions.find(x => x.value === s)?.label ?? s }
function crmStatusColor(s: string) { return crmStatusOptions.find(x => x.value === s)?.color ?? 'default' }
function leadLabel(l: string) { return leadOptions.find(x => x.value === l)?.label ?? l }
function colCenters(status: string) { return filteredCenters.value.filter(c => c.crmStatus === status) }
function goToCenter(id: number) { router.push(`/acc/sales/center/${id}`) }

const overdueCount = computed(() => centers.value.filter(c => c.overdue).length)
const filteredCenters = computed(() => {
  let list = centers.value
  if (filterOverdue.value) list = list.filter(c => c.overdue)
  if (filters.value.search) list = list.filter(c => c.name.includes(filters.value.search))
  if (filters.value.crmStatus) list = list.filter(c => c.crmStatus === filters.value.crmStatus)
  if (filters.value.lead) list = list.filter(c => c.lead === filters.value.lead)
  if (filters.value.potential) list = list.filter(c => String(c.potential) === filters.value.potential)
  return list
})

async function loadCenters() {
  loading.value = true
  try {
    const [centersRes, tagsRes] = await Promise.all([
      axios.post('/api/acc/salescenter/list', {}, { headers: apiHeaders() }),
      axios.get('/api/acc/salescentertag/list', { headers: apiHeaders() }),
    ])
    centers.value = centersRes.data
    availableTags.value = tagsRes.data
  } finally { loading.value = false }
}

function openDialog(center: Center | null) {
  form.value = center
    ? { id: center.id, name: center.name, province: center.province ?? '', city: center.city ?? '', type: center.type ?? '', potential: center.potential, lead: center.lead, crmStatus: center.crmStatus, followupDate: center.followupDate, tel: center.tel ?? '', address: center.address ?? '', selectedTags: center.tags ?? [] }
    : emptyForm()
  dialog.value = true
}

function openDialogWithStatus(status: string) {
  form.value = { ...emptyForm(), crmStatus: status }
  dialog.value = true
}

async function save() {
  if (!form.value.name) return
  saving.value = true
  try {
    const tagIds = form.value.selectedTags.map((t: any) => typeof t === 'object' ? t.id : t)
    await axios.post('/api/acc/salescenter/mod', { ...form.value, tagIds }, { headers: apiHeaders() })
    dialog.value = false
    await loadCenters()
  } finally { saving.value = false }
}

async function deleteCenter(c: Center) {
  const res = await Swal.fire({ title: `حذف ${c.name}?`, icon: 'warning', showCancelButton: true, confirmButtonText: 'حذف', cancelButtonText: 'انصراف', confirmButtonColor: '#d32f2f' })
  if (!res.isConfirmed) return
  await axios.post(`/api/acc/salescenter/del/${c.id}`, {}, { headers: apiHeaders() })
  await loadCenters()
}

onMounted(loadCenters)
</script>
