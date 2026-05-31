<template>
  <v-alert v-if="upcomingCheques.length > 0" type="warning" variant="tonal" border="start" class="mb-3">
    <div class="d-flex align-center justify-space-between">
      <div>
        <strong>{{ upcomingCheques.length }} چک پرداختنی</strong> در ۷ روز آینده سررسید می‌شوند
      </div>
      <v-btn size="small" variant="text" @click="expanded = !expanded">
        {{ expanded ? 'بستن' : 'مشاهده' }}
        <v-icon>{{ expanded ? 'mdi-chevron-up' : 'mdi-chevron-down' }}</v-icon>
      </v-btn>
    </div>

    <v-expand-transition>
      <div v-if="expanded" class="mt-2">
        <v-table density="compact">
          <thead>
            <tr>
              <th>شخص</th>
              <th>بانک</th>
              <th>تاریخ سررسید</th>
              <th>روز مانده</th>
              <th>مبلغ (ریال)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in upcomingCheques" :key="c.id">
              <td>{{ c.personName }}</td>
              <td>{{ c.bankName }}</td>
              <td>{{ c.payDate }}</td>
              <td>
                <v-chip :color="c.daysUntilDue <= 1 ? 'error' : c.daysUntilDue <= 3 ? 'warning' : 'info'" size="x-small">
                  {{ c.daysUntilDue }} روز
                </v-chip>
              </td>
              <td>{{ Number(c.amount).toLocaleString('fa-IR') }}</td>
            </tr>
          </tbody>
        </v-table>
      </div>
    </v-expand-transition>
  </v-alert>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import axios from 'axios'

const upcomingCheques = ref<any[]>([])
const expanded = ref(false)

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/cheque/upcoming')
    upcomingCheques.value = Array.isArray(data) ? data : []
  } catch {
    // silent fail
  }
})
</script>
