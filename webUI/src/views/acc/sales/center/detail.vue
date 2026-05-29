<template>
  <v-toolbar color="toolbar">
    <template v-slot:prepend>
      <v-btn @click="$router.back()" variant="text" icon="mdi-arrow-right" />
    </template>
    <v-toolbar-title>
      {{ center?.name ?? 'مرکز فروش' }}
      <span v-if="center?.city" class="text-caption text-medium-emphasis ms-2">{{ center.city }}</span>
    </v-toolbar-title>
    <v-spacer />
    <v-btn color="primary" prepend-icon="mdi-plus" @click="openReportDialog(null)">ثبت گزارش</v-btn>
    <v-btn color="secondary" variant="tonal" prepend-icon="mdi-briefcase-plus" class="ms-2" @click="openDealDialog(null)">فرصت جدید</v-btn>
  </v-toolbar>

  <v-container fluid class="pa-2">
    <!-- info chips -->
    <div v-if="center" class="d-flex flex-wrap gap-2 mb-3">
      <v-chip size="small" prepend-icon="mdi-tag-outline">{{ centerTypeLabel(center.type) }}</v-chip>
      <v-chip v-if="center.potential" size="small" prepend-icon="mdi-star">
        {{ '★'.repeat(center.potential) + '☆'.repeat(4 - center.potential) }}
      </v-chip>
      <v-chip v-if="center.tel" size="small" prepend-icon="mdi-phone">{{ center.tel }}</v-chip>
      <v-chip v-if="center.person" size="small" prepend-icon="mdi-account" color="primary" variant="tonal"
        @click="$router.push('/acc/persons/card?id=' + center.person.id)" style="cursor:pointer">
        {{ center.person.name }}
      </v-chip>
      <v-chip v-if="center.address" size="small" prepend-icon="mdi-map-marker">{{ center.address }}</v-chip>
    </div>

    <div v-if="loading" class="d-flex justify-center pa-8">
      <v-progress-circular indeterminate />
    </div>

    <v-tabs v-else v-model="tab" class="mb-3">
      <v-tab value="deals">
        <v-badge :content="deals.length" color="primary" v-if="deals.length">معاملات</v-badge>
        <span v-else>معاملات</span>
      </v-tab>
      <v-tab value="reports">
        <v-badge :content="reports.length" color="blue" v-if="reports.length">گزارشات</v-badge>
        <span v-else>گزارشات</span>
      </v-tab>
      <v-tab value="plans">
        <v-badge :content="plans.length" color="orange" v-if="plans.length">برنامه‌ها</v-badge>
        <span v-else>برنامه‌ها</span>
      </v-tab>
      <v-tab value="timeline">خط زمانی</v-tab>
    </v-tabs>

    <!-- ════ Deals ════ -->
    <v-window v-model="tab">
      <v-window-item value="deals">
        <v-card v-if="deals.length === 0" variant="outlined" class="pa-6 text-center text-disabled">
          هیچ فرصت فروشی ثبت نشده است
        </v-card>
        <v-row dense>
          <v-col v-for="deal in deals" :key="deal.id" cols="12" sm="6" md="4">
            <v-card variant="outlined" class="h-100">
              <v-card-title class="py-2 px-3 d-flex align-center justify-space-between">
                <span class="text-body-2 font-weight-bold">{{ deal.title }}</span>
                <v-chip :color="stageColor(deal.stage)" size="x-small" variant="tonal">{{ stageLabel(deal.stage) }}</v-chip>
              </v-card-title>
              <v-divider />
              <v-card-text class="pa-3">
                <div v-if="deal.amount" class="text-body-1 font-weight-bold mb-1">
                  {{ Number(deal.amount).toLocaleString('fa') }} ریال
                </div>
                <div v-if="deal.dueDate" class="text-caption" :class="isDuePast(deal.dueDate) ? 'text-error' : ''">
                  <v-icon size="12">mdi-calendar-clock</v-icon> سررسید: {{ deal.dueDate }}
                </div>
                <div v-if="deal.des" class="text-caption text-medium-emphasis mt-1">{{ deal.des }}</div>
                <div class="d-flex flex-wrap gap-1 mt-2">
                  <v-chip v-if="deal.preInvoiceId" size="x-small" color="blue" variant="tonal"
                    @click="$router.push('/acc/presell/mod?id=' + deal.preInvoiceId)" style="cursor:pointer">
                    پیش‌فاکتور #{{ deal.preInvoiceId }}
                  </v-chip>
                  <v-chip v-if="deal.storeroomTicketId" size="x-small" color="orange" variant="tonal">
                    حواله #{{ deal.storeroomTicketId }}
                  </v-chip>
                  <v-chip v-if="deal.sellDocId" size="x-small" color="green" variant="tonal">
                    فاکتور #{{ deal.sellDocId }}
                  </v-chip>
                </div>
              </v-card-text>
              <v-card-actions class="pa-2">
                <v-btn size="x-small" variant="text" prepend-icon="mdi-pencil" @click="openDealDialog(deal)">ویرایش</v-btn>
                <v-spacer />
                <v-btn
                  v-for="s in nextStages(deal.stage)"
                  :key="s.value"
                  size="x-small"
                  :color="s.color"
                  variant="tonal"
                  @click="advanceStage(deal, s.value)"
                >{{ s.label }}</v-btn>
                <v-btn
                  v-if="deal.stage === 'pre_invoice' && !deal.approvedAt"
                  size="x-small"
                  color="success"
                  variant="tonal"
                  @click="approveDeal(deal)"
                >تأیید مدیر</v-btn>
              </v-card-actions>
            </v-card>
          </v-col>
        </v-row>
      </v-window-item>

      <!-- ════ Reports ════ -->
      <v-window-item value="reports">
        <v-card v-if="reports.length === 0" variant="outlined" class="pa-6 text-center text-disabled">
          هیچ گزارشی ثبت نشده است
        </v-card>
        <v-timeline density="compact" side="end">
          <v-timeline-item
            v-for="r in reports"
            :key="r.id"
            :dot-color="resultColor(r.result)"
            size="small"
          >
            <template v-slot:opposite>
              <span class="text-caption">{{ r.date }}</span>
            </template>
            <v-card variant="outlined" class="mb-2">
              <v-card-text class="pa-3">
                <div class="d-flex align-center justify-space-between mb-1">
                  <v-chip size="x-small" :color="resultColor(r.result)" variant="tonal">{{ resultLabel(r.result) }}</v-chip>
                  <div>
                    <v-btn icon="mdi-pencil" size="x-small" variant="text" @click="openReportDialog(r)" />
                    <v-btn icon="mdi-delete" size="x-small" variant="text" color="error" @click="deleteReport(r)" />
                  </div>
                </div>
                <div v-if="r.des" class="text-body-2">{{ r.des }}</div>
                <div v-if="r.nextAction" class="text-caption text-warning mt-1">
                  <v-icon size="12">mdi-arrow-right-circle</v-icon> {{ r.nextAction }}
                  <span v-if="r.nextDate"> — {{ r.nextDate }}</span>
                </div>
                <div v-if="r.deal" class="text-caption text-primary mt-1">
                  <v-icon size="12">mdi-briefcase</v-icon> {{ r.deal.title }}
                </div>
                <div v-if="r.submitter" class="text-caption text-disabled">{{ r.submitter.mobile }}</div>
              </v-card-text>
            </v-card>
          </v-timeline-item>
        </v-timeline>
      </v-window-item>

      <!-- ════ Plans ════ -->
      <v-window-item value="plans">
        <v-card v-if="plans.length === 0" variant="outlined" class="pa-6 text-center text-disabled">
          هیچ برنامه بازدیدی ثبت نشده است
        </v-card>
        <v-list>
          <v-list-item
            v-for="p in plans"
            :key="p.id"
            :prepend-icon="p.actionType === 'visit' ? 'mdi-map-marker-outline' : 'mdi-phone-outline'"
            :subtitle="p.scheduledDate"
          >
            <template v-slot:title>
              {{ p.actionType === 'visit' ? 'ویزیت حضوری' : 'تماس تلفنی' }}
              <v-chip v-if="p.done" size="x-small" color="success" class="ms-1">انجام شد</v-chip>
              <v-chip v-else size="x-small" color="warning" class="ms-1">در انتظار</v-chip>
            </template>
            <template v-slot:append>
              <span v-if="p.des" class="text-caption text-disabled">{{ p.des.substring(0, 30) }}</span>
            </template>
          </v-list-item>
        </v-list>
      </v-window-item>

      <!-- ════ Timeline ════ -->
      <v-window-item value="timeline">
        <v-timeline density="compact" side="end">
          <v-timeline-item
            v-for="item in timelineItems"
            :key="item._key"
            :dot-color="item.color"
            size="small"
          >
            <template v-slot:opposite>
              <span class="text-caption">{{ item.date }}</span>
            </template>
            <div>
              <v-chip size="x-small" :color="item.color" variant="tonal" class="me-1">{{ item.type }}</v-chip>
              <span class="text-body-2">{{ item.text }}</span>
              <div v-if="item.sub" class="text-caption text-disabled">{{ item.sub }}</div>
            </div>
          </v-timeline-item>
        </v-timeline>
      </v-window-item>
    </v-window>
  </v-container>

  <!-- ════ Report Dialog ════ -->
  <v-dialog v-model="reportDialog" max-width="520" persistent>
    <v-card>
      <v-card-title class="pa-4">{{ reportForm.id ? 'ویرایش گزارش' : 'ثبت گزارش بازدید/تماس' }}</v-card-title>
      <v-divider />
      <v-card-text class="pa-4">
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-text-field v-model="reportForm.date" label="تاریخ *" variant="outlined" density="compact" placeholder="1403/01/15" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-select
              v-model="reportForm.result"
              :items="resultOptions"
              item-title="label"
              item-value="value"
              label="نتیجه"
              variant="outlined"
              density="compact"
            />
          </v-col>
          <v-col cols="12">
            <v-textarea v-model="reportForm.des" label="گزارش" variant="outlined" density="compact" rows="3" auto-grow />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="reportForm.nextAction" label="اقدام بعدی" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="reportForm.nextDate" label="تاریخ اقدام بعدی" variant="outlined" density="compact" placeholder="1403/01/20" />
          </v-col>
          <v-col cols="12" v-if="reportForm.result === 'deal'">
            <v-text-field v-model="reportForm.dealTitle" label="عنوان معامله (ایجاد فرصت خودکار)" variant="outlined" density="compact" />
          </v-col>
        </v-row>
      </v-card-text>
      <v-divider />
      <v-card-actions class="pa-3">
        <v-btn variant="text" @click="reportDialog = false">انصراف</v-btn>
        <v-spacer />
        <v-btn color="primary" variant="flat" :loading="reportSaving" @click="saveReport">ذخیره</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- ════ Deal Dialog ════ -->
  <v-dialog v-model="dealDialogOpen" max-width="500" persistent>
    <v-card>
      <v-card-title class="pa-4">{{ dealForm.id ? 'ویرایش فرصت' : 'فرصت جدید' }}</v-card-title>
      <v-divider />
      <v-card-text class="pa-4">
        <v-row dense>
          <v-col cols="12">
            <v-text-field v-model="dealForm.title" label="عنوان فرصت *" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="dealForm.amount" label="مبلغ (ریال)" variant="outlined" density="compact" type="number" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="dealForm.dueDate" label="سررسید" variant="outlined" density="compact" placeholder="1403/01/15" />
          </v-col>
          <v-col cols="12">
            <v-select
              v-model="dealForm.stage"
              :items="stages"
              item-title="label"
              item-value="value"
              label="مرحله"
              variant="outlined"
              density="compact"
            />
          </v-col>
          <v-col cols="12">
            <v-textarea v-model="dealForm.des" label="توضیحات" variant="outlined" density="compact" rows="2" auto-grow />
          </v-col>
        </v-row>
      </v-card-text>
      <v-divider />
      <v-card-actions class="pa-3">
        <v-btn variant="text" @click="dealDialogOpen = false">انصراف</v-btn>
        <v-spacer />
        <v-btn color="primary" variant="flat" :loading="dealSaving" @click="saveDeal">ذخیره</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import Swal from 'sweetalert2'
import { useApplicationStore } from '../../../../stores/applicationStore'

const appStore = useApplicationStore()
const route = useRoute()
const router = useRouter()
const centerId = computed(() => Number(route.params.id ?? route.query.id))

interface Center { id: number; name: string; type: string | null; potential: number | null; tel: string | null; address: string | null; city: string | null; person: { id: number; name: string } | null }
interface Deal { id: number; title: string; stage: string; amount: string | null; dueDate: string | null; des: string | null; preInvoiceId: number | null; storeroomTicketId: number | null; sellDocId: number | null; approvedAt: string | null; approvedBy: { id: number; mobile: string } | null; createdAt: string }
interface Report { id: number; date: string; result: string; des: string | null; nextAction: string | null; nextDate: string | null; deal: { id: number; title: string } | null; submitter: { id: number; mobile: string } | null }
interface Plan { id: number; scheduledDate: string; actionType: string; done: boolean; doneDate: string | null; des: string | null }

const loading = ref(false)
const reportSaving = ref(false)
const dealSaving = ref(false)
const tab = ref('deals')
const center = ref<Center | null>(null)
const deals = ref<Deal[]>([])
const reports = ref<Report[]>([])
const plans = ref<Plan[]>([])
const reportDialog = ref(false)
const dealDialogOpen = ref(false)

const reportForm = ref({ id: null as number | null, date: '', result: 'follow_up', des: '', nextAction: '', nextDate: '', dealTitle: '', dealId: null as number | null })
const dealForm = ref({ id: null as number | null, title: '', amount: '', dueDate: '', stage: 'planning', des: '' })

const stages = [
  { value: 'planning',    label: 'برنامه‌ریزی',   color: 'grey' },
  { value: 'visited',     label: 'بازدید شد',     color: 'blue' },
  { value: 'pre_invoice', label: 'پیش‌فاکتور',    color: 'indigo' },
  { value: 'approved',    label: 'تأیید مدیر',    color: 'purple' },
  { value: 'dispatched',  label: 'حواله انبار',   color: 'orange' },
  { value: 'invoiced',    label: 'فاکتور صادر',   color: 'teal' },
  { value: 'collected',   label: 'وصول شده',      color: 'success' },
]

const resultOptions = [
  { value: 'interested',     label: 'علاقه‌مند' },
  { value: 'not_interested', label: 'علاقه‌ای ندارد' },
  { value: 'follow_up',      label: 'پیگیری لازم' },
  { value: 'deal',           label: 'معامله / فرصت' },
]

function apiHeaders() {
  return { activeBid: appStore.activeBid, activeYear: appStore.activeYear, activeMoney: appStore.activeMoney }
}

function stageLabel(s: string) { return stages.find(x => x.value === s)?.label ?? s }
function stageColor(s: string) { return stages.find(x => x.value === s)?.color ?? 'default' }
function isDuePast(date: string) { return !!date && date < new Date().toISOString().slice(0, 10).replace(/-/g, '/') }
function centerTypeLabel(t: string | null) { return ({ customer: 'مشتری', prospect: 'بالقوه', lead: 'سرنخ' } as Record<string, string>)[t ?? ''] ?? t ?? '' }
function resultLabel(r: string) { return resultOptions.find(x => x.value === r)?.label ?? r }
function resultColor(r: string) { return ({ interested: 'success', not_interested: 'error', follow_up: 'warning', deal: 'primary' } as Record<string, string>)[r] ?? 'default' }

function nextStages(current: string) {
  const idx = stages.findIndex(s => s.value === current)
  return idx >= 0 && idx < stages.length - 1 ? [stages[idx + 1]] : []
}

const timelineItems = computed(() => {
  const items: { _key: string; date: string; color: string; type: string; text: string; sub?: string }[] = []
  deals.value.forEach(d => items.push({ _key: `deal-${d.id}`, date: d.createdAt, color: stageColor(d.stage), type: 'معامله', text: d.title, sub: stageLabel(d.stage) }))
  reports.value.forEach(r => items.push({ _key: `rep-${r.id}`, date: r.date, color: resultColor(r.result), type: 'گزارش', text: r.des?.substring(0, 60) ?? resultLabel(r.result), sub: r.nextAction ?? undefined }))
  plans.value.forEach(p => items.push({ _key: `plan-${p.id}`, date: p.scheduledDate, color: p.done ? 'success' : 'orange', type: 'برنامه', text: p.actionType === 'visit' ? 'ویزیت حضوری' : 'تماس', sub: p.des ?? undefined }))
  return items.sort((a, b) => b.date.localeCompare(a.date))
})

async function load() {
  loading.value = true
  try {
    const res = await axios.get(`/api/acc/salescenter/timeline/${centerId.value}`, { headers: apiHeaders() })
    center.value = res.data.center
    deals.value = res.data.deals
    reports.value = res.data.reports
    plans.value = res.data.plans
  } finally {
    loading.value = false
  }
}

function openReportDialog(report: Report | null) {
  if (report) {
    reportForm.value = { id: report.id, date: report.date, result: report.result, des: report.des ?? '', nextAction: report.nextAction ?? '', nextDate: report.nextDate ?? '', dealTitle: '', dealId: report.deal?.id ?? null }
  } else {
    reportForm.value = { id: null, date: '', result: 'follow_up', des: '', nextAction: '', nextDate: '', dealTitle: '', dealId: null }
  }
  reportDialog.value = true
}

async function saveReport() {
  reportSaving.value = true
  try {
    const payload = { ...reportForm.value, centerId: centerId.value }
    const res = await axios.post('/api/acc/visitreport/mod', payload, { headers: apiHeaders() })
    if (res.data.result === 1) {
      reportDialog.value = false
      await load()
    }
  } finally {
    reportSaving.value = false
  }
}

async function deleteReport(r: Report) {
  const res = await Swal.fire({ title: 'حذف گزارش', icon: 'warning', showCancelButton: true, confirmButtonText: 'حذف', cancelButtonText: 'انصراف', confirmButtonColor: '#d32f2f' })
  if (!res.isConfirmed) return
  await axios.post(`/api/acc/visitreport/del/${r.id}`, {}, { headers: apiHeaders() })
  await load()
}

function openDealDialog(deal: Deal | null) {
  if (deal) {
    dealForm.value = { id: deal.id, title: deal.title, amount: deal.amount ?? '', dueDate: deal.dueDate ?? '', stage: deal.stage, des: deal.des ?? '' }
  } else {
    dealForm.value = { id: null, title: '', amount: '', dueDate: '', stage: 'planning', des: '' }
  }
  dealDialogOpen.value = true
}

async function saveDeal() {
  if (!dealForm.value.title) return
  dealSaving.value = true
  try {
    const payload = { ...dealForm.value, centerId: centerId.value }
    await axios.post('/api/acc/salesdeal/mod', payload, { headers: apiHeaders() })
    dealDialogOpen.value = false
    await load()
  } finally {
    dealSaving.value = false
  }
}

async function advanceStage(deal: Deal, stage: string) {
  await axios.post(`/api/acc/salesdeal/stage/${deal.id}`, { stage }, { headers: apiHeaders() })
  await load()
}

async function approveDeal(deal: Deal) {
  await axios.post(`/api/acc/salesdeal/approve/${deal.id}`, {}, { headers: apiHeaders() })
  await load()
}

onMounted(load)
</script>
