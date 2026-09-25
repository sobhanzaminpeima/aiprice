<template>
  <div class='p-8'>
    <div class='flex items-center justify-between mb-8'>
      <div>
        <h1 class='text-3xl font-black mb-1' style='color:var(--text-primary)'>Support <span class='gradient-text'>Center</span></h1>
        <p class='text-sm' style='color:var(--text-muted)'>User support tickets and inquiries</p>
      </div>
      <div class='flex gap-2'>
        <button v-for='t in ticketTabs' :key='t' @click='activeTab=t' class='px-4 py-2 rounded-xl text-xs font-semibold transition-all'
          :style="activeTab===t?'background:rgba(111,255,210,0.1);color:var(--accent-teal);border:1px solid rgba(111,255,210,0.2)':'background:var(--surface-overlay);color:var(--text-muted);border:1px solid transparent'">{{ t }}</button>
      </div>
    </div>
    <div class='grid grid-cols-4 gap-4 mb-6'>
      <div v-for='s in ticketStats' :key='s.label' class='rounded-2xl p-4 text-center' style='background:var(--bg-surface);border:1px solid var(--border-subtle)'>
        <p class='text-2xl font-black' :style='{color:s.color}'>{{ s.val }}</p>
        <p class='text-xs mt-1' style='color:var(--text-muted)'>{{ s.label }}</p>
      </div>
    </div>
    <div class='rounded-2xl' style='background:var(--bg-surface);border:1px solid var(--border-subtle)'>
      <div class='p-5' style='border-bottom:1px solid var(--border-subtle)'>
        <h2 class='font-bold' style='color:var(--text-primary)'>{{ activeTab }} Tickets</h2>
      </div>
      <div class='p-5 space-y-3'>
        <div v-if='tickets.length===0' class='text-center py-10'>
          <div class='text-5xl mb-3'>S</div>
          <p class='font-semibold' style='color:var(--text-primary)'>No {{ activeTab.toLowerCase() }} tickets</p>
          <p class='text-xs mt-1' style='color:var(--text-muted)'>All clear!</p>
        </div>
        <div v-for='ticket in tickets' :key='ticket.id' class='flex items-start gap-4 p-4 rounded-xl' style='background:var(--surface-overlay-soft);border:1px solid var(--surface-overlay)'>
          <div class='w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs shrink-0' style='background:rgba(86,216,255,0.15);color:var(--accent-cyan)'>{{ ticket.user?.charAt(0)||'U' }}</div>
          <div class='flex-1 min-w-0'>
            <div class='flex items-center justify-between mb-1'>
              <p class='text-sm font-semibold' style='color:var(--text-primary)'>{{ ticket.subject }}</p>
              <span class='text-xs' style='color:var(--text-muted)'>{{ ticket.time }}</span>
            </div>
            <p class='text-xs' style='color:var(--text-muted)'>{{ ticket.preview }}</p>
            <div class='flex gap-2 mt-2'>
              <span class='text-xs px-2 py-0.5 rounded-full' :style='ticketStyle(ticket.priority)'>{{ ticket.priority }}</span>
              <button class='text-xs px-3 py-1 rounded-lg' style='background:rgba(111,255,210,0.08);color:var(--accent-teal);border:1px solid rgba(111,255,210,0.15)'>Reply</button>
              <button class='text-xs px-3 py-1 rounded-lg' style='background:var(--surface-overlay);color:var(--text-muted);border:1px solid var(--border-subtle)'>Close</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup lang='ts'>
import { ref, computed } from 'vue'
const ticketTabs = ['Open', 'In Progress', 'Closed']
const activeTab = ref('Open')
const ticketStats = [
  { label:'Open', val:'3', color:'#fbbf24' },
  { label:'In Progress', val:'1', color:'#56d8ff' },
  { label:'Avg Response', val:'2.4h', color:'#6fffd2' },
  { label:'Closed Today', val:'5', color:'#a78bfa' },
]
const allTickets = [
  { id:1, user:'Ali K.', subject:'Cannot upload more than 3 listings', preview:'I have Premium plan but still limited to 3 listings...', time:'2h ago', priority:'High', status:'Open' },
  { id:2, user:'Maria S.', subject:'AI price seems incorrect for my property', preview:'The AI battery shows 42% but I believe my price is fair...', time:'5h ago', priority:'Medium', status:'Open' },
  { id:3, user:'Hassan M.', subject:'Payment not processed', preview:'I upgraded to Dealer plan but it shows Free...', time:'1d ago', priority:'High', status:'Open' },
  { id:4, user:'Emma L.', subject:'Photos not loading on listing', preview:'My listing photos uploaded fine but now they are blank...', time:'2d ago', priority:'Low', status:'In Progress' },
]
const tickets = computed(() => {
  const map: Record<string,string> = { 'Open':'Open', 'In Progress':'In Progress', 'Closed':'Closed' }
  return allTickets.filter(t => t.status === map[activeTab.value])
})
function ticketStyle(p: string) {
  const m: Record<string,string> = {
    High: 'background:rgba(239,68,68,0.1);color:#ef4444;border:1px solid rgba(239,68,68,0.2)',
    Medium: 'background:rgba(251,191,36,0.1);color:#fbbf24;border:1px solid rgba(251,191,36,0.2)',
    Low: 'background:rgba(111,255,210,0.1);color:var(--accent-teal);border:1px solid rgba(111,255,210,0.2)',
  }
  return m[p] || ''
}
</script>