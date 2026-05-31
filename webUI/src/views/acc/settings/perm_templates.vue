<template>
  <v-container fluid>
    <v-row class="mb-3" align="center">
      <v-col><h2 class="text-h6">قالب‌های سطح دسترسی</h2></v-col>
      <v-col cols="auto">
        <v-btn color="primary" prepend-icon="mdi-plus" @click="openNew">قالب جدید</v-btn>
      </v-col>
    </v-row>

    <v-row>
      <!-- Template list -->
      <v-col cols="12" md="4">
        <v-card>
          <v-list lines="two">
            <v-list-item
              v-for="tpl in templates"
              :key="tpl.id"
              :title="tpl.name"
              :subtitle="`${countPerms(tpl.permissions)} دسترسی فعال`"
              :active="selected?.id === tpl.id"
              @click="selectTemplate(tpl)"
              color="primary"
            >
              <template #append>
                <v-btn icon size="x-small" variant="text" color="error" @click.stop="delTemplate(tpl.id)">
                  <v-icon>mdi-delete</v-icon>
                </v-btn>
              </template>
            </v-list-item>
          </v-list>
          <v-card-text v-if="templates.length === 0" class="text-center text-medium-emphasis">هنوز قالبی تعریف نشده</v-card-text>
        </v-card>
      </v-col>

      <!-- Template editor -->
      <v-col cols="12" md="8">
        <v-card v-if="selected">
          <v-card-title class="d-flex align-center gap-2">
            <v-text-field v-model="selected.name" label="نام قالب" density="compact" variant="outlined" hide-details style="max-width:250px"/>
            <v-spacer/>
            <v-btn color="primary" :loading="saving" @click="saveTemplate">ذخیره</v-btn>
          </v-card-title>
          <v-card-text>
            <v-row>
              <v-col v-for="f in fields" :key="f.key" cols="12" sm="6" md="4" class="py-0">
                <v-checkbox
                  v-model="selected.permissions[f.key]"
                  :label="f.label"
                  density="compact"
                  hide-details
                />
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>

        <!-- Apply to user -->
        <v-card class="mt-3" v-if="selected">
          <v-card-title class="text-subtitle-1">اعمال قالب به کاربر</v-card-title>
          <v-card-text>
            <v-row align="center">
              <v-col>
                <v-select
                  v-model="applyUserId"
                  :items="users"
                  item-title="label"
                  item-value="id"
                  label="انتخاب کاربر"
                  density="compact"
                  variant="outlined"
                  hide-details
                />
              </v-col>
              <v-col cols="auto">
                <v-btn color="warning" :loading="applying" @click="applyTemplate">اعمال</v-btn>
              </v-col>
            </v-row>
            <v-alert v-if="applyResult" :type="applyResult === 'ok' ? 'success' : 'error'" class="mt-2" variant="tonal">
              {{ applyResult === 'ok' ? 'دسترسی‌ها با موفقیت اعمال شد' : 'خطا در اعمال دسترسی' }}
            </v-alert>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- New template dialog -->
    <v-dialog v-model="newDialog" max-width="400">
      <v-card>
        <v-card-title>قالب جدید</v-card-title>
        <v-card-text>
          <v-text-field v-model="newName" label="نام قالب *" variant="outlined"/>
        </v-card-text>
        <v-card-actions>
          <v-spacer/>
          <v-btn @click="newDialog = false">انصراف</v-btn>
          <v-btn color="primary" @click="createNew">ایجاد</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import axios from 'axios'


const templates = ref<any[]>([])
const fields = ref<any[]>([])
const selected = ref<any>(null)
const saving = ref(false)
const users = ref<any[]>([])
const applyUserId = ref<number | null>(null)
const applying = ref(false)
const applyResult = ref<string | null>(null)
const newDialog = ref(false)
const newName = ref('')

function countPerms(perms: Record<string, boolean>) {
  return Object.values(perms).filter(Boolean).length
}

function selectTemplate(tpl: any) {
  selected.value = JSON.parse(JSON.stringify(tpl))
}

async function loadTemplates() {
  const { data } = await axios.get('/api/acc/perm-template/list', )
  templates.value = Array.isArray(data) ? data : []
}

async function saveTemplate() {
  saving.value = true
  try {
    await axios.post('/api/acc/perm-template/save', selected.value, )
    await loadTemplates()
    const updated = templates.value.find(t => t.id === selected.value.id)
    if (updated) selected.value = JSON.parse(JSON.stringify(updated))
  } finally {
    saving.value = false
  }
}

async function delTemplate(id: number) {
  await axios.post(`/api/acc/perm-template/delete/${id}`, {}, )
  templates.value = templates.value.filter(t => t.id !== id)
  if (selected.value?.id === id) selected.value = null
}

function openNew() {
  newName.value = ''
  newDialog.value = true
}

async function createNew() {
  if (!newName.value.trim()) return
  await axios.post('/api/acc/perm-template/save', { name: newName.value, permissions: {} }, )
  newDialog.value = false
  await loadTemplates()
  const newest = templates.value[templates.value.length - 1]
  if (newest) selectTemplate(newest)
}

async function applyTemplate() {
  if (!selected.value || !applyUserId.value) return
  applying.value = true
  applyResult.value = null
  try {
    const { data } = await axios.post('/api/acc/perm-template/apply', {
      templateId: selected.value.id,
      userId: applyUserId.value,
    }, )
    applyResult.value = data.result === 1 ? 'ok' : 'error'
  } finally {
    applying.value = false
  }
}

onMounted(async () => {
  const [f, u] = await Promise.all([
    axios.get('/api/acc/perm-template/fields', ),
    axios.get('/api/acc/crm/users', ),
  ])
  fields.value = Array.isArray(f.data) ? f.data : []
  users.value = (Array.isArray(u.data) ? u.data : []).map((u: any) => ({ id: u.id, label: u.name ?? u.mobile }))

  await loadTemplates()
})
</script>
