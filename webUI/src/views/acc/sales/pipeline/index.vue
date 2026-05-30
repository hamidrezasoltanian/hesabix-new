<template>
  <v-toolbar color="toolbar" title="Pipeline فروش">
    <template v-slot:prepend>
      <v-btn @click="$router.back()" variant="text" icon="mdi-arrow-right" />
    </template>
    <v-spacer />
    <!-- Priority filter -->
    <v-chip-group v-model="filterPriority" class="me-2" selected-class="text-primary">
      <v-chip value="" size="small">همه</v-chip>
      <v-chip value="high" size="small" color="error">بالا</v-chip>
      <v-chip value="medium" size="small" color="warning">متوسط</v-chip>
      <v-chip value="low" size="small" color="success">کم</v-chip>
    </v-chip-group>
    <v-btn-toggle v-model="viewMode" mandatory density="compact" class="me-2">
      <v-btn value="kanban" icon="mdi-view-column-outline" />
      <v-btn value="list" icon="mdi-view-list-outline" />
      <v-btn value="collections" icon="mdi-cash-clock" />
    </v-btn-toggle>
    <v-btn color="primary" prepend-icon="mdi-plus" @click="openDealDialog(null)">فرصت جدید</v-btn>
  </v-toolbar>

  <!-- Stats bar -->
  <div class="stats-bar px-4 py-2 d-flex gap-4 flex-wrap align-center">
    <div class="stat-item">
      <span class="text-caption text-medium-emphasis">فرصت‌های فعال</span>
      <span class="text-body-2 font-weight-bold ms-1">{{ activeDeals.length }}</span>
    </div>
    <v-divider vertical />
    <div class="stat-item">
      <span class="text-caption text-medium-emphasis">ارزش کل پایپ‌لاین</span>
      <span class="text-body-2 font-weight-bold ms-1">{{ totalPipelineValue.toLocaleString('fa') }} ریال</span>
    </div>
    <v-divider vertical />
    <div class="stat-item">
      <span class="text-caption text-medium-emphasis">وصول شده</span>
      <span class="text-body-2 font-weight-bold ms-1 text-success">{{ collectedDeals.length }}</span>
    </div>
    <v-divider vertical />
    <div class="stat-item">
      <span class="text-caption text-medium-emphasis">اولویت بالا</span>
      <span class="text-body-2 font-weight-bold ms-1 text-error">{{ highPriorityCount }}</span>
    </div>
  </div>

  <v-container fluid class="pa-2">

    <!-- ════ Kanban ════ -->
    <div v-if="viewMode === 'kanban'" class="kanban-wrap">
      <div class="kanban-board">
        <div
          v-for="stage in stages"
          :key="stage.value"
          class="kanban-col"
          :class="{ 'drag-over': dragOverStage === stage.value }"
          @dragover.prevent="dragOverStage = stage.value"
          @dragleave.self="dragOverStage = null"
          @drop.prevent="onDrop(stage.value)"
        >
          <div class="kanban-col-header" :style="{ borderTopColor: stage.color }">
            <span class="text-body-2 font-weight-bold">{{ stage.label }}</span>
            <v-chip size="x-small" :color="stage.color" class="ms-1">
              {{ filteredDealsByStage(stage.value).length }}
            </v-chip>
            <v-spacer />
            <span v-if="stageTotal(stage.value)" class="text-caption text-medium-emphasis">
              {{ stageTotal(stage.value).toLocaleString('fa') }}
            </span>
          </div>
          <div v-if="loading" class="d-flex justify-center pa-4">
            <v-progress-circular indeterminate size="24" />
          </div>
          <div v-else class="kanban-col-body">
            <div
              v-if="filteredDealsByStage(stage.value).length === 0"
              class="text-caption text-center text-disabled pa-4"
            >خالی</div>
            <v-card
              v-for="deal in filteredDealsByStage(stage.value)"
              :key="deal.id"
              class="kanban-card mb-2"
              :class="priorityCardClass(deal.priority)"
              variant="outlined"
              draggable="true"
              @dragstart="startDrag(deal)"
              @dragend="dragOverStage = null"
              @click="openDealDialog(deal)"
            >
              <v-card-text class="pa-2">
                <div class="d-flex align-start justify-space-between">
                  <span class="text-body-2 font-weight-medium">{{ deal.title }}</span>
                  <v-icon v-if="deal.priority === 'high'" size="14" color="error" class="ms-1 mt-1">mdi-flag</v-icon>
                  <v-icon v-else-if="deal.priority === 'medium'" size="14" color="warning" class="ms-1 mt-1">mdi-flag</v-icon>
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
                <div class="d-flex mt-1 gap-1 flex-wrap align-center">
                  <v-chip v-if="deal.preInvoiceId" size="x-small" color="blue" variant="tonal">پ.فاکتور</v-chip>
                  <v-chip v-if="deal.storeroomTicketId" size="x-small" color="orange" variant="tonal">حواله</v-chip>
                  <v-chip v-if="deal.sellDocId" size="x-small" color="green" variant="tonal">فاکتور</v-chip>
                  <v-avatar v-if="deal.owner" size="18" color="primary" class="ms-auto text-white" style="font-size:9px">
                    {{ deal.owner.mobile.slice(-2) }}
                  </v-avatar>
                </div>
              </v-card-text>
            </v-card>
            <v-btn
              variant="text"
              size="small"
              prepend-icon="mdi-plus"
              class="w-100 mt-1"
              @click.stop="openDealDialogForStage(stage.value)"
            >فرصت جدید</v-btn>
          </div>
        </div>
      </div>
    </div>

    <!-- ════ List ════ -->
    <div v-if="viewMode === 'list'">
      <v-row dense class="mb-2">
        <v-col cols="12" sm="3">
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
        <v-col cols="12" sm="3">
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
        <template v-slot:item.priority="{ item }">
          <v-icon v-if="item.priority === 'high'" color="error" size="16">mdi-flag</v-icon>
          <v-icon v-else-if="item.priority === 'medium'" color="warning" size="16">mdi-flag</v-icon>
          <v-icon v-else-if="item.priority === 'low'" color="success" size="16">mdi-flag-outline</v-icon>
          <span v-else class="text-disabled">—</span>
        </template>
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
  <v-dialog v-model="dealDialog" max-width="700" persistent scrollable>
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

      <v-tabs v-model="dialogTab" density="compact" class="px-4">
        <v-tab value="info">اطلاعات</v-tab>
        <v-tab value="tasks" :disabled="!editDeal?.id">
          وظایف
          <v-badge v-if="tasks.length" :content="tasks.length" color="primary" inline />
        </v-tab>
        <v-tab value="activity" :disabled="!editDeal?.id">فعالیت</v-tab>
        <v-tab value="docs" :disabled="!editDeal?.id">اسناد</v-tab>
      </v-tabs>
      <v-divider />

      <v-card-text class="pa-4">
        <!-- ── Tab: اطلاعات ── -->
        <v-window v-model="dialogTab">
          <v-window-item value="info">
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
              <v-col cols="12" sm="4">
                <v-text-field v-model="form.amount" label="مبلغ (ریال)" variant="outlined" density="compact" type="number" />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field v-model="form.dueDate" label="سررسید" variant="outlined" density="compact" placeholder="1403/01/15" />
              </v-col>
              <v-col cols="12" sm="4">
                <v-select
                  v-model="form.priority"
                  :items="priorityItems"
                  item-title="label"
                  item-value="value"
                  label="اولویت"
                  variant="outlined"
                  density="compact"
                  clearable
                />
              </v-col>
              <v-col cols="12">
                <v-textarea v-model="form.des" label="توضیحات" variant="outlined" density="compact" rows="2" auto-grow />
              </v-col>
            </v-row>

            <!-- Stage advance buttons (edit only) -->
            <template v-if="editDeal?.id">
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
              <v-alert v-if="editDeal.approvedAt" type="success" variant="tonal" density="compact" class="mt-3">
                تأیید شده توسط {{ editDeal.approvedBy?.mobile }} در {{ editDeal.approvedAt }}
              </v-alert>
            </template>
          </v-window-item>

          <!-- ── Tab: وظایف ── -->
          <v-window-item value="tasks">
            <div class="d-flex gap-2 mb-3">
              <v-text-field
                v-model="newTaskTitle"
                label="وظیفه جدید..."
                variant="outlined"
                density="compact"
                hide-details
                @keydown.enter="addTask"
                class="flex-grow-1"
              />
              <v-btn color="primary" variant="flat" :loading="addingTask" @click="addTask">افزودن</v-btn>
            </div>

            <!-- Progress bar -->
            <div v-if="tasks.length" class="mb-3">
              <div class="d-flex justify-space-between text-caption text-medium-emphasis mb-1">
                <span>پیشرفت</span>
                <span>{{ doneTasks }}/{{ tasks.length }}</span>
              </div>
              <v-progress-linear
                :model-value="tasks.length ? (doneTasks / tasks.length) * 100 : 0"
                color="success"
                rounded
                height="6"
              />
            </div>

            <div v-if="tasksLoading" class="d-flex justify-center pa-4">
              <v-progress-circular indeterminate size="24" />
            </div>
            <div v-else>
              <div
                v-for="task in tasks"
                :key="task.id"
                class="d-flex align-center gap-2 py-1 task-row"
              >
                <v-checkbox
                  :model-value="task.done"
                  hide-details
                  density="compact"
                  @update:model-value="toggleTask(task)"
                />
                <span :class="task.done ? 'text-decoration-line-through text-medium-emphasis' : ''">
                  {{ task.title }}
                </span>
                <span v-if="task.doneAt" class="text-caption text-medium-emphasis ms-1">({{ task.doneAt }})</span>
                <v-spacer />
                <v-btn icon="mdi-delete" size="x-small" variant="text" color="error" @click="deleteTask(task)" />
              </div>
              <div v-if="!tasks.length" class="text-center text-caption text-disabled pa-4">
                وظیفه‌ای تعریف نشده است
              </div>
            </div>
          </v-window-item>

          <!-- ── Tab: فعالیت ── -->
          <v-window-item value="activity">
            <!-- Add comment -->
            <div class="d-flex gap-2 mb-4">
              <v-textarea
                v-model="newComment"
                label="یادداشت یا نظر..."
                variant="outlined"
                density="compact"
                rows="2"
                hide-details
                auto-grow
                class="flex-grow-1"
              />
              <v-btn color="primary" variant="flat" :loading="addingComment" @click="addComment" class="align-self-end">ثبت</v-btn>
            </div>

            <div v-if="activitiesLoading" class="d-flex justify-center pa-4">
              <v-progress-circular indeterminate size="24" />
            </div>
            <v-timeline v-else-if="activities.length" density="compact" side="end" truncate-line="both">
              <v-timeline-item
                v-for="a in activities"
                :key="a.id"
                :dot-color="activityColor(a.type)"
                size="x-small"
              >
                <template v-slot:opposite>
                  <span class="text-caption text-medium-emphasis">{{ a.date }}</span>
                </template>
                <div>
                  <div class="text-caption text-medium-emphasis mb-1">{{ a.user.mobile }}</div>
                  <div v-if="a.type === 'status_change'" class="text-caption">
                    <v-icon size="12">mdi-swap-horizontal</v-icon>
                    {{ stageLabel(a.content.split(' → ')[0]) }} ← {{ stageLabel(a.content.split(' → ')[1]) }}
                  </div>
                  <div v-else class="text-body-2">{{ a.content }}</div>
                </div>
              </v-timeline-item>
            </v-timeline>
            <div v-else class="text-center text-caption text-disabled pa-4">
              فعالیتی ثبت نشده است
            </div>
          </v-window-item>

          <!-- ── Tab: اسناد ── -->
          <v-window-item value="docs">
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
          </v-window-item>
        </v-window>
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
  priority: string | null
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

interface Task {
  id: number
  title: string
  done: boolean
  doneAt: string | null
  displayOrder: number
  createdAt: string
  doneBy: { id: number; mobile: string } | null
}

interface Activity {
  id: number
  type: string
  content: string
  date: string
  user: { id: number; mobile: string }
}

interface Center { id: number; name: string; person?: { id: number; name: string } | null }

const loading = ref(false)
const saving = ref(false)
const approving = ref(false)
const collectionsLoading = ref(false)

const viewMode = ref<'kanban' | 'list' | 'collections'>('kanban')
const filterStage = ref('')
const filterPriority = ref('')
const search = ref('')
const deals = ref<Deal[]>([])
const centers = ref<Center[]>([])
const collections = ref<Record<string, Deal[]>>({})

// Drag & drop
const draggingDeal = ref<Deal | null>(null)
const dragOverStage = ref<string | null>(null)

// Dialog state
const dealDialog = ref(false)
const dialogTab = ref('info')
const editDeal = ref<Deal | null>(null)
const form = ref({
  id: null as number | null,
  title: '',
  centerId: null as number | null,
  stage: 'planning',
  priority: null as string | null,
  amount: '' as string | number,
  dueDate: '',
  des: '',
  preInvoiceId: null as number | null,
  storeroomTicketId: null as number | null,
  sellDocId: null as number | null,
})

// Tasks
const tasks = ref<Task[]>([])
const tasksLoading = ref(false)
const newTaskTitle = ref('')
const addingTask = ref(false)

// Activity
const activities = ref<Activity[]>([])
const activitiesLoading = ref(false)
const newComment = ref('')
const addingComment = ref(false)

const stages = [
  { value: 'planning',    label: 'برنامه‌ریزی',   color: 'grey',    icon: 'mdi-calendar' },
  { value: 'visited',     label: 'بازدید شد',     color: 'blue',    icon: 'mdi-account-check' },
  { value: 'pre_invoice', label: 'پیش‌فاکتور',    color: 'indigo',  icon: 'mdi-file-document' },
  { value: 'approved',    label: 'تأیید مدیر',    color: 'purple',  icon: 'mdi-check-decagram' },
  { value: 'dispatched',  label: 'حواله انبار',   color: 'orange',  icon: 'mdi-warehouse' },
  { value: 'invoiced',    label: 'فاکتور صادر',   color: 'teal',    icon: 'mdi-receipt' },
  { value: 'collected',   label: 'وصول شده',      color: 'success', icon: 'mdi-cash-check' },
]

const priorityItems = [
  { value: 'high',   label: 'بالا' },
  { value: 'medium', label: 'متوسط' },
  { value: 'low',    label: 'کم' },
]

const listHeaders = [
  { title: 'عنوان', key: 'title' },
  { title: 'مرکز', key: 'center.name' },
  { title: 'مشتری', key: 'person.name' },
  { title: 'مرحله', key: 'stage' },
  { title: 'اولویت', key: 'priority', sortable: false },
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

// ─── Computed ────────────────────────────────────────────────────────────────

const activeDeals = computed(() => deals.value.filter(d => d.stage !== 'collected'))
const collectedDeals = computed(() => deals.value.filter(d => d.stage === 'collected'))
const totalPipelineValue = computed(() => activeDeals.value.reduce((s, d) => s + (d.amount ? Number(d.amount) : 0), 0))
const highPriorityCount = computed(() => deals.value.filter(d => d.priority === 'high').length)
const doneTasks = computed(() => tasks.value.filter(t => t.done).length)

function filteredDealsByStage(stage: string) {
  return deals.value.filter(d => {
    if (d.stage !== stage) return false
    if (filterPriority.value && d.priority !== filterPriority.value) return false
    return true
  })
}

function stageTotal(stage: string) {
  const total = filteredDealsByStage(stage).reduce((s, d) => s + (d.amount ? Number(d.amount) : 0), 0)
  return total || 0
}

const filteredDeals = computed(() => {
  let result = deals.value
  if (filterStage.value) result = result.filter(d => d.stage === filterStage.value)
  if (filterPriority.value) result = result.filter(d => d.priority === filterPriority.value)
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

// ─── Helpers ─────────────────────────────────────────────────────────────────

function stageLabel(s: string) { return stages.find(x => x.value === s)?.label ?? s }
function stageColor(s: string) { return stages.find(x => x.value === s)?.color ?? 'default' }
function isDuePast(date: string) { return date < new Date().toISOString().slice(0, 10).replace(/-/g, '/') }
function sumAmount(arr: Deal[]) { return arr.reduce((s, d) => s + (d.amount ? Number(d.amount) : 0), 0) }

function priorityCardClass(priority: string | null) {
  if (priority === 'high') return 'priority-high'
  if (priority === 'medium') return 'priority-medium'
  if (priority === 'low') return 'priority-low'
  return ''
}

function activityColor(type: string) {
  if (type === 'status_change') return 'primary'
  if (type === 'comment') return 'success'
  return 'grey'
}

function nextStages(current: string) {
  const idx = stages.findIndex(s => s.value === current)
  return idx < stages.length - 1 ? [stages[idx + 1]] : []
}

// ─── Drag & Drop ─────────────────────────────────────────────────────────────

function startDrag(deal: Deal) {
  draggingDeal.value = deal
}

async function onDrop(targetStage: string) {
  dragOverStage.value = null
  const deal = draggingDeal.value
  draggingDeal.value = null
  if (!deal || deal.stage === targetStage) return
  // Optimistic update
  deal.stage = targetStage
  await axios.post(`/api/acc/salesdeal/stage/${deal.id}`, { stage: targetStage }, { headers: apiHeaders() })
  await loadDeals()
}

// ─── Data Loading ─────────────────────────────────────────────────────────────

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

async function loadTasks(dealId: number) {
  tasksLoading.value = true
  try {
    const res = await axios.get(`/api/acc/dealtask/list/${dealId}`, { headers: apiHeaders() })
    tasks.value = res.data
  } finally {
    tasksLoading.value = false
  }
}

async function loadActivities(dealId: number) {
  activitiesLoading.value = true
  try {
    const res = await axios.get(`/api/acc/dealactivity/list/${dealId}`, { headers: apiHeaders() })
    activities.value = res.data
  } finally {
    activitiesLoading.value = false
  }
}

// ─── Deal CRUD ────────────────────────────────────────────────────────────────

function openDealDialog(deal: Deal | null) {
  editDeal.value = deal
  dialogTab.value = 'info'
  tasks.value = []
  activities.value = []
  newTaskTitle.value = ''
  newComment.value = ''

  if (deal) {
    form.value = {
      id: deal.id,
      title: deal.title,
      centerId: deal.center?.id ?? null,
      stage: deal.stage,
      priority: deal.priority,
      amount: deal.amount ?? '',
      dueDate: deal.dueDate ?? '',
      des: deal.des ?? '',
      preInvoiceId: deal.preInvoiceId,
      storeroomTicketId: deal.storeroomTicketId,
      sellDocId: deal.sellDocId,
    }
    loadTasks(deal.id)
    loadActivities(deal.id)
  } else {
    form.value = { id: null, title: '', centerId: null, stage: 'planning', priority: null, amount: '', dueDate: '', des: '', preInvoiceId: null, storeroomTicketId: null, sellDocId: null }
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

async function deleteDeal(deal: Deal | null) {
  if (!deal) return
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

// ─── Tasks ────────────────────────────────────────────────────────────────────

async function addTask() {
  if (!newTaskTitle.value.trim() || !editDeal.value) return
  addingTask.value = true
  try {
    await axios.post('/api/acc/dealtask/mod', { dealId: editDeal.value.id, title: newTaskTitle.value }, { headers: apiHeaders() })
    newTaskTitle.value = ''
    await loadTasks(editDeal.value.id)
  } finally {
    addingTask.value = false
  }
}

async function toggleTask(task: Task) {
  await axios.post(`/api/acc/dealtask/toggle/${task.id}`, {}, { headers: apiHeaders() })
  if (editDeal.value) await loadTasks(editDeal.value.id)
}

async function deleteTask(task: Task) {
  await axios.post(`/api/acc/dealtask/del/${task.id}`, {}, { headers: apiHeaders() })
  if (editDeal.value) await loadTasks(editDeal.value.id)
}

// ─── Activity ─────────────────────────────────────────────────────────────────

async function addComment() {
  if (!newComment.value.trim() || !editDeal.value) return
  addingComment.value = true
  try {
    await axios.post('/api/acc/dealactivity/add', { dealId: editDeal.value.id, content: newComment.value }, { headers: apiHeaders() })
    newComment.value = ''
    await loadActivities(editDeal.value.id)
  } finally {
    addingComment.value = false
  }
}

// ─── Navigation ───────────────────────────────────────────────────────────────

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

// ─── Lifecycle ────────────────────────────────────────────────────────────────

watch(viewMode, (v) => { if (v === 'collections') loadCollections() })

onMounted(() => {
  loadDeals()
  loadCenters()
})
</script>

<style scoped>
.stats-bar {
  background: rgba(var(--v-theme-surface-variant), 0.4);
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  min-height: 40px;
}
.stat-item {
  display: flex;
  align-items: center;
  white-space: nowrap;
}
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
  width: 240px;
  flex-shrink: 0;
  background: rgba(var(--v-theme-surface), 1);
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  transition: background 0.15s;
}
.kanban-col.drag-over {
  background: rgba(var(--v-theme-primary), 0.06);
  border-color: rgba(var(--v-theme-primary), 0.4);
}
.kanban-col-header {
  padding: 8px 12px;
  border-top: 3px solid;
  border-radius: 8px 8px 0 0;
  display: flex;
  align-items: center;
  gap: 4px;
}
.kanban-col-body {
  padding: 8px;
  flex: 1;
  overflow-y: auto;
  max-height: calc(100vh - 230px);
}
.kanban-card {
  cursor: grab;
  transition: box-shadow 0.15s, opacity 0.15s;
}
.kanban-card:hover {
  box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}
.kanban-card:active {
  cursor: grabbing;
  opacity: 0.7;
}
.priority-high {
  border-left: 3px solid rgb(var(--v-theme-error)) !important;
}
.priority-medium {
  border-left: 3px solid rgb(var(--v-theme-warning)) !important;
}
.priority-low {
  border-left: 3px solid rgb(var(--v-theme-success)) !important;
}
.task-row:hover {
  background: rgba(var(--v-theme-surface-variant), 0.4);
  border-radius: 4px;
}
</style>
