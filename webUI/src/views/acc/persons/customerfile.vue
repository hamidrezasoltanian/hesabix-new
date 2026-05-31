<template>
  <v-container fluid>
    <v-row class="mb-2" align="center">
      <v-col>
        <v-btn icon variant="text" @click="$router.back()"><v-icon>mdi-arrow-right</v-icon></v-btn>
        <span class="text-h6 mr-2">پرونده مشتری: {{ person?.nikename ?? '' }}</span>
      </v-col>
    </v-row>

    <v-row>
      <!-- Person info card -->
      <v-col cols="12" md="4">
        <v-card class="mb-3">
          <v-card-title class="text-subtitle-1">اطلاعات مشتری</v-card-title>
          <v-card-text v-if="person">
            <v-list density="compact">
              <v-list-item v-if="person.name" prepend-icon="mdi-account" :subtitle="person.name" title="نام کامل"/>
              <v-list-item v-if="person.mobile" prepend-icon="mdi-phone" :subtitle="person.mobile" title="موبایل"/>
              <v-list-item v-if="person.tel" prepend-icon="mdi-phone-classic" :subtitle="person.tel" title="تلفن"/>
              <v-list-item v-if="person.address" prepend-icon="mdi-map-marker" :subtitle="person.address" title="آدرس"/>
              <v-list-item v-if="person.ostan" prepend-icon="mdi-map" :subtitle="person.ostan" title="استان"/>
            </v-list>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Notes / Tasks / Reminders -->
      <v-col cols="12" md="8">
        <!-- Add new item form -->
        <v-card class="mb-3">
          <v-card-text>
            <v-row align="start">
              <v-col cols="12" sm="2">
                <v-select
                  v-model="newNote.type"
                  :items="noteTypes"
                  item-title="label"
                  item-value="value"
                  label="نوع"
                  density="compact"
                  variant="outlined"
                  hide-details
                />
              </v-col>
              <v-col>
                <v-textarea
                  v-model="newNote.content"
                  label="متن یادداشت / یادآور / وظیفه..."
                  rows="2"
                  density="compact"
                  variant="outlined"
                  hide-details
                />
              </v-col>
            </v-row>
            <v-row align="center" class="mt-1">
              <v-col v-if="newNote.type === 'reminder'" cols="12" sm="4">
                <v-text-field v-model="newNote.reminderDate" label="تاریخ یادآور (YYYY/MM/DD)" density="compact" variant="outlined" hide-details/>
              </v-col>
              <v-col v-if="newNote.type === 'mention'" cols="12" sm="4">
                <v-text-field v-model="newNote.mentionedMobile" label="موبایل شخص منشن‌شده" density="compact" variant="outlined" hide-details/>
              </v-col>
              <v-spacer/>
              <v-col cols="auto">
                <v-btn color="primary" :loading="saving" @click="addNote">ثبت</v-btn>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>

        <!-- Filter chips -->
        <v-chip-group v-model="typeFilter" class="mb-2">
          <v-chip value="all" filter>همه</v-chip>
          <v-chip v-for="t in noteTypes" :key="t.value" :value="t.value" filter :prepend-icon="t.icon">
            {{ t.label }}
          </v-chip>
        </v-chip-group>

        <!-- Notes list -->
        <v-timeline density="compact" side="end">
          <v-timeline-item
            v-for="note in filteredNotes"
            :key="note.id"
            :dot-color="typeColor(note.type)"
            :icon="typeIcon(note.type)"
            size="small"
          >
            <v-card variant="outlined">
              <v-card-text class="pa-2">
                <div class="d-flex justify-space-between align-center mb-1">
                  <div>
                    <v-chip :color="typeColor(note.type)" size="x-small" class="me-1">{{ typeLabel(note.type) }}</v-chip>
                    <span class="text-caption text-medium-emphasis">{{ note.date }}</span>
                    <span v-if="note.user" class="text-caption text-medium-emphasis mr-1">— {{ note.user.name ?? note.user.mobile }}</span>
                  </div>
                  <v-btn icon size="x-small" variant="text" color="error" @click="delNote(note.id)">
                    <v-icon size="small">mdi-delete</v-icon>
                  </v-btn>
                </div>
                <div class="text-body-2" style="white-space:pre-wrap">{{ note.content }}</div>
                <div v-if="note.reminderDate" class="text-caption text-warning mt-1">
                  <v-icon size="x-small">mdi-bell</v-icon> یادآور: {{ note.reminderDate }}
                </div>
                <div v-if="note.mentionedMobile" class="text-caption text-primary mt-1">
                  <v-icon size="x-small">mdi-at</v-icon> {{ note.mentionedMobile }}
                </div>
              </v-card-text>
            </v-card>
          </v-timeline-item>
        </v-timeline>

        <v-alert v-if="notes.length === 0 && !loading" type="info" variant="tonal" class="mt-2">
          هنوز یادداشتی ثبت نشده است
        </v-alert>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'

const route = useRoute()

const personId = computed(() => Number(route.params.id))
const person = ref<any>(null)
const notes = ref<any[]>([])
const loading = ref(false)
const saving = ref(false)
const typeFilter = ref('all')

const newNote = ref({ type: 'note', content: '', reminderDate: '', mentionedMobile: '' })

const noteTypes = [
  { value: 'note', label: 'یادداشت', icon: 'mdi-note-text', color: 'blue' },
  { value: 'task', label: 'وظیفه', icon: 'mdi-checkbox-marked-circle-outline', color: 'green' },
  { value: 'reminder', label: 'یادآور', icon: 'mdi-bell', color: 'orange' },
  { value: 'mention', label: 'منشن', icon: 'mdi-at', color: 'purple' },
  { value: 'report', label: 'گزارش', icon: 'mdi-file-document', color: 'teal' },
]

function typeColor(type: string) { return noteTypes.find(t => t.value === type)?.color ?? 'grey' }
function typeIcon(type: string) { return noteTypes.find(t => t.value === type)?.icon ?? 'mdi-note' }
function typeLabel(type: string) { return noteTypes.find(t => t.value === type)?.label ?? type }

const filteredNotes = computed(() =>
  typeFilter.value === 'all' ? notes.value : notes.value.filter(n => n.type === typeFilter.value)
)

async function loadNotes() {
  loading.value = true
  try {
    const { data } = await axios.get(`/api/acc/customer-note/list/${personId.value}`, )
    notes.value = Array.isArray(data) ? data : []
  } finally {
    loading.value = false
  }
}

async function addNote() {
  if (!newNote.value.content.trim()) return
  saving.value = true
  try {
    await axios.post('/api/acc/customer-note/add', {
      personId: personId.value,
      ...newNote.value,
    }, )
    newNote.value = { type: 'note', content: '', reminderDate: '', mentionedMobile: '' }
    await loadNotes()
  } finally {
    saving.value = false
  }
}

async function delNote(id: number) {
  await axios.post(`/api/acc/customer-note/del/${id}`, {}, )
  notes.value = notes.value.filter(n => n.id !== id)
}

onMounted(async () => {
  // Load person info from persons list
  try {
    const { data } = await axios.post('/api/acc/person/responsible-list', {}, )
    person.value = (Array.isArray(data) ? data : []).find((p: any) => p.id === personId.value) ?? null
  } catch {}
  await loadNotes()
})
</script>
