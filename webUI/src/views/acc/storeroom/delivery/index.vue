<template>
  <v-toolbar color="toolbar" title="ارسال و رهگیری">
    <template v-slot:prepend>
      <v-tooltip text="بازگشت" location="bottom">
        <template v-slot:activator="{ props }">
          <v-btn v-bind="props" @click="$router.back()" class="d-none d-sm-flex" variant="text" icon="mdi-arrow-right" />
        </template>
      </v-tooltip>
    </template>
    <v-spacer />
    <v-btn color="primary" prepend-icon="mdi-plus" @click="createDialog = true">ارسال جدید</v-btn>
  </v-toolbar>

  <v-container fluid class="pa-4">
    <!-- فیلتر وضعیت -->
    <v-chip-group v-model="statusFilter" class="mb-4" selected-class="text-primary">
      <v-chip value="all">همه</v-chip>
      <v-chip value="pending">در انتظار</v-chip>
      <v-chip value="sent">ارسال شده</v-chip>
      <v-chip value="delivered">تحویل داده شده</v-chip>
      <v-chip value="failed">ناموفق</v-chip>
      <v-chip value="returned">مرجوع</v-chip>
    </v-chip-group>

    <v-data-table
      :headers="headers"
      :items="filteredItems"
      :loading="loading"
      density="compact"
      class="elevation-1 text-center"
    >
      <template v-slot:item.courier="{ item }">
        <v-chip :color="courierColor(item.courier)" size="small" variant="flat">{{ item.courier }}</v-chip>
      </template>
      <template v-slot:item.status="{ item }">
        <v-chip :color="deliveryStatusColor(item.status)" size="small">{{ deliveryStatusLabel(item.status) }}</v-chip>
      </template>
      <template v-slot:item.actions="{ item }">
        <v-btn size="small" icon="mdi-pencil" color="primary" variant="text" @click="openEditDialog(item)" />
      </template>
      <template v-slot:empty>
        <div class="text-center pa-4 text-grey">رکوردی وجود ندارد</div>
      </template>
    </v-data-table>
  </v-container>

  <!-- دیالوگ ایجاد -->
  <v-dialog v-model="createDialog" max-width="550">
    <v-card>
      <v-toolbar color="toolbar" title="ایجاد ارسال جدید">
        <v-spacer />
        <v-btn icon="mdi-close" variant="text" @click="createDialog = false" />
      </v-toolbar>
      <v-card-text>
        <v-row>
          <v-col cols="12">
            <v-text-field v-model="createForm.ticketId" label="کد حواله" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-select
              v-model="createForm.courier"
              :items="courierOptions"
              label="پیک"
              variant="outlined"
              density="compact"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="createForm.recipientName" label="نام گیرنده" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="createForm.recipientTel" label="تلفن گیرنده" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="12">
            <v-textarea v-model="createForm.notes" label="توضیحات" variant="outlined" density="compact" rows="2" />
          </v-col>
        </v-row>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="createDialog = false">انصراف</v-btn>
        <v-btn color="primary" variant="elevated" @click="createDelivery" :loading="saving">ثبت</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- دیالوگ ویرایش -->
  <v-dialog v-model="editDialog" max-width="550">
    <v-card>
      <v-toolbar color="toolbar" title="ویرایش ارسال">
        <v-spacer />
        <v-btn icon="mdi-close" variant="text" @click="editDialog = false" />
      </v-toolbar>
      <v-card-text>
        <v-row>
          <v-col cols="12" sm="6">
            <v-select
              v-model="editForm.status"
              :items="statusOptions"
              label="وضعیت"
              variant="outlined"
              density="compact"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="editForm.trackingCode" label="کد رهگیری" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="editForm.sentAt" label="تاریخ ارسال" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="editForm.deliveredAt" label="تاریخ تحویل" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="12">
            <v-textarea v-model="editForm.notes" label="توضیحات" variant="outlined" density="compact" rows="2" />
          </v-col>
        </v-row>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="editDialog = false">انصراف</v-btn>
        <v-btn color="primary" variant="elevated" @click="updateDelivery" :loading="saving">ذخیره</v-btn>
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
const createDialog = ref(false)
const editDialog = ref(false)
const statusFilter = ref('all')
const items = ref([])
const selectedItem = ref(null)
const snackbar = ref({ show: false, message: '', color: 'success' })

const createForm = ref({ ticketId: '', courier: '', recipientName: '', recipientTel: '', notes: '' })
const editForm = ref({ status: '', trackingCode: '', sentAt: '', deliveredAt: '', notes: '' })

const courierOptions = ['تیپاکس', 'ایرانپیام', 'چاپار', 'مستقیم', 'سایر']
const statusOptions = [
  { title: 'در انتظار', value: 'pending' },
  { title: 'ارسال شده', value: 'sent' },
  { title: 'تحویل داده شده', value: 'delivered' },
  { title: 'ناموفق', value: 'failed' },
  { title: 'مرجوع', value: 'returned' }
]

const headers = [
  { title: 'کد حواله', key: 'ticketCode', align: 'center' },
  { title: 'گیرنده', key: 'recipientName', align: 'center' },
  { title: 'پیک', key: 'courier', align: 'center' },
  { title: 'کد رهگیری', key: 'trackingCode', align: 'center' },
  { title: 'تاریخ ارسال', key: 'sentAt', align: 'center' },
  { title: 'تاریخ تحویل', key: 'deliveredAt', align: 'center' },
  { title: 'وضعیت', key: 'status', align: 'center' },
  { title: 'ویرایش', key: 'actions', align: 'center', sortable: false }
]

const filteredItems = computed(() => {
  if (statusFilter.value === 'all') return items.value
  return items.value.filter(i => i.status === statusFilter.value)
})

function courierColor(courier) {
  const map = { 'تیپاکس': 'red', 'ایرانپیام': 'blue', 'چاپار': 'yellow-darken-3', 'مستقیم': 'green', 'سایر': 'grey' }
  return map[courier] || 'grey'
}

function deliveryStatusColor(status) {
  const map = { pending: 'warning', sent: 'blue', delivered: 'success', failed: 'error', returned: 'purple' }
  return map[status] || 'grey'
}

function deliveryStatusLabel(status) {
  const map = { pending: 'در انتظار', sent: 'ارسال شده', delivered: 'تحویل داده شده', failed: 'ناموفق', returned: 'مرجوع' }
  return map[status] || status
}

function showSnack(message, color = 'success') {
  snackbar.value = { show: true, message, color }
}

function openEditDialog(item) {
  selectedItem.value = item
  editForm.value = {
    status: item.status,
    trackingCode: item.trackingCode || '',
    sentAt: item.sentAt || '',
    deliveredAt: item.deliveredAt || '',
    notes: item.notes || ''
  }
  editDialog.value = true
}

async function loadData() {
  loading.value = true
  try {
    const res = await axios.get('/api/storeroom/delivery/list')
    items.value = res.data?.data || res.data || []
  } catch (e) {
    console.error(e)
    showSnack('خطا در بارگذاری', 'error')
  } finally {
    loading.value = false
  }
}

async function createDelivery() {
  saving.value = true
  try {
    await axios.post('/api/storeroom/delivery/create', createForm.value)
    createDialog.value = false
    createForm.value = { ticketId: '', courier: '', recipientName: '', recipientTel: '', notes: '' }
    showSnack('ارسال ثبت شد')
    await loadData()
  } catch (e) {
    console.error(e)
    showSnack('خطا در ثبت ارسال', 'error')
  } finally {
    saving.value = false
  }
}

async function updateDelivery() {
  saving.value = true
  try {
    await axios.post(`/api/storeroom/delivery/update/${selectedItem.value.id}`, editForm.value)
    editDialog.value = false
    showSnack('ارسال به‌روز شد')
    await loadData()
  } catch (e) {
    console.error(e)
    showSnack('خطا در به‌روزرسانی', 'error')
  } finally {
    saving.value = false
  }
}

onMounted(loadData)
</script>

<style scoped>
:deep(.v-data-table-header th) { text-align: center !important; }
</style>
