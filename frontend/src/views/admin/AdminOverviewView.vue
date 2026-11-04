<template>
  <div class="p-8">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">Admin <span class="gradient-text">Dashboard</span></h1>
        <p class="text-sm" style="color:var(--text-muted)">Platform overview — {{ new Date().toLocaleDateString("en-GB", {weekday:"long",year:"numeric",month:"long",day:"numeric"}) }}</p>
      </div>
      <div class="ai-badge">Live</div>
    </div>

    <div v-if="loading" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
      <div v-for="i in 8" :key="i" class="rounded-2xl h-28 animate-pulse" style="background:var(--bg-surface)"></div>
    </div>

    <template v-else>
      <!-- Stats grid -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div v-for="s in stats" :key="s.label" class="rounded-2xl p-5" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <AppIcon :name="s.icon" :size="24" class="mb-1" />
          <div class="text-3xl font-black mb-0.5" :style="{color: s.color}">{{ s.value?.toLocaleString() || 0 }}</div>
          <div class="text-xs" style="color:var(--text-muted)">{{ s.label }}</div>
        </div>
      </div>

      <!-- Two columns -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Listings -->
        <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <div class="flex items-center justify-between mb-5">
            <h2 class="font-bold" style="color:var(--text-primary)">Recent Listings</h2>
            <RouterLink to="/admin/listings" class="text-xs" style="color:var(--accent-teal)">View all →</RouterLink>
          </div>
          <div class="space-y-3">
            <div v-for="l in data?.recent_listings?.slice(0,6)" :key="l.id" class="flex items-center justify-between p-3 rounded-xl" style="background:var(--surface-overlay-soft);border:1px solid var(--surface-overlay)">
              <div class="flex items-center gap-3">
                <AppIcon :name="listingTypeIcon(l.type)" :size="20" />
                <div>
                  <p class="text-xs font-medium truncate max-w-[160px]" style="color:var(--text-primary)">{{ l.title }}</p>
                  <p class="text-xs" style="color:var(--text-muted)">{{ l.user?.name }}</p>
                </div>
              </div>
              <span class="text-xs px-2 py-0.5 rounded-full capitalize" :class="statusClass(l.status)">{{ l.status }}</span>
            </div>
          </div>
        </div>

        <!-- Recent Users -->
        <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <div class="flex items-center justify-between mb-5">
            <h2 class="font-bold" style="color:var(--text-primary)">Recent Users</h2>
            <RouterLink to="/admin/users" class="text-xs" style="color:var(--accent-teal)">View all →</RouterLink>
          </div>
          <div class="space-y-3">
            <div v-for="u in data?.recent_users?.slice(0,6)" :key="u.id" class="flex items-center justify-between p-3 rounded-xl" style="background:var(--surface-overlay-soft);border:1px solid var(--surface-overlay)">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs" style="background:linear-gradient(135deg,rgba(var(--accent-teal-rgb),0.2),rgba(var(--accent-cyan-rgb),0.2));color:var(--accent-teal)">{{ u.name?.charAt(0) }}</div>
                <div>
                  <p class="text-xs font-medium" style="color:var(--text-primary)">{{ u.name }}</p>
                  <p class="text-xs truncate max-w-[120px]" style="color:var(--text-muted)">{{ u.email }}</p>
                </div>
              </div>
              <span class="text-xs px-2 py-0.5 rounded-full capitalize" style="background:rgba(var(--accent-cyan-rgb),0.1);color:var(--accent-cyan);border:1px solid rgba(var(--accent-cyan-rgb),0.2)">{{ u.role }}</span>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
<script setup lang="ts">
import { ref, computed, onMounted } from "vue"; import { RouterLink } from "vue-router"; import client from "@/api/client"
import AppIcon from '@/components/ui/AppIcon.vue'
const loading = ref(true); const data = ref<any>(null)
onMounted(async () => { const r = await client.get("/admin/dashboard"); data.value = r.data; loading.value = false })
const stats = computed(() => data.value ? [
  { icon: "users", label: "Total Users", value: data.value.stats.total_users, color: "var(--accent-cyan)" },
  { icon: "clipboard", label: "Active Listings", value: data.value.stats.active_listings, color: "var(--accent-teal)" },
  { icon: "alert", label: "Pending Review", value: data.value.stats.pending_listings, color: "var(--warning)" },
  { icon: "car", label: "Vehicles", value: data.value.stats.vehicle_listings, color: "var(--accent-teal)" },
  { icon: "home", label: "Properties", value: data.value.stats.property_listings, color: "var(--accent-cyan)" },
  { icon: "key", label: "Rentals", value: data.value.stats.rental_listings, color: "var(--accent-violet)" },
  { icon: "building", label: "Dealers", value: data.value.stats.dealers, color: "var(--warning)" },
  { icon: "shield", label: "KYC Verified", value: 0, color: "var(--accent-teal)" },
] : [])
function statusClass(s: string) {
  return { active: "badge-teal", pending: "badge-amber", rejected: "badge-red" }[s] || "badge-cyan"
}
function listingTypeIcon(type: string) {
  const icons: Record<string, string> = { vehicle: "car", property: "home", rental: "key" }
  return icons[type] ?? "clipboard"
}
</script>
