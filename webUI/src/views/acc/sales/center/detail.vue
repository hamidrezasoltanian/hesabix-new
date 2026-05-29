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
    <v-btn icon="mdi-pencil" variant="text" class="ms-1" @click="editCenterDialog = true" />
  </v-toolbar>

  <v-container fluid class="pa-2">
    <!-- overdue banner -->
    <v-alert v-if="center?.overdue" type="warning" variant="tonal" density="compact" class="mb-2" closable>
      پیگیری سررسید گذشته است — تاریخ: {{ center.followupDate }}
    </v-alert>

    <!-- info chips -->
    <div v-if="center" class="d-flex flex-wrap gap-2 mb-3">
      <v-chip size="small" :color="crmStatusColor(center.crmStatus)" variant="tonal"
        @click="editCenterDialog = true" style="cursor:pointer">
        {{ crmStatusLabel(center.crmStatus) }}
      </v-chip>
      <v-chip v-if="center.lead" size="small" variant="tonal" color="blue">{{ leadLabel(center.lead) }}</v-chip>
      <v-chip v-if="center.potential" size="small" color="amber">
        {{ '★'.repeat(center.potential) + '☆'.repeat(4 - center.potential) }}
      </v-chip>
      <v-chip v-if="center.tel" size="small" prepend-icon="mdi-phone">{{ center.tel }}</v-chip>
      <v-chip v-if="center.followupDate" size="small" prepend-icon="mdi-calendar-clock"
        :color="center.overdue ? 'error' : 'default'">پیگیری: {{ center.followupDate }}</v-chip>
      <v-chip v-if="center.person" size="small" prepend-icon="mdi-account" color="primary" variant="tonal"
        @click="$router.push('/acc/persons/card?id=' + center.person.id)" style="cursor:pointer">
        {{ center.person.name }}
      </v-chip>
      <v-chip
        v-for="tag in (center.tags ?? [])" :key="tag.id" size="small"
        :style="{ backgroundColor: tag.color + '22', color: tag.color, borderColor: tag.color }"
        variant="outlined">{{ tag.name }}</v-chip>
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
      <v-tab value="activities">
        <v-badge :content="activities.length" color="teal" v-if="activities.length">فعالیت‌ها</v-badge>
        <span v-else>فعالیت‌ها</span>
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

      <!-- ════ Activities ════ -->
      <v-window-item value="activities">
        <div class="d-flex justify-end mb-3">
          <v-btn color="primary" prepend-icon="mdi-plus" size="small" @click="openActivityDialog">ثبت فعالیت</v-btn>
        </div>
        <v-card v-if="activities.length === 0" variant="outlined" class="pa-6 text-center text-disabled">
          هیچ فعالیتی ثبت نشده است
        </v-card>
        <v-list>
          <v-list-item v-for="a in activities" :key="a.id"
            :prepend-icon="activityIcon(a.type)" :subtitle="a.date">
            <template v-slot:title>
              <v-chip size="x-small" :color="activityColor(a.type)" variant="tonal" class="me-2">{{ activityLabel(a.type) }}</v-chip>
              <span v-if="a.type === 'sale' && a.amount">{{ Number(a.amount).toLocaleString('fa') }} ریال</span>
              <v-chip v-if="a.type === 'sale' && a.cashSale" size="x-small" color="green" variant="tonal" class="ms-1">نقدی</v-chip>
              <v-chip v-if="a.type === 'mission'" size="x-small" :color="a.done ? 'success' : 'warning'" variant="tonal" class="ms-1">
                {{ a.done ? 'انجام شد' : 'در دست اقدام' }}
              </v-chip>
            </template>
            <template v-slot:append>
              <v-btn icon="mdi-delete" size="x-small" variant="text" color="error" @click="deleteActivity(a)" />
            </template>
            <div v-if="a.note" class="text-caption text-medium-emphasis">{{ a.note }}</div>
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

  <!-- ════ Edit Center Dialog ════ -->
  <v-dialog v-model="editCenterDialog" max-width="600" persistent>
    <v-card>
      <v-card-title class="pa-4">ویرایش اطلاعات مرکز</v-card-title>
      <v-divider />
      <v-card-text class="pa-4">
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-select v-model="centerForm.crmStatus" :items="crmStatusOptions" item-title="label" item-value="value"
              label="وضعیت CRM" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-select v-model="centerForm.lead" :items="leadOptions" item-title="label" item-value="value"
              label="طبقه‌بندی لید" variant="outlined" density="compact" clearable />
          </v-col>
          <v-col cols="12" sm="6">
            <v-select v-model="centerForm.potential"
              :items="[{v:1,l:'★★★★ عالی'},{v:2,l:'★★★☆ خوب'},{v:3,l:'★★☆☆ متوسط'},{v:4,l:'★☆☆☆ پایین'}]"
              item-title="l" item-value="v" label="پتانسیل" variant="outlined" density="compact" clearable />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="centerForm.followupDate" label="تاریخ پیگیری بعدی"
              variant="outlined" density="compact" placeholder="1403/01/20" clearable />
          </v-col>
          <v-col cols="12">
            <v-combobox v-model="centerForm.selectedTags" :items="availableTags" item-title="name" item-value="id"
              label="تگ‌ها" variant="outlined" density="compact" multiple chips closable-chips return-object />
          </v-col>
        </v-row>
      </v-card-text>
      <v-divider />
      <v-card-actions class="pa-3">
        <v-btn variant="text" @click="editCenterDialog = false">انصراف</v-btn>
        <v-spacer />
        <v-btn color="primary" variant="flat" :loading="centerSaving" @click="saveCenter">ذخیره</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- ════ Activity Dialog ════ -->
  <v-dialog v-model="activityDialog" max-width="480" persistent>
    <v-card>
      <v-card-title class="pa-4">ثبت فعالیت</v-card-title>
      <v-divider />
      <v-card-text class="pa-4">
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-select v-model="activityForm.type" :items="activityTypeOptions" item-title="label" item-value="value"
              label="نوع فعالیت *" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="activityForm.date" label="تاریخ *" variant="outlined" density="compact" placeholder="1403/01/15" />
          </v-col>
          <v-col cols="12" v-if="activityForm.type === 'sale'">
            <v-text-field v-model="activityForm.amount" label="مبلغ فروش (ریال)" variant="outlined" density="compact" type="number" />
          </v-col>
          <v-col cols="6" v-if="activityForm.type === 'sale'">
            <v-switch v-model="activityForm.cashSale" label="فروش نقدی" color="success" density="compact" />
          </v-col>
          <v-col cols="6" v-if="activityForm.type === 'mission'">
            <v-switch v-model="activityForm.done" label="انجام شد" color="success" density="compact" />
          </v-col>
          <v-col cols="12">
            <v-textarea v-model="activityForm.note" label="توضیحات" variant="outlined" density="compact" rows="2" auto-grow />
          </v-col>
        </v-row>
      </v-card-text>
      <v-divider />
      <v-card-actions class="pa-3">
        <v-btn variant="text" @click="activityDialog = false">انصراف</v-btn>
        <v-spacer />
        <v-btn color="primary" variant="flat" :loading="activitySaving" @click="saveActivity">ذخیره</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

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
import { useRoute } from 'vue-router'
import axios from 'axios'
import Swal from 'sweetalert2'
import { useApplicationStore } from '../../../../stores/applicationStore'

const appStore = useApplicationStore()
const route = useRoute()
const centerId = computed(() => Number(route.params.id ?? route.query.id))

interface Tag { id: number; name: string; color: string }
interface Center { id: number; name: string; type: string | null; potential: number | null; lead: string | null; crmStatus: string; followupDate: string | null; overdue: boolean; tel: string | null; city: string | null; person: { id: number; name: string } | null; tags: Tag[] }
interface Deal { id: number; title: string; stage: string; amount: string | null; dueDate: string | null; des: string | null; preInvoiceId: number | null; storeroomTicketId: number | null; sellDocId: number | null; approvedAt: string | null; createdAt: string }
interface Report { id: number; date: string; result: string; des: string | null; nextAction: string | null; nextDate: string | null; deal: { id: number; title: string } | null; submitter: { id: number; mobile: string } | null }
interface Plan { id: number; scheduledDate: string; actionType: string; done: boolean; doneDate: string | null; des: string | null }
interface Activity { id: number; type: string; date: string; amount: string | null; cashSale: boolean; done: boolean; note: string | null; user: { id: number; mobile: string } }

const loading = ref(false)
const reportSaving = ref(false)
const dealSaving = ref(false)
const centerSaving = ref(false)
const activitySaving = ref(false)
const tab = ref('deals')
const center = ref<Center | null>(null)
const deals = ref<Deal[]>([])
const reports = ref<Report[]>([])
const plans = ref<Plan[]>([])
const activities = ref<Activity[]>([])
const availableTags = ref<Tag[]>([])

const reportDialog = ref(false)
const dealDialogOpen = ref(false)
const editCenterDialog = ref(false)
const activityDialog = ref(false)

const reportForm = ref({ id: null as number | null, date: '', result: 'follow_up', des: '', nextAction: '', nextDate: '', dealTitle: '', dealId: null as number | null })
const dealForm = ref({ id: null as number | null, title: '', amount: '', dueDate: '', stage: 'planning', des: '' })
const centerForm = ref({ crmStatus: 'no_contact', lead: null as string | null, potential: null as number | null, followupDate: null as string | null, selectedTags: [] as Tag[] })
const activityForm = ref({ type: 'call', date: '', amount: '', cashSale: false, done: false, note: '' })

const stages = [
  { value: 'planning',    label: 'برنامه‌ریزی', color: 'grey' },
  { value: 'visited',     label: 'بازدید شد',   color: 'blue' },
  { value: 'pre_invoice', label: 'پیش‌فاکتور',  color: 'indigo' },
  { value: 'approved',    label: 'تأیید مدیر',  color: 'purple' },
  { value: 'dispatched',  label: 'حواله انبار', color: 'orange' },
  { value: 'invoiced',    label: 'فاکتور صادر', color: 'teal' },
  { value: 'collected',   label: 'وصول شده',    color: 'success' },
]
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
const resultOptions = [
  { value: 'interested',     label: 'علاقه‌مند' },
  { value: 'not_interested', label: 'علاقه‌ای ندارد' },
  { value: 'follow_up',      label: 'پیگیری لازم' },
  { value: 'deal',           label: 'معامله / فرصت' },
]
const activityTypeOptions = [
  { value: 'call',    label: 'تماس تلفنی' },
  { value: 'visit',   label: 'ویزیت حضوری' },
  { value: 'sale',    label: 'فروش / قرارداد' },
  { value: 'mission', label: 'ماموریت' },
]

function apiHeaders() {
  return { activeBid: appStore.activeBid, activeYear: appStore.activeYear, activeMoney: appStore.activeMoney }
}
function stageLabel(s: string) { return stages.find(x => x.value === s)?.label ?? s }
function stageColor(s: string) { return stages.find(x => x.value === s)?.color ?? 'default' }
function crmStatusLabel(s: string) { return crmStatusOptions.find(x => x.value === s)?.label ?? s }
function crmStatusColor(s: string) { return crmStatusOptions.find(x => x.value === s)?.color ?? 'default' }
function leadLabel(l: string) { return leadOptions.find(x => x.value === l)?.label ?? l }
function resultLabel(r: string) { return resultOptions.find(x => x.value === r)?.label ?? r }
function resultColor(r: string) { return ({ interested: 'success', not_interested: 'error', follow_up: 'warning', deal: 'primary' } as Record<string, string>)[r] ?? 'default' }
function isDuePast(date: string) { return !!date && date < new Date().toISOString().slice(0, 10) }
function nextStages(cur: string) { const i = stages.findIndex(s => s.value === cur); return i >= 0 && i < stages.length - 1 ? [stages[i + 1]] : [] }
function actionTypeLabel(t: string) { return ({ call: 'تماس', visit: 'ویزیت', sale: 'فروش', mission: 'ماموریت' } as Record<string, string>)[t] ?? t }
function activityIcon(t: string) { return ({ call: 'mdi-phone', visit: 'mdi-map-marker', sale: 'mdi-handshake', mission: 'mdi-briefcase-outline' } as Record<string, string>)[t] ?? 'mdi-circle' }
function activityLabel(t: string) { return activityTypeOptions.find(x => x.value === t)?.label ?? t }
function activityColor(t: string) { return ({ call: 'blue', visit: 'indigo', sale: 'success', mission: 'orange' } as Record<string, string>)[t] ?? 'default' }

const timelineItems = computed(() => {
  const items: { _key: string; date: string; color: string; type: string; text: string; sub?: string }[] = []
  deals.value.forEach(d => items.push({ _key: `deal-${d.id}`, date: d.createdAt, color: stageColor(d.stage), type: 'معامله', text: d.title, sub: stageLabel(d.stage) }))
  reports.value.forEach(r => items.push({ _key: `rep-${r.id}`, date: r.date, color: resultColor(r.result), type: 'گزارش', text: r.des?.substring(0, 60) ?? resultLabel(r.result), sub: r.nextAction ?? undefined }))
  plans.value.forEach(p => items.push({ _key: `plan-${p.id}`, date: p.scheduledDate, color: p.done ? 'success' : 'orange', type: 'برنامه', text: actionTypeLabel(p.actionType), sub: p.des ?? undefined }))
  activities.value.forEach(a => items.push({ _key: `act-${a.id}`, date: a.date, color: activityColor(a.type), type: 'فعالیت', text: activityLabel(a.type), sub: a.note ?? undefined }))
  return items.sort((a, b) => b.date.localeCompare(a.date))
})

async function load() {
  loading.value = true
  try {
    const [timelineRes, actRes, tagsRes] = await Promise.all([
      axios.get(`/api/acc/salescenter/timeline/${centerId.value}`, { headers: apiHeaders() }),
      axios.post('/api/acc/activitylog/list', { centerId: centerId.value }, { headers: apiHeaders() }),
      axios.get('/api/acc/salescentertag/list', { headers: apiHeaders() }),
    ])
    center.value = timelineRes.data.center
    deals.value = timelineRes.data.deals
    reports.value = timelineRes.data.reports
    plans.value = timelineRes.data.plans
    activities.value = actRes.data
    availableTags.value = tagsRes.data
    if (center.value) {
      centerForm.value = {
        crmStatus: center.value.crmStatus ?? 'no_contact',
        lead: center.value.lead,
        potential: center.value.potential,
        followupDate: center.value.followupDate,
        selectedTags: center.value.tags ?? [],
      }
    }
  } finally { loading.value = false }
}

function openReportDialog(report: Report | null) {
  reportForm.value = report
    ? { id: report.id, date: report.date, result: report.result, des: report.des ?? '', nextAction: report.nextAction ?? '', nextDate: report.nextDate ?? '', dealTitle: '', dealId: report.deal?.id ?? null }
    : { id: null, date: '', result: 'follow_up', des: '', nextAction: '', nextDate: '', dealTitle: '', dealId: null }
  reportDialog.value = true
}

async function saveReport() {
  reportSaving.value = true
  try {
    const res = await axios.post('/api/acc/visitreport/mod', { ...reportForm.value, centerId: centerId.value }, { headers: apiHeaders() })
    if (res.data.result === 1) { reportDialog.value = false; await load() }
  } finally { reportSaving.value = false }
}

async function deleteReport(r: Report) {
  const c = await Swal.fire({ title: 'حذف گزارش', icon: 'warning', showCancelButton: true, confirmButtonText: 'حذف', cancelButtonText: 'انصراف', confirmButtonColor: '#d32f2f' })
  if (!c.isConfirmed) return
  await axios.post(`/api/acc/visitreport/del/${r.id}`, {}, { headers: apiHeaders() })
  await load()
}

function openDealDialog(deal: Deal | null) {
  dealForm.value = deal
    ? { id: deal.id, title: deal.title, amount: deal.amount ?? '', dueDate: deal.dueDate ?? '', stage: deal.stage, des: deal.des ?? '' }
    : { id: null, title: '', amount: '', dueDate: '', stage: 'planning', des: '' }
  dealDialogOpen.value = true
}

async function saveDeal() {
  if (!dealForm.value.title) return
  dealSaving.value = true
  try {
    await axios.post('/api/acc/salesdeal/mod', { ...dealForm.value, centerId: centerId.value }, { headers: apiHeaders() })
    dealDialogOpen.value = false; await load()
  } finally { dealSaving.value = false }
}

async function advanceStage(deal: Deal, stage: string) {
  await axios.post(`/api/acc/salesdeal/stage/${deal.id}`, { stage }, { headers: apiHeaders() })
  await load()
}

async function approveDeal(deal: Deal) {
  await axios.post(`/api/acc/salesdeal/approve/${deal.id}`, {}, { headers: apiHeaders() })
  await load()
}

async function saveCenter() {
  centerSaving.value = true
  try {
    const tagIds = centerForm.value.selectedTags.map((t: any) => typeof t === 'object' ? t.id : t)
    await axios.post('/api/acc/salescenter/mod', {
      id: centerId.value, name: center.value?.name,
      crmStatus: centerForm.value.crmStatus, lead: centerForm.value.lead,
      potential: centerForm.value.potential, followupDate: centerForm.value.followupDate, tagIds,
    }, { headers: apiHeaders() })
    editCenterDialog.value = false; await load()
  } finally { centerSaving.value = false }
}

function openActivityDialog() {
  activityForm.value = { type: 'call', date: '', amount: '', cashSale: false, done: false, note: '' }
  activityDialog.value = true
}

async function saveActivity() {
  if (!activityForm.value.type || !activityForm.value.date) return
  activitySaving.value = true
  try {
    await axios.post('/api/acc/activitylog/add', { ...activityForm.value, centerId: centerId.value }, { headers: apiHeaders() })
    activityDialog.value = false; await load()
  } finally { activitySaving.value = false }
}

async function deleteActivity(a: Activity) {
  const c = await Swal.fire({ title: 'حذف فعالیت', icon: 'warning', showCancelButton: true, confirmButtonText: 'حذف', cancelButtonText: 'انصراف', confirmButtonColor: '#d32f2f' })
  if (!c.isConfirmed) return
  await axios.post(`/api/acc/activitylog/del/${a.id}`, {}, { headers: apiHeaders() })
  await load()
}

onMounted(load)
</script>
