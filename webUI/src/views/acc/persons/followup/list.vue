<template>
  <v-toolbar color="toolbar" title="مطالبات و پیگیری">
    <template v-slot:prepend>
      <v-btn @click="$router.back()" class="d-none d-sm-flex" variant="text" icon="mdi-arrow-right" />
    </template>
    <v-spacer />
    <v-btn color="primary" prepend-icon="mdi-plus" @click="openDialog(null)">پیگیری جدید</v-btn>
  </v-toolbar>

  <v-container fluid>
    <!-- فیلترها -->
    <v-card class="mb-4" variant="outlined">
      <v-card-text>
        <v-row dense>
          <v-col cols="12" md="3">
            <v-select
              v-model="filterStatus"
              :items="statusOptions"
              item-title="label"
              item-value="value"
              label="وضعیت"
              variant="outlined"
              density="compact"
              clearable
              @update:model-value="loadFollowups"
            />
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filterType"
              :items="typeOptions"
              item-title="label"
              item-value="value"
              label="نوع فعالیت"
              variant="outlined"
              density="compact"
              clearable
            />
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field
              v-model="search"
              label="جستجو"
              variant="outlined"
              density="compact"
              prepend-inner-icon="mdi-magnify"
              clearable
            />
          </v-col>
          <v-col cols="12" md="2" class="d-flex align-center">
            <v-btn color="secondary" variant="outlined" prepend-icon="mdi-refresh" @click="loadFollowups" block>
              بروزرسانی
            </v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- خلاصه آماری -->
    <v-row dense class="mb-4">
      <v-col cols="6" md="3">
        <v-card color="orange-lighten-4" variant="flat">
          <v-card-text class="text-center pa-3">
            <div class="text-h5 font-weight-bold">{{ pendingCount }}</div>
            <div class="text-caption">در انتظار پیگیری</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="6" md="3">
        <v-card color="red-lighten-4" variant="flat">
          <v-card-text class="text-center pa-3">
            <div class="text-h5 font-weight-bold">{{ overdueCount }}</div>
            <div class="text-caption">تأخیر در پیگیری</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="6" md="3">
        <v-card color="green-lighten-4" variant="flat">
          <v-card-text class="text-center pa-3">
            <div class="text-h5 font-weight-bold">{{ doneCount }}</div>
            <div class="text-caption">انجام شده</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="6" md="3">
        <v-card color="blue-lighten-4" variant="flat">
          <v-card-text class="text-center pa-3">
            <div class="text-h6 font-weight-bold">{{ $filters.formatNumber(totalAmount) }}</div>
            <div class="text-caption">مبلغ پیگیری‌ها</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- جدول -->
    <v-data-table
      :headers="headers"
      :items="filteredItems"
      :loading="loading"
      :search="search"
      class="elevation-1"
      :header-props="{ class: 'custom-header' }"
      density="comfortable"
    >
      <template v-slot:item.status="{ item }">
        <v-chip :color="statusColor(item.status)" size="small">
          {{ statusLabel(item.status) }}
        </v-chip>
      </template>
      <template v-slot:item.type="{ item }">
        <v-chip :color="typeColor(item.type)" size="small" variant="outlined">
          <v-icon start size="14">{{ typeIcon(item.type) }}</v-icon>
          {{ typeLabel(item.type) }}
        </v-chip>
      </template>
      <template v-slot:item.followupDate="{ item }">
        <span :class="isOverdue(item) ? 'text-red font-weight-bold' : ''">
          {{ item.followupDate || '—' }}
          <v-icon v-if="isOverdue(item)" color="red" size="14">mdi-alert</v-icon>
        </span>
      </template>
      <template v-slot:item.amount="{ item }">
        {{ item.amount ? $filters.formatNumber(item.amount) : '—' }}
      </template>
      <template v-slot:item.actions="{ item }">
        <v-btn icon size="small" variant="text" color="primary" @click="openDialog(item)">
          <v-icon>mdi-pencil</v-icon>
        </v-btn>
        <v-btn icon size="small" variant="text" color="success" @click="markDone(item)" :disabled="item.status === 'done'">
          <v-tooltip activator="parent" text="علامت انجام شده" />
          <v-icon>mdi-check-circle</v-icon>
        </v-btn>
        <v-btn icon size="small" variant="text" color="error" @click="deleteItem(item)">
          <v-icon>mdi-delete</v-icon>
        </v-btn>
      </template>
    </v-data-table>
  </v-container>

  <!-- دیالوگ ثبت/ویرایش -->
  <v-dialog v-model="dialog" max-width="600" persistent>
    <v-card>
      <v-toolbar color="primary" density="compact">
        <v-toolbar-title>{{ editItem.id ? 'ویرایش پیگیری' : 'پیگیری جدید' }}</v-toolbar-title>
        <v-spacer />
        <v-btn icon @click="dialog = false"><v-icon>mdi-close</v-icon></v-btn>
      </v-toolbar>
      <v-card-text class="pt-4">
        <v-row>
          <v-col cols="12" md="6">
            <Hpersonsearch v-model="editItem.person" label="شخص" />
          </v-col>
          <v-col cols="12" md="6">
            <v-select
              v-model="editItem.type"
              :items="typeOptions"
              item-title="label"
              item-value="value"
              label="نوع فعالیت"
              variant="outlined"
              density="compact"
            />
          </v-col>
          <v-col cols="12" md="6">
            <Hdatepicker v-model="editItem.date" label="تاریخ" />
          </v-col>
          <v-col cols="12" md="6">
            <Hdatepicker v-model="editItem.followupDate" label="تاریخ پیگیری بعدی" />
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              v-model="editItem.amount"
              label="مبلغ (اختیاری)"
              variant="outlined"
              density="compact"
              type="number"
            />
          </v-col>
          <v-col cols="12" md="6">
            <v-select
              v-model="editItem.status"
              :items="statusOptions"
              item-title="label"
              item-value="value"
              label="وضعیت"
              variant="outlined"
              density="compact"
            />
          </v-col>
          <v-col cols="12">
            <v-textarea
              v-model="editItem.des"
              label="توضیحات"
              variant="outlined"
              density="compact"
              rows="3"
            />
          </v-col>
        </v-row>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn @click="dialog = false">انصراف</v-btn>
        <v-btn color="primary" :loading="saving" @click="saveItem">ذخیره</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-snackbar v-model="snackbar.show" :color="snackbar.color" :timeout="3000">
    {{ snackbar.message }}
  </v-snackbar>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import Hdatepicker from '@/components/forms/Hdatepicker.vue'
import Hpersonsearch from '@/components/forms/Hpersonsearch.vue'

interface FollowupItem {
  id: number | null
  date: string
  followupDate: string | null
  type: string
  des: string | null
  amount: string | null
  status: string
  person: { id: number; nikename: string } | null
  submitter?: string
}

const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const search = ref('')
const filterStatus = ref<string | null>(null)
const filterType = ref<string | null>(null)
const items = ref<FollowupItem[]>([])
const snackbar = ref({ show: false, message: '', color: 'primary' })

const editItem = ref<FollowupItem>({
  id: null, date: '', followupDate: null, type: 'call',
  des: null, amount: null, status: 'pending', person: null
})

const statusOptions = [
  { value: 'pending', label: 'در انتظار' },
  { value: 'done', label: 'انجام شده' },
  { value: 'cancelled', label: 'لغو شده' },
]

const typeOptions = [
  { value: 'call', label: 'تماس تلفنی' },
  { value: 'visit', label: 'ویزیت' },
  { value: 'payment', label: 'وصول مطالبه' },
  { value: 'message', label: 'پیام' },
  { value: 'note', label: 'یادداشت' },
  { value: 'complaint', label: 'شکایت' },
]

const headers = [
  { title: 'شخص', key: 'person.nikename', sortable: true },
  { title: 'نوع', key: 'type' },
  { title: 'تاریخ', key: 'date', sortable: true },
  { title: 'پیگیری بعدی', key: 'followupDate', sortable: true },
  { title: 'مبلغ', key: 'amount', sortable: true },
  { title: 'وضعیت', key: 'status' },
  { title: 'توضیحات', key: 'des' },
  { title: 'عملیات', key: 'actions', sortable: false },
]

const today = new Date().toISOString().slice(0, 10)

const filteredItems = computed(() => {
  let res = items.value
  if (filterStatus.value) res = res.filter(i => i.status === filterStatus.value)
  if (filterType.value) res = res.filter(i => i.type === filterType.value)
  return res
})

const pendingCount = computed(() => items.value.filter(i => i.status === 'pending').length)
const doneCount = computed(() => items.value.filter(i => i.status === 'done').length)
const overdueCount = computed(() => items.value.filter(i => isOverdue(i)).length)
const totalAmount = computed(() => items.value.reduce((s, i) => s + (parseFloat(i.amount || '0') || 0), 0))

function isOverdue(item: FollowupItem) {
  if (item.status !== 'pending' || !item.followupDate) return false
  return item.followupDate < today
}

function statusColor(s: string) {
  return { pending: 'orange', done: 'green', cancelled: 'grey' }[s] ?? 'grey'
}

function statusLabel(s: string) {
  return statusOptions.find(o => o.value === s)?.label ?? s
}

function typeLabel(t: string) {
  return typeOptions.find(o => o.value === t)?.label ?? t
}

function typeColor(t: string) {
  return { call: 'blue', visit: 'purple', payment: 'green', message: 'teal', note: 'grey', complaint: 'red' }[t] ?? 'grey'
}

function typeIcon(t: string) {
  return { call: 'mdi-phone', visit: 'mdi-map-marker', payment: 'mdi-cash', message: 'mdi-message', note: 'mdi-note', complaint: 'mdi-alert' }[t] ?? 'mdi-circle'
}

async function loadFollowups() {
  loading.value = true
  try {
    const res = await axios.post('/api/acc/person/followup/list', {
      status: filterStatus.value || undefined
    })
    items.value = res.data
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function openDialog(item: FollowupItem | null) {
  if (item) {
    editItem.value = { ...item }
  } else {
    editItem.value = { id: null, date: '', followupDate: null, type: 'call', des: null, amount: null, status: 'pending', person: null }
  }
  dialog.value = true
}

async function saveItem() {
  if (!editItem.value.person || !editItem.value.type || !editItem.value.date) {
    snackbar.value = { show: true, message: 'شخص، نوع و تاریخ الزامی هستند.', color: 'error' }
    return
  }
  saving.value = true
  try {
    await axios.post('/api/acc/person/followup/mod', {
      id: editItem.value.id,
      personId: editItem.value.person.id,
      type: editItem.value.type,
      date: editItem.value.date,
      followupDate: editItem.value.followupDate,
      des: editItem.value.des,
      amount: editItem.value.amount,
      status: editItem.value.status,
    })
    dialog.value = false
    snackbar.value = { show: true, message: 'پیگیری ذخیره شد.', color: 'success' }
    await loadFollowups()
  } catch (e) {
    snackbar.value = { show: true, message: 'خطا در ذخیره', color: 'error' }
  } finally {
    saving.value = false
  }
}

async function markDone(item: FollowupItem) {
  try {
    await axios.post('/api/acc/person/followup/mod', {
      id: item.id,
      personId: item.person!.id,
      type: item.type,
      date: item.date,
      followupDate: item.followupDate,
      des: item.des,
      amount: item.amount,
      status: 'done',
    })
    await loadFollowups()
  } catch (e) {
    console.error(e)
  }
}

async function deleteItem(item: FollowupItem) {
  const r = await Swal.fire({
    text: 'پیگیری حذف شود؟',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'بله',
    cancelButtonText: 'خیر'
  })
  if (!r.isConfirmed) return
  try {
    await axios.post(`/api/acc/person/followup/del/${item.id}`)
    await loadFollowups()
  } catch (e) {
    console.error(e)
  }
}

onMounted(loadFollowups)
</script>
