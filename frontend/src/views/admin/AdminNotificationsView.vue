<template>
  <div class="p-8">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">Notification <span class="gradient-text">Center</span></h1>
        <p class="text-sm" style="color:var(--text-muted)">Manage platform-wide notifications and alerts</p>
      </div>
      <button class="btn-primary px-5 py-2.5 text-sm">+ Send Notification</button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
      <div v-for="s in stats" :key="s.label" class="rounded-2xl p-4" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <div class="text-2xl mb-2">{{ s.icon }}</div>
        <p class="text-xl font-black" :style="{color: s.color}">{{ s.value }}</p>
        <p class="text-xs mt-1" style="color:var(--text-muted)">{{ s.label }}</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Send New Notification -->
      <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid rgba(111,255,210,0.15)">
        <h2 class="font-bold mb-5" style="color:var(--text-primary)">Send Notification</h2>
        <div class="space-y-4">
          <div>
            <label class="block text-xs font-medium mb-2" style="color:var(--text-muted)">Target Audience</label>
            <select class="w-full px-3 py-2.5 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
              <option>All Users</option>
              <option>Premium Subscribers</option>
              <option>Dealers Only</option>
              <option>Unverified Users</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium mb-2" style="color:var(--text-muted)">Type</label>
            <div class="grid grid-cols-2 gap-2">
              <button v-for="t in notifTypes" :key="t.label" class="px-3 py-2 rounded-xl text-xs font-medium text-left" :style="`background:rgba(${t.rgb},0.08);color:rgb(${t.rgb});border:1px solid rgba(${t.rgb},0.2)`">
                {{ t.icon }} {{ t.label }}
              </button>
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium mb-2" style="color:var(--text-muted)">Title</label>
            <input placeholder="Notification title..." class="w-full px-3 py-2.5 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
          </div>
          <div>
            <label class="block text-xs font-medium mb-2" style="color:var(--text-muted)">Message</label>
            <textarea rows="3" placeholder="Message body..." class="w-full px-3 py-2.5 rounded-xl text-sm resize-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)"></textarea>
          </div>
          <button class="w-full py-3 rounded-xl font-semibold text-sm" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">Send Now</button>
        </div>
      </div>

      <!-- Recent Notifications -->
      <div class="lg:col-span-2 rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <h2 class="font-bold mb-5" style="color:var(--text-primary)">Recent Notifications</h2>
        <div class="space-y-3 max-h-96 overflow-y-auto">
          <div v-for="n in notifications" :key="n.id" class="p-4 rounded-xl" style="background:var(--surface-overlay-soft);border:1px solid var(--surface-overlay)">
            <div class="flex items-start gap-3">
              <span class="text-lg shrink-0">{{ n.icon }}</span>
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between mb-1">
                  <p class="text-xs font-bold" style="color:var(--text-primary)">{{ n.title }}</p>
                  <span class="text-xs" style="color:var(--text-muted)">{{ n.time }}</span>
                </div>
                <p class="text-xs" style="color:var(--text-muted)">{{ n.message }}</p>
                <div class="flex items-center gap-3 mt-2">
                  <span class="text-xs px-2 py-0.5 rounded-full" :style="`background:rgba(${n.rgb},0.1);color:rgb(${n.rgb})`">{{ n.target }}</span>
                  <span class="text-xs" style="color:var(--text-muted)">{{ n.sent }} sent · {{ n.opened }} opened</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const stats = [
  { icon: '📤', label: 'Sent Today', value: '284', color: '#6fffd2' },
  { icon: '📬', label: 'Open Rate', value: '68%', color: '#56d8ff' },
  { icon: '🔔', label: 'Unread (Users)', value: '1,284', color: '#a78bfa' },
  { icon: '📋', label: 'Scheduled', value: '3', color: '#fbbf24' },
]
const notifTypes = [
  { icon: '📢', label: 'Announcement', rgb: '111,255,210' },
  { icon: '⚠️', label: 'Alert', rgb: '239,68,68' },
  { icon: '🎉', label: 'Promotion', rgb: '167,139,250' },
  { icon: '🔔', label: 'System', rgb: '86,216,255' },
]
const notifications = [
  { id: 1, icon: '📢', title: 'Summer Listing Promotion', message: 'List your property for free this July!', time: '2h ago', target: 'All Users', sent: 284, opened: 193, rgb: '111,255,210' },
  { id: 2, icon: '⚠️', title: 'Unverified Listings Warning', message: 'Your listing may be removed if not verified within 48 hours.', time: '5h ago', target: 'Unverified', sent: 14, opened: 12, rgb: '239,68,68' },
  { id: 3, icon: '🎉', title: 'Dealer Plan Discount', message: 'Get 20% off Dealer Plan with code SUMMER20', time: '1d ago', target: 'Free Users', sent: 89, opened: 43, rgb: '167,139,250' },
  { id: 4, icon: '🔔', title: 'New AI Feature: Price Battery', message: 'We\'ve launched the AI Price Battery on all listings!', time: '2d ago', target: 'All Users', sent: 373, opened: 248, rgb: '86,216,255' },
]
</script>
