<template>
  <v-toolbar color="toolbar" title="سفارش خرید">
    <template v-slot:prepend>
      <v-tooltip text="بازگشت" location="bottom">
        <template v-slot:activator="{ props }">
          <v-btn v-bind="props" @click="$router.back()" class="d-none d-sm-flex" variant="text" icon="mdi-arrow-right" />
        </template>
      </v-tooltip>
    </template>
    <v-spacer />
    <v-btn color="primary" prepend-icon="mdi-plus" @click="newDialog = true">سفارش خرید جدید</v-btn>
  </v-toolbar>

  <v-container fluid class="pa-4">
    <!-- فیلتر تب‌ها -->
    <v-btn-group class="mb-4" variant="outlined" divided>
      <v-btn :color="activeFilter === 'active' ? 'primary' : ''" @click="activeFilter = 'active'">در جریان</v-btn>
      <v-btn :color="activeFilter === 'all' ? 'primary' : ''" @click="activeFilter = 'all'">همه</v-btn>
    </v-btn-group>

    <v-data-table
      :headers="headers"
      :items="filteredItems"
      :loading="loading"
      density="compact"
      class="elevation-1 text-center"
    >
      <template v-slot:item.status="{ item }">
        <v-chip :color="statusColor(item.status)" size="small">{{ statusLabel(item.status) }}</v-chip>
      </template>
      <template v-slot:item.totalAmount="{ item }">
        {{ formatNumber(item.totalAmount) }}
      </template>
      <template v-slot:item.actions="{ item }">
        <v-btn size="small" icon="mdi-eye" color="primary" variant="text" :to="'/acc/storeroom/po/view/' + item.id" />
        <v-btn
          v-if="item.status === 'draft' || item.status === 'pending'"
          size="small" icon="mdi-check" color="success" variant="text"
          @click="approveItem(item.id)"
        />
        <v-btn
          v-if="item.status !== 'received' && item.status !== 'cancelled'"
          size="small" icon="mdi-cancel" color="error" variant="text"
          @click="cancelItem(item.id)"
        />
      </template>
      <template v-slot:empty>
        <div class="text-center pa-4 text-grey">سفارش خریدی وجود ندارد</div>
      </template>
    </v-data-table>
  </v-container>

  <!-- دیالوگ ایجاد سفارش خرید جدید -->
  <v-dialog v-model="newDialog" max-width="700" scrollable>
    <v-card>
      <v-toolbar color="toolbar" title="سفارش خرید جدید">
        <v-spacer />
        <v-btn icon="mdi-close" variant="text" @click="newDialog = false" />
      </v-toolbar>
      <v-card-text>
        <v-row>
          <v-col cols="12" sm="6">
            <v-text-field v-model="newForm.date" label="تاریخ" variant="outlined" density="compact" placeholder="1403/01/01" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="newForm.personName" label="نام تأمین‌کننده" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="12">
            <v-textarea v-model="newForm.notes" label="توضیحات" variant="outlined" density="compact" rows="2" />
          </v-col>
        </v-row>

        <v-divider class="my-3" />
        <div class="d-flex justify-space-between align-center mb-2">
          <span class="text-subtitle-2">اقلام سفارش</span>
          <v-btn size="small" color="primary" prepend-icon="mdi-plus" @click="addItem">افزودن ردیف</v-btn>
        </div>

        <v-table density="compact">
          <thead>
            <tr>
              <th class="text-center">کالا</th>
              <th class="text-center">تعداد</th>
              <th class="text-center">قیمت واحد</th>
              <th class="text-center">توضیحات</th>
              <th class="text-center">حذف</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, idx) in newForm.items" :key="idx">
              <td>
                <v-text-field v-model="row.commodity" variant="plain" density="compact" hide-details placeholder="نام کالا" />
              </td>
              <td>
                <v-text-field v-model.number="row.qty" type="number" variant="plain" density="compact" hide-details />
              </td>
              <td>
                <v-text-field v-model.number="row.unitPrice" type="number" variant="plain" density="compact" hide-details />
              </td>
              <td>
                <v-text-field v-model="row.notes" variant="plain" density="compact" hide-details />
              </td>
              <td class="text-center">
                <v-btn icon="mdi-delete" color="error" size="x-small" variant="text" @click="removeItem(idx)" />
              </td>
            </tr>
            <tr v-if="newForm.items.length === 0">
              <td colspan="5" class="text-center text-grey pa-3">ردیفی وجود ندارد</td>
            </tr>
          </tbody>
        </v-table>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="newDialog = false">انصراف</v-btn>
        <v-btn color="primary" variant="elevated" @click="createPO" :loading="saving">ثبت سفارش</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">
    {{ snackbar.message }}
  </v-snackbar>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const loading = ref(false)
const saving = ref(false)
const newDialog = ref(false)
const activeFilter = ref('active')
const items = ref([])
const snackbar = ref({ show: false, message: '', color: 'success' })

const newForm = ref({
  date: '',
  personName: '',
  notes: '',
  items: []
})

const headers = [
  { title: 'کد', key: 'code', align: 'center' },
  { title: 'تاریخ', key: 'date', align: 'center' },
  { title: 'تأمین‌کننده', key: 'personName', align: 'center' },
  { title: 'تعداد اقلام', key: 'itemsCount', align: 'center' },
  { title: 'مبلغ کل', key: 'totalAmount', align: 'center' },
  { title: 'وضعیت', key: 'status', align: 'center' },
  { title: 'عملیات', key: 'actions', align: 'center', sortable: false }
]

const activeStatuses = ['draft', 'pending', 'approved']

const filteredItems = computed(() => {
  if (activeFilter.value === 'active') {
    return items.value.filter(i => activeStatuses.includes(i.status))
  }
  return items.value
})

function statusColor(status) {
  const map = { draft: 'grey', pending: 'orange', approved: 'blue', received: 'success', cancelled: 'error' }
  return map[status] || 'grey'
}

function statusLabel(status) {
  const map = { draft: 'پیش‌نویس', pending: 'در انتظار', approved: 'تأیید شده', received: 'دریافت شده', cancelled: 'لغو شده' }
  return map[status] || status
}

function formatNumber(value) {
  if (!value) return '0'
  return Number(value).toLocaleString('fa-IR')
}

function showSnack(message, color = 'success') {
  snackbar.value = { show: true, message, color }
}

function addItem() {
  newForm.value.items.push({ commodity: '', qty: 1, unitPrice: 0, notes: '' })
}

function removeItem(idx) {
  newForm.value.items.splice(idx, 1)
}

async function loadData() {
  loading.value = true
  try {
    const res = await axios.get('/api/storeroom/po/list')
    items.value = res.data?.data || res.data || []
  } catch (e) {
    console.error(e)
    showSnack('خطا در بارگذاری داده‌ها', 'error')
  } finally {
    loading.value = false
  }
}

async function createPO() {
  saving.value = true
  try {
    await axios.post('/api/storeroom/po/create', newForm.value)
    newDialog.value = false
    newForm.value = { date: '', personName: '', notes: '', items: [] }
    showSnack('سفارش خرید با موفقیت ثبت شد')
    await loadData()
  } catch (e) {
    console.error(e)
    showSnack('خطا در ثبت سفارش', 'error')
  } finally {
    saving.value = false
  }
}

async function approveItem(id) {
  try {
    await axios.post(`/api/storeroom/po/approve/${id}`)
    showSnack('سفارش تأیید شد')
    await loadData()
  } catch (e) {
    console.error(e)
    showSnack('خطا در تأیید سفارش', 'error')
  }
}

async function cancelItem(id) {
  try {
    await axios.post(`/api/storeroom/po/cancel/${id}`)
    showSnack('سفارش لغو شد')
    await loadData()
  } catch (e) {
    console.error(e)
    showSnack('خطا در لغو سفارش', 'error')
  }
}

onMounted(loadData)
</script>

<style scoped>
:deep(.v-data-table-header th) { text-align: center !important; }
</style>
