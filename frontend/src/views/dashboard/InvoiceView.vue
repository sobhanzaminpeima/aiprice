<template>
  <div class="p-8 max-w-3xl">
    <div class="flex items-center justify-between mb-6 print:hidden">
      <RouterLink to="/dashboard/subscription" class="text-sm" style="color:var(--text-muted)">← Subscription</RouterLink>
      <button v-if="invoice" @click="print" class="px-4 py-2 rounded-xl text-sm font-semibold" style="background:var(--surface-overlay);color:var(--text-primary);border:1px solid var(--border-subtle)">Print / Save as PDF</button>
    </div>

    <p v-if="error" class="text-sm" style="color:#ef4444">{{ error }}</p>
    <div v-else-if="!invoice" class="h-96 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>

    <div v-else class="invoice rounded-2xl p-10" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <div class="flex justify-between items-start mb-10">
        <div>
          <BrandLogo :size="30" />
          <p class="text-xs mt-3" style="color:var(--text-muted)">AIPrice.app · aiprice.app</p>
        </div>
        <div class="text-right">
          <p class="text-2xl font-black" style="color:var(--text-primary)">INVOICE</p>
          <p class="text-sm font-mono" style="color:var(--text-secondary)">{{ invoice.invoice_number }}</p>
          <span class="inline-block mt-2 px-2 py-0.5 rounded text-xs font-bold uppercase" :style="invoice.status === 'paid' ? 'background:rgba(111,255,210,0.12);color:var(--accent-teal)' : 'background:rgba(239,68,68,0.1);color:#ef4444'">{{ invoice.status === 'void' ? 'refunded' : invoice.status }}</span>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-6 mb-10 text-sm">
        <div>
          <p class="text-xs uppercase tracking-wider mb-1" style="color:var(--text-muted)">Billed to</p>
          <p class="font-semibold" style="color:var(--text-primary)">{{ invoice.user?.profile?.business_name || invoice.user?.name }}</p>
          <p style="color:var(--text-secondary)">{{ invoice.user?.email }}</p>
          <p v-if="invoice.user?.profile?.address" style="color:var(--text-secondary)">{{ invoice.user.profile.address }}</p>
          <p v-if="invoice.user?.profile?.city" style="color:var(--text-secondary)">{{ [invoice.user.profile.city, invoice.user.profile.country].filter(Boolean).join(', ') }}</p>
          <p v-if="invoice.user?.profile?.tax_id" style="color:var(--text-secondary)">Tax ID: {{ invoice.user.profile.tax_id }}</p>
        </div>
        <div class="text-right">
          <p class="text-xs uppercase tracking-wider mb-1" style="color:var(--text-muted)">Issued</p>
          <p style="color:var(--text-primary)">{{ fmtDate(invoice.created_at) }}</p>
          <p class="text-xs uppercase tracking-wider mt-3 mb-1" style="color:var(--text-muted)">Paid</p>
          <p style="color:var(--text-primary)">{{ invoice.paid_at ? fmtDate(invoice.paid_at) : '—' }}</p>
          <p class="text-xs mt-3" style="color:var(--text-muted)">Payment {{ invoice.payment?.payment_number }}</p>
        </div>
      </div>

      <table class="w-full text-sm mb-8">
        <thead><tr class="text-xs uppercase tracking-wider" style="color:var(--text-muted);border-bottom:1px solid var(--border-subtle)"><th class="text-left pb-3">Description</th><th class="text-left pb-3">Period</th><th class="text-right pb-3">Amount</th></tr></thead>
        <tbody>
          <tr v-for="(item, i) in invoice.items" :key="i" style="border-bottom:1px solid var(--border-subtle)">
            <td class="py-4" style="color:var(--text-primary)">{{ item.description }}</td>
            <td class="py-4" style="color:var(--text-secondary)">{{ item.period_start ? `${fmtDate(item.period_start)} – ${fmtDate(item.period_end)}` : '—' }}</td>
            <td class="py-4 text-right" style="color:var(--text-primary)">{{ money(item.amount) }}</td>
          </tr>
        </tbody>
      </table>

      <div class="ml-auto w-64 space-y-2 text-sm">
        <div class="flex justify-between"><span style="color:var(--text-muted)">Subtotal</span><span style="color:var(--text-primary)">{{ money(invoice.subtotal) }}</span></div>
        <div class="flex justify-between"><span style="color:var(--text-muted)">Tax</span><span style="color:var(--text-primary)">{{ money(invoice.tax) }}</span></div>
        <div class="flex justify-between pt-2 text-base font-black" style="border-top:1px solid var(--border-subtle)"><span style="color:var(--text-primary)">Total</span><span style="color:var(--accent-teal)">{{ money(invoice.total) }}</span></div>
      </div>

      <p v-if="invoice.payment?.gateway_receipt" class="mt-10 text-xs print:hidden" style="color:var(--text-muted)">
        Provider receipt: <a :href="invoice.payment.gateway_receipt" target="_blank" rel="noopener" style="color:var(--accent-teal)">view</a>
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import client from '@/api/client'
import BrandLogo from '@/components/BrandLogo.vue'
import { apiErrorMessage } from '@/api/errors'

const route = useRoute()
const invoice = ref<any>(null)
const error = ref('')

const money = (v: number | string) =>
  new Intl.NumberFormat('en-GB', { style: 'currency', currency: invoice.value?.currency || 'GBP' }).format(Number(v))
const fmtDate = (d: string) => new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })
const print = () => window.print()

onMounted(async () => {
  try {
    invoice.value = (await client.get(`/billing/invoices/${route.params.id}`)).data
  } catch (e) {
    error.value = apiErrorMessage(e, 'Invoice not found.')
  }
})
</script>

<style scoped>
@media print {
  .invoice { border: none !important; background: #fff !important; }
  .invoice * { color: #111 !important; }
}
</style>
