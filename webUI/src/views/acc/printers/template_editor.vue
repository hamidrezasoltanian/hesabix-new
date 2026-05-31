<template>
  <v-container fluid>
    <v-row class="mb-3" align="center">
      <v-col><h2 class="text-h6">ویرایشگر قالب چاپ</h2></v-col>
      <v-col cols="auto">
        <v-btn color="primary" :loading="saving" prepend-icon="mdi-content-save" @click="save">ذخیره قالب‌ها</v-btn>
      </v-col>
    </v-row>

    <v-row>
      <!-- Variable reference -->
      <v-col cols="12" md="3">
        <v-card>
          <v-card-title class="text-subtitle-1">متغیرهای قابل استفاده</v-card-title>
          <v-card-subtitle>{{ activeTab }}</v-card-subtitle>
          <v-card-text class="pa-1">
            <v-list density="compact">
              <v-list-item
                v-for="v in currentVars"
                :key="v.key"
                :subtitle="v.label"
                class="py-0"
                style="cursor:pointer"
                @click="insertVar(v.key)"
              >
                <template #title>
                  <code class="text-primary text-caption">{{ v.key }}</code>
                </template>
              </v-list-item>
            </v-list>
            <v-alert type="info" variant="tonal" density="compact" class="mt-2 text-caption">
              روی هر متغیر کلیک کنید تا در ویرایشگر درج شود
            </v-alert>
          </v-card-text>
        </v-card>

        <v-card class="mt-3">
          <v-card-title class="text-subtitle-1">پیش‌نمایش</v-card-title>
          <v-card-text>
            <div
              class="preview-box"
              style="border:1px solid #ccc;min-height:200px;padding:10px;font-size:12px;overflow:auto"
              v-html="previewHtml"
            />
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Editor tabs -->
      <v-col cols="12" md="9">
        <v-card>
          <v-tabs v-model="tab" bg-color="primary">
            <v-tab value="invoice">فاکتور فروش</v-tab>
            <v-tab value="preinvoice">پیش‌فاکتور</v-tab>
            <v-tab value="storeroom">حواله انبار</v-tab>
          </v-tabs>

          <v-window v-model="tab">
            <v-window-item value="invoice">
              <v-card-text>
                <v-textarea
                  ref="invoiceRef"
                  v-model="templates.invoiceTemplate"
                  label="HTML قالب فاکتور فروش"
                  rows="20"
                  variant="outlined"
                  font-family="monospace"
                  style="font-family:monospace;font-size:12px"
                />
              </v-card-text>
            </v-window-item>

            <v-window-item value="preinvoice">
              <v-card-text>
                <v-textarea
                  ref="preinvoiceRef"
                  v-model="templates.preinvoiceTemplate"
                  label="HTML قالب پیش‌فاکتور"
                  rows="20"
                  variant="outlined"
                  style="font-family:monospace;font-size:12px"
                />
              </v-card-text>
            </v-window-item>

            <v-window-item value="storeroom">
              <v-card-text>
                <v-textarea
                  ref="storeroomRef"
                  v-model="templates.storeroomTemplate"
                  label="HTML قالب حواله انبار"
                  rows="20"
                  variant="outlined"
                  style="font-family:monospace;font-size:12px"
                />
              </v-card-text>
            </v-window-item>
          </v-window>
        </v-card>

        <!-- Action buttons -->
        <v-row class="mt-2">
          <v-col cols="auto">
            <v-btn variant="tonal" @click="resetToDefault">بازگشت به قالب پیش‌فرض</v-btn>
          </v-col>
          <v-col cols="auto">
            <v-btn variant="tonal" prepend-icon="mdi-eye" @click="showPreview = !showPreview">
              {{ showPreview ? 'پنهان کردن پیش‌نمایش' : 'پیش‌نمایش' }}
            </v-btn>
          </v-col>
        </v-row>
      </v-col>
    </v-row>

    <v-snackbar v-model="snack" :color="snackColor" timeout="3000">{{ snackText }}</v-snackbar>
  </v-container>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const tab = ref('invoice')
const saving = ref(false)
const showPreview = ref(true)
const snack = ref(false)
const snackText = ref('')
const snackColor = ref('success')

const templates = ref({ invoiceTemplate: '', preinvoiceTemplate: '', storeroomTemplate: '' })
const defaults = ref({ invoice: '', preinvoice: '', storeroom: '' })
const vars = ref<Record<string, any[]>>({ invoice: [], preinvoice: [], storeroom: [] })

const activeTab = computed(() => (({ invoice: 'فاکتور فروش', preinvoice: 'پیش‌فاکتور', storeroom: 'حواله انبار' } as Record<string, string>)[tab.value] ?? ''))

const currentVars = computed(() => vars.value[tab.value] ?? [])

const currentTemplate = computed(() => {
  if (tab.value === 'invoice') return templates.value.invoiceTemplate
  if (tab.value === 'preinvoice') return templates.value.preinvoiceTemplate
  return templates.value.storeroomTemplate
})

const previewHtml = computed(() => {
  if (!showPreview.value) return ''
  let html = currentTemplate.value
  // Replace vars with sample values
  const samples: Record<string, string> = {
    '{{business_name}}': 'شرکت نمونه',
    '{{invoice_number}}': '۱۲۳۴',
    '{{invoice_date}}': '۱۴۰۳/۰۱/۱۵',
    '{{preinvoice_number}}': '۵۶۷',
    '{{preinvoice_date}}': '۱۴۰۳/۰۱/۱۵',
    '{{expire_date}}': '۱۴۰۳/۰۲/۱۵',
    '{{ticket_number}}': '۸۹',
    '{{ticket_date}}': '۱۴۰۳/۰۱/۱۵',
    '{{ticket_type}}': 'خروج',
    '{{storeroom_name}}': 'انبار مرکزی',
    '{{person_name}}': 'محمد محمدی',
    '{{person_mobile}}': '09123456789',
    '{{person_address}}': 'تهران، خیابان آزادی',
    '{{total_amount}}': '۱,۰۰۰,۰۰۰',
    '{{discount_amount}}': '۵۰,۰۰۰',
    '{{tax_amount}}': '۹۵,۰۰۰',
    '{{payable_amount}}': '۱,۰۴۵,۰۰۰',
    '{{description}}': 'بدون توضیح',
    '{{items_table}}': '<table border="1" style="width:100%;border-collapse:collapse"><tr><th>کالا</th><th>تعداد</th><th>قیمت</th></tr><tr><td>کالای نمونه</td><td>۲</td><td>۵۰۰,۰۰۰</td></tr></table>',
  }
  for (const [k, v] of Object.entries(samples)) {
    html = html.replaceAll(k, v)
  }
  return html
})

function insertVar(key: string) {
  if (tab.value === 'invoice') templates.value.invoiceTemplate += key
  else if (tab.value === 'preinvoice') templates.value.preinvoiceTemplate += key
  else templates.value.storeroomTemplate += key
}

function resetToDefault() {
  if (tab.value === 'invoice') templates.value.invoiceTemplate = defaults.value.invoice
  else if (tab.value === 'preinvoice') templates.value.preinvoiceTemplate = defaults.value.preinvoice
  else templates.value.storeroomTemplate = defaults.value.storeroom
}

async function save() {
  saving.value = true
  try {
    await axios.post('/api/acc/print-template/save', templates.value, )
    snackText.value = 'قالب‌ها ذخیره شدند'
    snackColor.value = 'success'
    snack.value = true
  } catch {
    snackText.value = 'خطا در ذخیره‌سازی'
    snackColor.value = 'error'
    snack.value = true
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  const [t, v] = await Promise.all([
    axios.get('/api/acc/print-template/load', ),
    axios.get('/api/acc/print-template/variables', ),
  ])
  templates.value.invoiceTemplate = t.data.invoiceTemplate ?? ''
  templates.value.preinvoiceTemplate = t.data.preinvoiceTemplate ?? ''
  templates.value.storeroomTemplate = t.data.storeroomTemplate ?? ''
  defaults.value.invoice = t.data.defaults?.invoice ?? ''
  defaults.value.preinvoice = t.data.defaults?.preinvoice ?? ''
  defaults.value.storeroom = t.data.defaults?.storeroom ?? ''
  vars.value = v.data ?? {}
})
</script>
