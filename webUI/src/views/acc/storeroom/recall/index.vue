<template>
  <v-toolbar color="toolbar" title="مدیریت Recall">
    <template v-slot:prepend>
      <v-tooltip text="بازگشت" location="bottom">
        <template v-slot:activator="{ props }">
          <v-btn v-bind="props" @click="$router.back()" class="d-none d-sm-flex" variant="text" icon="mdi-arrow-right" />
        </template>
      </v-tooltip>
    </template>
    <v-spacer />
    <v-btn color="error" prepend-icon="mdi-alert" @click="newDialog = true">Recall جدید</v-btn>
  </v-toolbar>

  <v-container fluid class="pa-4">
    <v-data-table
      :headers="headers"
      :items="items"
      :loading="loading"
      density="compact"
      class="elevation-1 text-center"
    >
      <template v-slot:item.priority="{ item }">
        <v-chip :color="priorityColor(item.priority)" size="small">{{ priorityLabel(item.priority) }}</v-chip>
      </template>
      <template v-slot:item.status="{ item }">
        <v-chip
          :color="item.status === 'active' ? 'error' : 'success'"
          size="small"
          :class="item.status === 'active' ? 'pulse-chip' : ''"
        >
          {{ item.status === 'active' ? 'فعال' : 'رفع شده' }}
        </v-chip>
      </template>
      <template v-slot:item.reason="{ item }">
        <span :title="item.reason">{{ truncate(item.reason, 40) }}</span>
      </template>
      <template v-slot:item.actions="{ item }">
        <v-btn
          v-if="item.status === 'active'"
          size="small"
          icon="mdi-check"
          color="success"
          variant="text"
          @click="resolveItem(item.id)"
        />
        <v-btn
          size="small"
          icon="mdi-delete"
          color="error"
          variant="text"
          @click="confirmDelete(item.id)"
        />
      </template>
      <template v-slot:empty>
        <div class="text-center pa-4 text-grey">Recall فعالی وجود ندارد</div>
      </template>
    </v-data-table>
  </v-container>

  <!-- دیالوگ ایجاد Recall جدید -->
  <v-dialog v-model="newDialog" max-width="600" scrollable>
    <v-card>
      <v-toolbar color="error" title="Recall جدید" theme="dark">
        <v-spacer />
        <v-btn icon="mdi-close" variant="text" @click="newDialog = false" />
      </v-toolbar>
      <v-card-text>
        <v-row>
          <v-col cols="12" sm="6">
            <v-text-field v-model="newForm.commodity" label="کالا" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="newForm.lotNo" label="شماره لات" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="12">
            <v-textarea v-model="newForm.reason" label="دلیل Recall" variant="outlined" density="compact" rows="3" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-select
              v-model="newForm.priority"
              :items="priorityOptions"
              label="اولویت"
              variant="outlined"
              density="compact"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="newForm.startDate" label="تاریخ شروع" variant="outlined" density="compact" placeholder="1403/01/01" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model.number="newForm.affectedCount" type="number" label="تعداد متأثر" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="12">
            <v-textarea v-model="newForm.notes" label="یادداشت" variant="outlined" density="compact" rows="2" />
          </v-col>
        </v-row>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="newDialog = false">انصراف</v-btn>
        <v-btn color="error" variant="elevated" @click="createRecall" :loading="saving">ثبت Recall</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- دیالوگ حذف -->
  <v-dialog v-model="deleteDialog.show" max-width="400">
    <v-card>
      <v-card-title>تأیید حذف</v-card-title>
      <v-card-text>آیا از حذف این Recall مطمئن هستید؟</v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="deleteDialog.show = false">خیر</v-btn>
        <v-btn color="error" variant="text" @click="deleteRecall">بله</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">
    {{ snackbar.message }}
  </v-snackbar>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const loading = ref(false)
const saving = ref(false)
const newDialog = ref(false)
const items = ref([])
const deleteDialog = ref({ show: false, id: null })
const snackbar = ref({ show: false, message: '', color: 'success' })

const priorityOptions = [
  { title: 'بالا', value: 'high' },
  { title: 'متوسط', value: 'medium' },
  { title: 'پایین', value: 'low' }
]

const newForm = ref({
  commodity: '',
  lotNo: '',
  reason: '',
  priority: 'high',
  startDate: '',
  affectedCount: 0,
  notes: ''
})

const headers = [
  { title: 'کالا', key: 'commodity', align: 'center' },
  { title: 'شماره لات', key: 'lotNo', align: 'center' },
  { title: 'دلیل', key: 'reason', align: 'center' },
  { title: 'اولویت', key: 'priority', align: 'center' },
  { title: 'تعداد متأثر', key: 'affectedCount', align: 'center' },
  { title: 'تاریخ شروع', key: 'startDate', align: 'center' },
  { title: 'وضعیت', key: 'status', align: 'center' },
  { title: 'عملیات', key: 'actions', align: 'center', sortable: false }
]

function priorityColor(priority) {
  const map = { high: 'error', medium: 'orange', low: 'yellow-darken-3' }
  return map[priority] || 'grey'
}

function priorityLabel(priority) {
  const map = { high: 'بالا', medium: 'متوسط', low: 'پایین' }
  return map[priority] || priority
}

function truncate(str, len) {
  if (!str) return ''
  return str.length > len ? str.substring(0, len) + '...' : str
}

function showSnack(message, color = 'success') {
  snackbar.value = { show: true, message, color }
}

function confirmDelete(id) {
  deleteDialog.value = { show: true, id }
}

async function loadData() {
  loading.value = true
  try {
    const res = await axios.get('/api/storeroom/recall/list')
    items.value = res.data?.data || res.data || []
  } catch (e) {
    console.error(e)
    showSnack('خطا در بارگذاری', 'error')
  } finally {
    loading.value = false
  }
}

async function createRecall() {
  saving.value = true
  try {
    await axios.post('/api/storeroom/recall/create', newForm.value)
    newDialog.value = false
    newForm.value = { commodity: '', lotNo: '', reason: '', priority: 'high', startDate: '', affectedCount: 0, notes: '' }
    showSnack('Recall ثبت شد')
    await loadData()
  } catch (e) {
    console.error(e)
    showSnack('خطا در ثبت Recall', 'error')
  } finally {
    saving.value = false
  }
}

async function resolveItem(id) {
  try {
    await axios.post(`/api/storeroom/recall/resolve/${id}`)
    showSnack('Recall رفع شد')
    await loadData()
  } catch (e) {
    console.error(e)
    showSnack('خطا', 'error')
  }
}

async function deleteRecall() {
  const id = deleteDialog.value.id
  deleteDialog.value.show = false
  try {
    await axios.delete(`/api/storeroom/recall/delete/${id}`)
    showSnack('Recall حذف شد')
    await loadData()
  } catch (e) {
    console.error(e)
    showSnack('خطا در حذف', 'error')
  }
}

onMounted(loadData)
</script>

<style scoped>
:deep(.v-data-table-header th) { text-align: center !important; }

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.6; }
}

.pulse-chip {
  animation: pulse 1.5s infinite;
}
</style>
