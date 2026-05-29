<template>
  <v-toolbar color="toolbar" title="Pipeline فروش">
    <template v-slot:prepend>
      <v-btn @click="$router.back()" variant="text" icon="mdi-arrow-right" />
    </template>
    <v-spacer />
    <v-btn-toggle v-model="viewMode" mandatory density="compact" class="me-2">
      <v-btn value="kanban" icon="mdi-view-column-outline" />
      <v-btn value="list" icon="mdi-view-list-outline" />
      <v-btn value="collections" icon="mdi-cash-clock" />
    </v-btn-toggle>
    <v-btn color="primary" prepend-icon="mdi-plus" @click="openDealDialog(null)">فرصت جدید</v-btn>
  </v-toolbar>

  <v-container fluid class="pa-2">

    <!-- ════ Kanban ════ -->
    <div v-if="viewMode === 'kanban'" class="kanban-wrap">
      <div class="kanban-board">
        <div
          v-for="stage in stages"
          :key="stage.value"
          class="kanban-col"
        >
          <div class="kanban-col-header" :style="{ borderTopColor: stage.color }">
            <span class="text-body-2 font-weight-bold">{{ stage.label }}</span>
            <v-chip size="x-small" :color="stage.color" class="ms-1">
              {{ dealsByStage(stage.value).length }}
            </v-chip>
          </div>
          <div v-if="loading" class="d-flex justify-center pa-4">
            <v-progress-circular indeterminate size="24" />
          </div>
          <div v-else class="kanban-col-body">
            <div
              v-if="dealsByStage(stage.value).length === 0"
              class="text-caption text-center text-disabled pa-4"
            >خالی</div>
            <v-card
              v-for="deal in dealsByStage(stage.value)"
              :key="deal.id"
              class="kanban-card mb-2"
              variant="outlined"
              @click="openDealDialog(deal)"
            >
              <v-card-text class="pa-2">
                <div class="d-flex align-start justify-space-between">
                  <span class="text-body-2 font-weight-medium">{{ deal.title }}</span>
                  <v-btn icon="mdi-pencil" size="x-small" variant="text" @click.stop="openDealDialog(deal)" />
                </div>
                <div class="text-caption text-medium-emphasis mt-1">{{ deal.center?.name }}</div>
                <div v-if="deal.person" class="text-caption">
                  <v-icon size="12">mdi-account</v-icon> {{ deal.person.name }}
                </div>
                <div v-if="deal.amount" class="text-caption font-weight-medium mt-1">
                  {{ Number(deal.amount).toLocaleString('fa') }} ریال
                </div>
                <div v-if="deal.dueDate" class="text-caption" :class="isDuePast(deal.dueDate) ? 'text-error' : 'text-warning'">
                  <v-icon size="12">mdi-calendar-clock</v-icon> {{ deal.dueDate }}
                </div>
                <div class="d-flex mt-1 gap-1 flex-wrap">
                  <v-chip v-if="deal.preInvoiceId" size="x-small" color="blue" variant="tonal">پ.فاکتور</v-chip>
                  <v-chip v-if="deal.storeroomTicketId" size="x-small" color="orange" variant="tonal">حواله</v-chip>
                  <v-chip v-if="deal.sellDocId" size="x-small" color="green" variant="tonal">فاکتور</v-chip>
                </div>
              </v-card-text>
            </v-card>
            <!-- quick-add -->
            <v-btn
              variant="text"
              size="small"
              prepend-icon="mdi-plus"
              class="w-100 mt-1"
              @click="openDealDialogForStage(stage.value)"
            >فرصت جدید</v-btn>
          </div>
        </div>
      </div>
    </div>

    <!-- ════ List ════ -->
    <div v-if="viewMode === 'list'">
      <v-row dense class="mb-2">
        <v-col cols="12" sm="4">
          <v-select
            v-model="filterStage"
            :items="[{label:'همه', value:''}, ...stages]"
            item-title="label"
            item-value="value"
            label="مرحله"
            variant="outlined"
            density="compact"
            hide-details
            @update:model-value="loadDeals"
          />
        </v-col>
        <v-col cols="12" sm="4">
          <v-text-field
            v-model="search"
            label="جستجو"
            variant="outlined"
            density="compact"
            prepend-inner-icon="mdi-magnify"
            hide-details
            clearable
          />
        </v-col>
      </v-row>
      <v-data-table
        :items="filteredDeals"
        :headers="listHeaders"
        :loading="loading"
        density="compact"
        @click:row="(_, { item }) => openDealDialog(item)"
      >
        <template v-slot:item.stage="{ item }">
          <v-chip :color="stageColor(item.stage)" size="small" variant="tonal">
            {{ stageLabel(item.stage) }}
          </v-chip>
        </template>
        <template v-slot:item.amount="{ item }">
          {{ item.amount ? Number(item.amount).toLocaleString('fa') : '—' }}
        </template>
        <template v-slot:item.dueDate="{ item }">
          <span :class="item.dueDate && isDuePast(item.dueDate) ? 'text-error font-weight-bold' : ''">
            {{ item.dueDate || '—' }}
          </span>
        </template>
        <template v-slot:item.actions="{ item }">
          <v-btn icon="mdi-pencil" size="x-small" variant="text" @click.stop="openDealDialog(item)" />
          <v-btn icon="mdi-delete" size="x-small" variant="text" color="error" @click.stop="deleteDeal(item)" />
        </template>
      </v-data-table>
    </div>

    <!-- ════ Collections ════ -->
    <div v-if="viewMode === 'collections'">
      <v-btn color="primary" variant="tonal" prepend-icon="mdi-refresh" @click="loadCollections" class="mb-3">
        بروزرسانی
      </v-btn>
      <div v-if="collectionsLoading" class="d-flex justify-center pa-8">
        <v-progress-circular indeterminate />
      </div>
      <template v-else>
        <!-- overdue -->
        <v-card v-if="collections.overdue?.length" class="mb-3" color="error" variant="tonal">
          <v-card-title class="py-2">
            <v-icon start>mdi-alert-circle</v-icon>
            سررسید گذشته ({{ collections.overdue.length }})
            <span class="ms-2 text-body-2">مجموع: {{ sumAmount(collections.overdue).toLocaleString('fa') }}</span>
          </v-card-title>
          <v-divider />
          <v-data-table :items="collections.overdue" :headers="collectionHeaders" density="compact" hide-default-footer>
            <template v-slot:item.stage="{ item }">
              <v-chip :color="stageColor(item.stage)" size="small" variant="tonal">{{ stageLabel(item.stage) }}</v-chip>
            </template>
            <template v-slot:item.amount="{ item }">{{ item.amount ? Number(item.amount).toLocaleString('fa') : '—' }}</template>
            <template v-slot:item.actions="{ item }">
              <v-btn size="x-small" variant="text" icon="mdi-open-in-new" @click="openDealDialog(item)" />
            </template>
          </v-data-table>
        </v-card>
        <!-- today -->
        <v-card v-if="collections.today?.length" class="mb-3" color="warning" variant="tonal">
          <v-card-title class="py-2">
            <v-icon start>mdi-calendar-today</v-icon>
            سررسید امروز ({{ collections.today.length }})
            <span class="ms-2 text-body-2">مجموع: {{ sumAmount(collections.today).toLocaleString('fa') }}</span>
          </v-card-title>
          <v-divider />
          <v-data-table :items="collections.today" :headers="collectionHeaders" density="compact" hide-default-footer>
            <template v-slot:item.stage="{ item }">
              <v-chip :color="stageColor(item.stage)" size="small" variant="tonal">{{ stageLabel(item.stage) }}</v-chip>
            </template>
            <template v-slot:item.amount="{ item }">{{ item.amount ? Number(item.amount).toLocaleString('fa') : '—' }}</template>
            <template v-slot:item.actions="{ item }">
              <v-btn size="x-small" variant="text" icon="mdi-open-in-new" @click="openDealDialog(item)" />
            </template>
          </v-data-table>
        </v-card>
        <!-- upcoming -->
        <v-card v-if="collections.upcoming?.length" class="mb-3" color="info" variant="tonal">
          <v-card-title class="py-2">
            <v-icon start>mdi-calendar-clock</v-icon>
            سررسید نزدیک ({{ collections.upcoming.length }})
            <span class="ms-2 text-body-2">مجموع: {{ sumAmount(collections.upcoming).toLocaleString('fa') }}</span>
          </v-card-title>
          <v-divider />
          <v-data-table :items="collections.upcoming" :headers="collectionHeaders" density="compact" hide-default-footer>
            <template v-slot:item.stage="{ item }">
              <v-chip :color="stageColor(item.stage)" size="small" variant="tonal">{{ stageLabel(item.stage) }}</v-chip>
            </template>
            <template v-slot:item.amount="{ item }">{{ item.amount ? Number(item.amount).toLocaleString('fa') : '—' }}</template>
            <template v-slot:item.actions="{ item }">
              <v-btn size="x-small" variant="text" icon="mdi-open-in-new" @click="openDealDialog(item)" />
            </template>
          </v-data-table>
        </v-card>
        <v-alert v-if="!collections.overdue?.length && !collections.today?.length && !collections.upcoming?.length" type="success">
          هیچ مطالبه‌ای سررسید نشده است
        </v-alert>
      </template>
    </div>
  </v-container>

  <!-- ════ Deal Dialog ════ -->
  <v-dialog v-model="dealDialog" max-width="640" persistent scrollable>
    <v-card>
      <v-card-title class="pa-4 d-flex align-center">
        <span>{{ editDeal?.id ? 'ویرایش فرصت' : 'فرصت جدید' }}</span>
        <v-spacer />
        <v-chip v-if="editDeal?.id" :color="stageColor(editDeal.stage)" size="small" variant="tonal" class="me-2">
          {{ stageLabel(editDeal.stage) }}
        </v-chip>
        <v-btn icon="mdi-close" variant="text" @click="dealDialog = false" />
      </v-card-title>
      <v-divider />
      <v-card-text class="pa-4">

        <!-- اطلاعات پایه -->
        <v-row dense>
          <v-col cols="12">
            <v-text-field v-model="form.title" label="عنوان فرصت *" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-select
              v-model="form.centerId"
              :items="centers"
              item-title="name"
              item-value="id"
              label="مرکز فروش *"
              variant="outlined"
              density="compact"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-select
              v-model="form.stage"
              :items="stages"
              item-title="label"
              item-value="value"
              label="مرحله"
              variant="outlined"
              density="compact"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.amount" label="مبلغ (ریال)" variant="outlined" density="compact" type="number" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.dueDate" label="سررسید" variant="outlined" density="compact" placeholder="1403/01/15" />
          </v-col>
          <v-col cols="12">
            <v-textarea v-model="form.des" label="توضیحات" variant="outlined" density="compact" rows="2" auto-grow />
          </v-col>
        </v-row>

        <!-- پیوند به اسناد (فقط در ویرایش) -->
        <template v-if="editDeal?.id">
          <v-divider class="my-3" />
          <div class="text-body-2 font-weight-bold mb-2">پیوند به اسناد حسابداری</div>
          <v-row dense>
            <v-col cols="12" sm="4">
              <v-text-field
                v-model.number="form.preInvoiceId"
                label="شناسه پیش‌فاکتور"
                variant="outlined"
                density="compact"
                type="number"
                :append-inner-icon="form.preInvoiceId ? 'mdi-open-in-new' : undefined"
                @click:append-inner="goToPreInvoice"
              />
            </v-col>
            <v-col cols="12" sm="4">
              <v-text-field
                v-model.number="form.storeroomTicketId"
                label="شناسه حواله انبار"
                variant="outlined"
                density="compact"
                type="number"
              />
            </v-col>
            <v-col cols="12" sm="4">
              <v-text-field
                v-model.number="form.sellDocId"
                label="شناسه فاکتور"
                variant="outlined"
                density="compact"
                type="number"
              />
            </v-col>
          </v-row>

          <!-- دکمه‌های مرحله‌ای -->
          <v-divider class="my-3" />
          <div class="text-body-2 font-weight-bold mb-2">عملیات</div>
          <div class="d-flex flex-wrap gap-2">
            <v-btn
              v-if="editDeal.stage === 'pre_invoice' && !editDeal.approvedAt"
              color="success"
              variant="flat"
              size="small"
              prepend-icon="mdi-check-decagram"
              :loading="approving"
              @click="approveDeal"
            >تأیید مدیر</v-btn>
            <v-btn
              v-for="s in nextStages(editDeal.stage)"
              :key="s.value"
              :color="s.color"
              variant="tonal"
              size="small"
              :prepend-icon="s.icon"
              @click="advanceStage(s.value)"
            >{{ s.label }}</v-btn>
            <v-btn
              size="small"
              variant="tonal"
              color="blue"
              prepend-icon="mdi-file-document-plus"
              @click="createPreInvoice"
            >ایجاد پیش‌فاکتور</v-btn>
          </div>

          <!-- وضعیت تأیید -->
          <v-alert v-if="editDeal.approvedAt" type="success" variant="tonal" density="compact" class="mt-3">
            تأیید شده توسط {{ editDeal.approvedBy?.mobile }} در {{ editDeal.approvedAt }}
          </v-alert>
        </template>
      </v-card-text>
      <v-divider />
      <v-card-actions class="pa-3">
        <v-btn color="error" variant="text" v-if="editDeal?.id" @click="deleteDeal(editDeal)">حذف</v-btn>
        <v-spacer />
        <v-btn variant="text" @click="dealDialog = false">انصراف</v-btn>
        <v-btn color="primary" variant="flat" :loading="saving" @click="saveDeal">ذخیره</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import Swal from 'sweetalert2'
import { useApplicationStore } from '../../../../stores/applicationStore'

const appStore = useApplicationStore()
const router = useRouter()

interface Deal {
  id: number
  title: string
  stage: string
  amount: string | null
  dueDate: string | null
  des: string | null
  createdAt: string
  closedAt: string | null
  approvedAt: string | null
  preInvoiceId: number | null
  storeroomTicketId: number | null
  sellDocId: number | null
  center: { id: number; name: string }
  person: { id: number; name: string } | null
  owner: { id: number; mobile: string } | null
  submitter: { id: number; mobile: string } | null
  approvedBy: { id: number; mobile: string } | null
}

interface Center { id: number; name: string; person?: { id: number; name: string } | null }

const loading = ref(false)
const saving = ref(false)
const approving = ref(false)
const collectionsLoading = ref(false)

const viewMode = ref<'kanban' | 'list' | 'collections'>('kanban')
const filterStage = ref('')
const search = ref('')
const deals = ref<Deal[]>([])
const centers = ref<Center[]>([])
const collections = ref<Record<string, Deal[]>>({})

const dealDialog = ref(false)
const editDeal = ref<Deal | null>(null)
const form = ref({
  id: null as number | null,
  title: '',
  centerId: null as number | null,
  stage: 'planning',
  amount: '' as string | number,
  dueDate: '',
  des: '',
  preInvoiceId: null as number | null,
  storeroomTicketId: null as number | null,
  sellDocId: null as number | null,
})

const stages = [
  { value: 'planning',    label: 'برنامه‌ریزی',   color: 'grey',    icon: 'mdi-calendar' },
  { value: 'visited',     label: 'بازدید شد',     color: 'blue',    icon: 'mdi-account-check' },
  { value: 'pre_invoice', label: 'پیش‌فاکتور',    color: 'indigo',  icon: 'mdi-file-document' },
  { value: 'approved',    label: 'تأیید مدیر',    color: 'purple',  icon: 'mdi-check-decagram' },
  { value: 'dispatched',  label: 'حواله انبار',   color: 'orange',  icon: 'mdi-warehouse' },
  { value: 'invoiced',    label: 'فاکتور صادر',   color: 'teal',    icon: 'mdi-receipt' },
  { value: 'collected',   label: 'وصول شده',      color: 'success', icon: 'mdi-cash-check' },
]

const listHeaders = [
  { title: 'عنوان', key: 'title' },
  { title: 'مرکز', key: 'center.name' },
  { title: 'مشتری', key: 'person.name' },
  { title: 'مرحله', key: 'stage' },
  { title: 'مبلغ', key: 'amount' },
  { title: 'سررسید', key: 'dueDate' },
  { title: '', key: 'actions', sortable: false },
]

const collectionHeaders = [
  { title: 'عنوان', key: 'title' },
  { title: 'مرکز', key: 'center.name' },
  { title: 'مشتری', key: 'person.name' },
  { title: 'وضعیت', key: 'stage' },
  { title: 'مبلغ', key: 'amount' },
  { title: 'سررسید', key: 'dueDate' },
  { title: '', key: 'actions', sortable: false },
]

function apiHeaders() {
  return { activeBid: appStore.activeBid, activeYear: appStore.activeYear, activeMoney: appStore.activeMoney }
}

function dealsByStage(stage: string) {
  return deals.value.filter(d => d.stage === stage)
}

const filteredDeals = computed(() => {
  let result = deals.value
  if (filterStage.value) result = result.filter(d => d.stage === filterStage.value)
  if (search.value) {
    const q = search.value.toLowerCase()
    result = result.filter(d =>
      d.title.toLowerCase().includes(q) ||
      d.center?.name?.toLowerCase().includes(q) ||
      d.person?.name?.toLowerCase().includes(q)
    )
  }
  return result
})

function stageLabel(s: string) { return stages.find(x => x.value === s)?.label ?? s }
function stageColor(s: string) { return stages.find(x => x.value === s)?.color ?? 'default' }
function isDuePast(date: string) { return date < new Date().toISOString().slice(0, 10).replace(/-/g, '/') }
function sumAmount(arr: Deal[]) { return arr.reduce((s, d) => s + (d.amount ? Number(d.amount) : 0), 0) }

function nextStages(current: string) {
  const idx = stages.findIndex(s => s.value === current)
  return idx < stages.length - 1 ? [stages[idx + 1]] : []
}

async function loadDeals() {
  loading.value = true
  try {
    const res = await axios.post('/api/acc/salesdeal/list', { stage: filterStage.value || undefined }, { headers: apiHeaders() })
    deals.value = res.data
  } finally {
    loading.value = false
  }
}

async function loadCenters() {
  const res = await axios.post('/api/acc/salescenter/list', {}, { headers: apiHeaders() })
  centers.value = res.data
}

async function loadCollections() {
  collectionsLoading.value = true
  try {
    const res = await axios.post('/api/acc/salesdeal/collections', {}, { headers: apiHeaders() })
    collections.value = res.data
  } finally {
    collectionsLoading.value = false
  }
}

function openDealDialog(deal: Deal | null) {
  editDeal.value = deal
  if (deal) {
    form.value = {
      id: deal.id,
      title: deal.title,
      centerId: deal.center?.id ?? null,
      stage: deal.stage,
      amount: deal.amount ?? '',
      dueDate: deal.dueDate ?? '',
      des: deal.des ?? '',
      preInvoiceId: deal.preInvoiceId,
      storeroomTicketId: deal.storeroomTicketId,
      sellDocId: deal.sellDocId,
    }
  } else {
    form.value = { id: null, title: '', centerId: null, stage: 'planning', amount: '', dueDate: '', des: '', preInvoiceId: null, storeroomTicketId: null, sellDocId: null }
  }
  dealDialog.value = true
}

function openDealDialogForStage(stage: string) {
  openDealDialog(null)
  form.value.stage = stage
}

async function saveDeal() {
  if (!form.value.title || !form.value.centerId) return
  saving.value = true
  try {
    const res = await axios.post('/api/acc/salesdeal/mod', form.value, { headers: apiHeaders() })
    if (res.data.result === 1) {
      dealDialog.value = false
      await loadDeals()
    }
  } finally {
    saving.value = false
  }
}

async function advanceStage(stage: string) {
  if (!editDeal.value) return
  await axios.post(`/api/acc/salesdeal/stage/${editDeal.value.id}`, { stage }, { headers: apiHeaders() })
  dealDialog.value = false
  await loadDeals()
}

async function approveDeal() {
  if (!editDeal.value) return
  approving.value = true
  try {
    await axios.post(`/api/acc/salesdeal/approve/${editDeal.value.id}`, {}, { headers: apiHeaders() })
    dealDialog.value = false
    await loadDeals()
  } finally {
    approving.value = false
  }
}

async function deleteDeal(deal: Deal) {
  const res = await Swal.fire({
    title: 'حذف فرصت',
    text: `فرصت "${deal.title}" حذف شود؟`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'حذف',
    cancelButtonText: 'انصراف',
    confirmButtonColor: '#d32f2f',
  })
  if (!res.isConfirmed) return
  await axios.post(`/api/acc/salesdeal/del/${deal.id}`, {}, { headers: apiHeaders() })
  dealDialog.value = false
  await loadDeals()
}

function createPreInvoice() {
  if (!editDeal.value) return
  const personId = editDeal.value.person?.id
  router.push({ path: '/acc/presell/mod', query: { dealId: editDeal.value.id, ...(personId ? { personId } : {}) } })
  dealDialog.value = false
}

function goToPreInvoice() {
  if (form.value.preInvoiceId) {
    router.push({ path: '/acc/presell/mod', query: { id: form.value.preInvoiceId } })
  }
}

watch(viewMode, (v) => { if (v === 'collections') loadCollections() })

onMounted(() => {
  loadDeals()
  loadCenters()
})
</script>

<style scoped>
.kanban-wrap {
  overflow-x: auto;
  padding-bottom: 8px;
}
.kanban-board {
  display: flex;
  gap: 12px;
  min-width: max-content;
}
.kanban-col {
  width: 230px;
  flex-shrink: 0;
  background: rgba(var(--v-theme-surface), 1);
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  display: flex;
  flex-direction: column;
}
.kanban-col-header {
  padding: 8px 12px;
  border-top: 3px solid;
  border-radius: 8px 8px 0 0;
  display: flex;
  align-items: center;
}
.kanban-col-body {
  padding: 8px;
  flex: 1;
  overflow-y: auto;
  max-height: calc(100vh - 200px);
}
.kanban-card {
  cursor: pointer;
  transition: box-shadow 0.15s;
}
.kanban-card:hover {
  box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}
</style>
