<template>
  <div class="flex min-h-screen" style="background:var(--bg-base)">
    <!-- Sidebar (desktop only) -->
    <aside class="hidden md:flex w-64 shrink-0 flex-col" style="background:var(--bg-surface);border-right:1px solid var(--border-subtle)">
      <div class="p-5 flex items-center gap-2.5" style="border-bottom:1px solid var(--border-subtle)">
        <RouterLink to="/" class="flex items-center gap-2">
          <BrandLogo :size="26" />
        </RouterLink>
      </div>
      <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
        <p class="text-xs font-bold px-3 mb-2 mt-2" style="color:var(--text-muted);letter-spacing:0.08em">DASHBOARD</p>
        <SidebarLink to="/dashboard/overview" icon="grid">Overview</SidebarLink>
        <SidebarLink to="/dashboard/listings" icon="list">My Listings</SidebarLink>
        <SidebarLink to="/dashboard/listings/new" icon="plus">New Listing</SidebarLink>
        <SidebarLink to="/dashboard/messages" icon="message">Messages</SidebarLink>
        <SidebarLink to="/dashboard/analytics" icon="chart">Analytics</SidebarLink>
        <SidebarLink to="/dashboard/favorites" icon="heart">Favourites</SidebarLink>
        <p class="text-xs font-bold px-3 mb-2 mt-4" style="color:var(--text-muted);letter-spacing:0.08em">ACCOUNT</p>
        <SidebarLink to="/dashboard/profile" icon="user">Profile</SidebarLink>
        <SidebarLink to="/dashboard/kyc" icon="shield">KYC Verification</SidebarLink>
        <SidebarLink to="/dashboard/support" icon="message">Help &amp; Support</SidebarLink>
        <SidebarLink to="/dashboard/subscription" icon="star">Subscription</SidebarLink>
      </nav>
      <div class="p-4" style="border-top:1px solid var(--border-subtle)">
        <div class="flex items-center gap-3 mb-3">
          <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">{{ auth.user?.name?.charAt(0) }}</div>
          <div class="flex-1 min-w-0">
            <p class="text-xs font-semibold truncate" style="color:var(--text-primary)">{{ auth.user?.name }}</p>
            <p class="text-xs truncate" style="color:var(--text-muted)">{{ auth.user?.role }}</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button @click="logout" class="flex-1 py-2 rounded-lg text-xs font-medium transition" style="background:var(--surface-overlay);border:1px solid var(--border-subtle);color:var(--text-muted)">Sign Out</button>
          <NotificationBell align="up" />
          <ThemeToggle :size="34" />
        </div>
      </div>
    </aside>

    <!-- Mobile top bar + drawer + bottom tabs -->
    <DashboardMobileNav />

    <!-- Content -->
    <main class="flex-1 p-4 md:p-8 pb-20 md:pb-8 overflow-auto">
      <VerifyEmailBanner />
      <RouterView />
    </main>
  </div>
</template>
<script setup lang="ts">
import { RouterLink, RouterView, useRouter } from "vue-router"
import { useAuthStore } from "@/stores/auth"
import SidebarLink from "@/components/ui/SidebarLink.vue"
import BrandLogo from "@/components/BrandLogo.vue"
import ThemeToggle from "@/components/ThemeToggle.vue"
import DashboardMobileNav from "@/components/DashboardMobileNav.vue"
import NotificationBell from "@/components/NotificationBell.vue"
import VerifyEmailBanner from "@/components/VerifyEmailBanner.vue"
const auth = useAuthStore(); const router = useRouter()
async function logout() { await auth.logout(); router.push("/") }
</script>
