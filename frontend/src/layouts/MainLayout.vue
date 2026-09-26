<template>
  <div style="min-height:100vh;background:var(--bg-base);color:var(--text-primary);display:flex;flex-direction:column;position:relative">
    <AnimatedBackground />

    <!-- Navbar -->
    <nav style="background:var(--nav-bg);backdrop-filter:blur(20px);border-bottom:1px solid rgba(111,255,210,0.08);position:sticky;top:0;z-index:50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div style="display:flex;align-items:center;justify-content:space-between;height:64px">

          <!-- Logo -->
          <RouterLink to="/" style="display:flex;align-items:center;gap:10px;text-decoration:none">
            <BrandLogo :size="30" />
          </RouterLink>

          <!-- Desktop Mega Nav -->
          <div class="hidden md:flex" style="align-items:center;gap:2px">

            <!-- Cars Mega Menu -->
            <div class="mega-wrapper" @mouseenter="showCars=true" @mouseleave="showCars=false">
              <button style="display:flex;align-items:center;gap:6px;padding:8px 14px;border-radius:10px;font-size:13px;font-weight:600;background:none;border:none;cursor:pointer;transition:all .2s"
                :style="$route.path.startsWith('/cars') ? 'color:var(--accent-teal);background:rgba(111,255,210,0.08)' : 'color:var(--text-secondary)'">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M8 6H5.5A2.5 2.5 0 003 8.5v2A1.5 1.5 0 004.5 12h15a1.5 1.5 0 001.5-1.5v-2A2.5 2.5 0 0018.5 6H16m-8 0l2-3h4l2 3M8 6h8M6 12v5a1 1 0 001 1h1m8-6v5a1 1 0 01-1 1h-1m-6 0h6"/>
                </svg>
                Cars
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="transition:.2s" :style="showCars?'transform:rotate(180deg)':''"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
              </button>
              <!-- Cars Dropdown -->
              <div v-show="showCars" class="mega-dropdown" style="left:0;width:520px">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
                  <div>
                    <p style="font-size:10px;font-weight:700;letter-spacing:.1em;color:var(--text-muted);text-transform:uppercase;margin-bottom:10px">By Body Type</p>
                    <RouterLink v-for="bt in carBodyTypes" :key="bt.label" :to="bt.to" @click="showCars=false"
                      style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:10px;text-decoration:none;transition:all .15s;margin-bottom:2px"
                      class="mega-item">
                      <div style="width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0" :style="'background:rgba('+bt.rgb+',0.12)'">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="1.6" :stroke="'rgb('+bt.rgb+')'"><path stroke-linecap="round" stroke-linejoin="round" :d="bt.icon"/></svg>
                      </div>
                      <div>
                        <p style="font-size:13px;font-weight:600;color:var(--text-primary)">{{ bt.label }}</p>
                        <p style="font-size:11px;color:var(--text-muted)">{{ bt.sub }}</p>
                      </div>
                    </RouterLink>
                  </div>
                  <div>
                    <p style="font-size:10px;font-weight:700;letter-spacing:.1em;color:var(--text-muted);text-transform:uppercase;margin-bottom:10px">Popular Brands</p>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px">
                      <RouterLink v-for="b in popularBrands" :key="b" :to="'/cars?brand='+b" @click="showCars=false"
                        style="padding:8px 12px;border-radius:8px;font-size:12px;font-weight:500;color:var(--text-secondary);text-decoration:none;transition:all .15s;border:1px solid var(--border-subtle)"
                        class="brand-chip">{{ b }}</RouterLink>
                    </div>
                    <RouterLink to="/cars" @click="showCars=false" style="display:flex;align-items:center;gap:6px;margin-top:14px;padding:10px 14px;border-radius:10px;font-size:13px;font-weight:600;color:var(--accent-teal);text-decoration:none;background:rgba(111,255,210,0.08);border:1px solid rgba(111,255,210,0.15)">
                      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/></svg>
                      View All Cars →
                    </RouterLink>
                  </div>
                </div>
              </div>
            </div>

            <!-- Properties Mega Menu -->
            <div class="mega-wrapper" @mouseenter="showProps=true" @mouseleave="showProps=false">
              <button style="display:flex;align-items:center;gap:6px;padding:8px 14px;border-radius:10px;font-size:13px;font-weight:600;background:none;border:none;cursor:pointer;transition:all .2s"
                :style="$route.path.startsWith('/properties') ? 'color:var(--accent-violet);background:rgba(167,139,250,0.08)' : 'color:var(--text-secondary)'">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Properties
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="transition:.2s" :style="showProps?'transform:rotate(180deg)':''"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
              </button>
              <!-- Properties Dropdown -->
              <div v-show="showProps" class="mega-dropdown" style="left:0;width:480px">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
                  <div>
                    <p style="font-size:10px;font-weight:700;letter-spacing:.1em;color:var(--text-muted);text-transform:uppercase;margin-bottom:10px">Property Types</p>
                    <RouterLink v-for="pt in propTypes" :key="pt.label" :to="pt.to" @click="showProps=false"
                      style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:10px;text-decoration:none;transition:all .15s;margin-bottom:2px"
                      class="mega-item">
                      <div style="width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0" :style="'background:rgba('+pt.rgb+',0.12)'">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="1.6" :stroke="'rgb('+pt.rgb+')'"><path stroke-linecap="round" stroke-linejoin="round" :d="pt.icon"/></svg>
                      </div>
                      <div>
                        <p style="font-size:13px;font-weight:600;color:var(--text-primary)">{{ pt.label }}</p>
                        <p style="font-size:11px;color:var(--text-muted)">{{ pt.sub }}</p>
                      </div>
                    </RouterLink>
                  </div>
                  <div>
                    <p style="font-size:10px;font-weight:700;letter-spacing:.1em;color:var(--text-muted);text-transform:uppercase;margin-bottom:10px">By Location</p>
                    <RouterLink v-for="city in propertyCities" :key="city" :to="'/properties?city='+city" @click="showProps=false"
                      style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;font-size:12px;font-weight:500;color:var(--text-secondary);text-decoration:none;transition:all .15s;margin-bottom:4px"
                      class="mega-item">
                      <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="#a78bfa" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                      {{ city }}
                    </RouterLink>
                    <RouterLink to="/properties" @click="showProps=false" style="display:flex;align-items:center;gap:6px;margin-top:14px;padding:10px 14px;border-radius:10px;font-size:13px;font-weight:600;color:var(--accent-violet);text-decoration:none;background:rgba(167,139,250,0.08);border:1px solid rgba(167,139,250,0.15)">
                      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/></svg>
                      View All Properties →
                    </RouterLink>
                  </div>
                </div>
              </div>
            </div>

            <!-- Other links -->
            <RouterLink to="/rentals" style="padding:8px 14px;border-radius:10px;font-size:13px;font-weight:600;text-decoration:none;transition:all .2s"
              :style="$route.path.startsWith('/rentals') ? 'color:var(--accent-cyan);background:rgba(86,216,255,0.08)' : 'color:var(--text-secondary)'">Rentals</RouterLink>
            <RouterLink to="/market-insights" style="padding:8px 14px;border-radius:10px;font-size:13px;font-weight:600;text-decoration:none;transition:all .2s"
              :style="$route.path.startsWith('/market') ? 'color:var(--accent-cyan);background:rgba(86,216,255,0.08)' : 'color:var(--text-secondary)'">Market</RouterLink>
            <RouterLink to="/pricing" style="padding:8px 14px;border-radius:10px;font-size:13px;font-weight:600;text-decoration:none;transition:all .2s"
              :style="$route.path.startsWith('/pricing') ? 'color:var(--accent-teal);background:rgba(111,255,210,0.08)' : 'color:var(--text-secondary)'">Pricing</RouterLink>
          </div>

          <!-- Auth + Mobile -->
          <div style="display:flex;align-items:center;gap:10px">
            <ThemeToggle :size="36" />
            <template v-if="auth.isAuthenticated">
              <RouterLink :to="auth.homeRoute" class="hidden md:flex" style="align-items:center;gap:6px;padding:8px 14px;border-radius:10px;font-size:13px;font-weight:600;color:var(--text-secondary);text-decoration:none;transition:all .2s;background:var(--surface-overlay);border:1px solid var(--border-subtle)">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Dashboard
              </RouterLink>
              <button @click="handleLogout" class="hidden md:block" style="padding:8px 14px;border-radius:10px;font-size:13px;font-weight:600;color:var(--text-muted);background:none;border:none;cursor:pointer">Logout</button>
            </template>
            <template v-else>
              <RouterLink to="/login" class="hidden md:block" style="padding:8px 14px;border-radius:10px;font-size:13px;font-weight:600;color:var(--text-secondary);text-decoration:none;transition:all .2s">Sign In</RouterLink>
              <RouterLink to="/register" class="hidden md:flex" style="align-items:center;gap:6px;padding:8px 18px;border-radius:10px;font-size:13px;font-weight:700;background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent);text-decoration:none">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                Get Started
              </RouterLink>
            </template>
            <!-- Mobile hamburger -->
            <button @click="mobileOpen=!mobileOpen" class="md:hidden" :aria-label="mobileOpen ? 'Close menu' : 'Open menu'" :aria-expanded="mobileOpen" style="padding:8px;border-radius:8px;background:var(--border-subtle);border:1px solid var(--border-strong);cursor:pointer">
              <svg v-if="!mobileOpen" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="var(--text-primary)" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
              <svg v-else width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="var(--text-primary)" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Mobile Menu -->
      <div v-show="mobileOpen" style="border-top:1px solid var(--border-subtle);padding:12px 16px;display:flex;flex-direction:column;gap:4px" class="md:hidden">
        <RouterLink to="/cars" @click="mobileOpen=false" style="display:flex;align-items:center;gap:10px;padding:12px;border-radius:10px;text-decoration:none;font-size:14px;font-weight:600;color:var(--text-primary)">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#6fffd2" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 6H5.5A2.5 2.5 0 003 8.5v2A1.5 1.5 0 004.5 12h15a1.5 1.5 0 001.5-1.5v-2A2.5 2.5 0 0018.5 6H16m-8 0l2-3h4l2 3M8 6h8M6 12v5a1 1 0 001 1h1m8-6v5a1 1 0 01-1 1h-1m-6 0h6"/></svg>
          Cars
        </RouterLink>
        <RouterLink to="/properties" @click="mobileOpen=false" style="display:flex;align-items:center;gap:10px;padding:12px;border-radius:10px;text-decoration:none;font-size:14px;font-weight:600;color:var(--text-primary)">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#a78bfa" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
          Properties
        </RouterLink>
        <RouterLink to="/rentals" @click="mobileOpen=false" style="display:flex;align-items:center;gap:10px;padding:12px;border-radius:10px;text-decoration:none;font-size:14px;font-weight:600;color:var(--text-primary)">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#56d8ff" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
          Rentals
        </RouterLink>
        <RouterLink to="/market-insights" @click="mobileOpen=false" style="padding:12px;border-radius:10px;text-decoration:none;font-size:14px;font-weight:600;color:var(--text-primary)">Market Insights</RouterLink>
        <RouterLink to="/pricing" @click="mobileOpen=false" style="padding:12px;border-radius:10px;text-decoration:none;font-size:14px;font-weight:600;color:var(--text-primary)">Pricing</RouterLink>
        <div style="height:1px;background:var(--border-subtle);margin:8px 0"></div>
        <template v-if="auth.isAuthenticated">
          <RouterLink :to="auth.homeRoute" @click="mobileOpen=false" style="padding:12px;border-radius:10px;text-decoration:none;font-size:14px;font-weight:600;color:var(--accent-teal)">Dashboard</RouterLink>
          <button @click="handleLogout" style="text-align:left;padding:12px;border-radius:10px;font-size:14px;font-weight:600;color:#ef4444;background:none;border:none;cursor:pointer">Logout</button>
        </template>
        <template v-else>
          <RouterLink to="/login" @click="mobileOpen=false" style="padding:12px;border-radius:10px;text-decoration:none;font-size:14px;font-weight:600;color:var(--text-secondary)">Sign In</RouterLink>
          <RouterLink to="/register" @click="mobileOpen=false" style="padding:12px;border-radius:10px;text-decoration:none;font-size:14px;font-weight:700;color:var(--on-accent);background:linear-gradient(135deg,#6fffd2,#56d8ff);text-align:center">Get Started</RouterLink>
        </template>
      </div>
    </nav>

    <!-- Content -->
    <main style="flex:1;position:relative;z-index:10" class="pb-16 md:pb-0">
      <slot />
    </main>

    <!-- Footer -->
    <footer class="pb-16 md:pb-0" style="position:relative;z-index:10;border-top:1px solid var(--border-subtle);margin-top:80px">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <!-- Inline grid-template-columns used to override the responsive classes and forced 5 columns on phones. -->
        <div class="footer-grid grid grid-cols-2 gap-8 mb-12">
          <div class="col-span-2 md:col-span-1">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
              <BrandLogo :size="28" />
            </div>
            <p style="font-size:13px;line-height:1.7;color:var(--text-muted);margin-bottom:20px">AI-powered price intelligence for North Cyprus real estate and automotive markets.</p>
            <div style="display:flex;gap:8px">
              <span style="width:8px;height:8px;border-radius:50%;background:#6fffd2;display:inline-block;animation:pulse 2s infinite"></span>
              <span style="font-size:12px;color:var(--text-muted)">Live AI pricing active</span>
            </div>
          </div>
          <div>
            <p style="font-size:11px;font-weight:700;letter-spacing:.1em;color:#4a6280;text-transform:uppercase;margin-bottom:12px">Vehicles</p>
            <div style="display:flex;flex-direction:column;gap:8px">
              <RouterLink to="/cars" style="font-size:13px;color:var(--text-muted);text-decoration:none;transition:.15s" class="footer-link">All Cars</RouterLink>
              <RouterLink to="/cars?body=SUV" style="font-size:13px;color:var(--text-muted);text-decoration:none;transition:.15s" class="footer-link">SUVs</RouterLink>
              <RouterLink to="/cars?body=Sedan" style="font-size:13px;color:var(--text-muted);text-decoration:none;transition:.15s" class="footer-link">Sedans</RouterLink>
              <RouterLink to="/cars?fuel=Electric" style="font-size:13px;color:var(--text-muted);text-decoration:none;transition:.15s" class="footer-link">Electric</RouterLink>
            </div>
          </div>
          <div>
            <p style="font-size:11px;font-weight:700;letter-spacing:.1em;color:#4a6280;text-transform:uppercase;margin-bottom:12px">Properties</p>
            <div style="display:flex;flex-direction:column;gap:8px">
              <RouterLink to="/properties" style="font-size:13px;color:var(--text-muted);text-decoration:none" class="footer-link">All Properties</RouterLink>
              <RouterLink to="/properties?type=Villa" style="font-size:13px;color:var(--text-muted);text-decoration:none" class="footer-link">Villas</RouterLink>
              <RouterLink to="/properties?type=Apartment" style="font-size:13px;color:var(--text-muted);text-decoration:none" class="footer-link">Apartments</RouterLink>
              <RouterLink to="/properties?for=rent" style="font-size:13px;color:var(--text-muted);text-decoration:none" class="footer-link">For Rent</RouterLink>
            </div>
          </div>
          <div>
            <p style="font-size:11px;font-weight:700;letter-spacing:.1em;color:#4a6280;text-transform:uppercase;margin-bottom:12px">Company</p>
            <div style="display:flex;flex-direction:column;gap:8px">
              <RouterLink to="/about" style="font-size:13px;color:var(--text-muted);text-decoration:none" class="footer-link">About</RouterLink>
              <RouterLink to="/blog" style="font-size:13px;color:var(--text-muted);text-decoration:none" class="footer-link">Blog</RouterLink>
              <RouterLink to="/contact" style="font-size:13px;color:var(--text-muted);text-decoration:none" class="footer-link">Contact</RouterLink>
              <RouterLink to="/faq" style="font-size:13px;color:var(--text-muted);text-decoration:none" class="footer-link">FAQ</RouterLink>
              <RouterLink to="/pricing" style="font-size:13px;color:var(--text-muted);text-decoration:none" class="footer-link">Pricing</RouterLink>
            </div>
          </div>
          <div>
            <p style="font-size:11px;font-weight:700;letter-spacing:.1em;color:#4a6280;text-transform:uppercase;margin-bottom:12px">Sell</p>
            <div style="display:flex;flex-direction:column;gap:8px">
              <RouterLink to="/register" style="font-size:13px;color:var(--text-muted);text-decoration:none" class="footer-link">Create Account</RouterLink>
              <RouterLink to="/dashboard/listings/new" style="font-size:13px;color:var(--text-muted);text-decoration:none" class="footer-link">Post Listing</RouterLink>
              <RouterLink to="/pricing" style="font-size:13px;color:var(--text-muted);text-decoration:none" class="footer-link">Subscription Plans</RouterLink>
            </div>
          </div>
        </div>
        <div style="border-top:1px solid var(--border-subtle);padding-top:24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
          <p style="font-size:12px;color:#4a6280">© 2026 AIPrice.app — North Cyprus AI Marketplace</p>
          <p style="font-size:12px;color:#4a6280">Powered by AI price intelligence</p>
        </div>
      </div>
    </footer>
    <FloatingAI />
    <BottomTabBar />
  </div>
</template>
<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import BottomTabBar from '@/components/BottomTabBar.vue'
import { useAuthStore } from '@/stores/auth'
import AnimatedBackground from '@/components/AnimatedBackground.vue'
import FloatingAI from '@/components/FloatingAI.vue'
import BrandLogo from '@/components/BrandLogo.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const showCars = ref(false)
const showProps = ref(false)
const mobileOpen = ref(false)

const carBodyTypes = [
  { label: 'Sedans', sub: 'Classic 4-door style', to: '/cars?body=Sedan', rgb: '111,255,210', icon: 'M8 6H5.5A2.5 2.5 0 003 8.5v2A1.5 1.5 0 004.5 12h15a1.5 1.5 0 001.5-1.5v-2A2.5 2.5 0 0018.5 6H16m-8 0l2-3h4l2 3M8 6h8M6 12v5a1 1 0 001 1h1m8-6v5a1 1 0 01-1 1h-1m-6 0h6' },
  { label: 'SUVs', sub: 'Sport utility vehicles', to: '/cars?body=SUV', rgb: '86,216,255', icon: 'M9 17a2 2 0 11-4 0 2 2 0 014 0zm10 0a2 2 0 11-4 0 2 2 0 014 0zM3 8h2l1.5-4h11L19 8h2v6H3V8z' },
  { label: 'Hatchbacks', sub: 'Compact & practical', to: '/cars?body=Hatchback', rgb: '167,139,250', icon: 'M7 6H5.5A2.5 2.5 0 003 8.5v2A1.5 1.5 0 004.5 12h15a1.5 1.5 0 001.5-1.5v-2A2.5 2.5 0 0018.5 6H7zm0 0l2-3h6l2 3M5 12v4h14v-4' },
  { label: 'Electric', sub: 'Zero emission vehicles', to: '/cars?fuel=Electric', rgb: '251,191,36', icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
]

const popularBrands = ['BMW', 'Mercedes', 'Toyota', 'Honda', 'Audi', 'Volkswagen', 'Ford', 'Hyundai']

const propTypes = [
  { label: 'Apartments', sub: 'City & seaside flats', to: '/properties?type=Apartment', rgb: '111,255,210', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
  { label: 'Villas', sub: 'Luxury standalone homes', to: '/properties?type=Villa', rgb: '167,139,250', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
  { label: 'Houses', sub: 'Family homes & cottages', to: '/properties?type=House', rgb: '86,216,255', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0h6' },
  { label: 'Land', sub: 'Plots & development land', to: '/properties?type=Land', rgb: '251,191,36', icon: 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7' },
]

const propertyCities = ['Girne', 'Lefkosa', 'Gazimağusa', 'Iskele', 'Guzelyurt']

async function handleLogout() {
  await auth.logout()
  router.push('/')
}
</script>
<style scoped>
.mega-wrapper { position:relative }
.mega-dropdown {
  position:absolute;top:calc(100% + 8px);border-radius:16px;padding:20px;
  background:rgba(8,13,19,0.97);backdrop-filter:blur(24px);
  border:1px solid rgba(111,255,210,0.12);box-shadow:0 24px 64px rgba(0,0,0,0.5);
  z-index:100;animation:fadeDown .15s ease;
}
@keyframes fadeDown { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }
.mega-item:hover { background:var(--surface-overlay) }
.brand-chip:hover { background:var(--border-subtle);color:var(--text-primary);border-color:var(--border-strong) }
.footer-link:hover { color:var(--text-primary) }
@media (min-width: 768px) { .footer-grid { grid-template-columns: 2fr 1fr 1fr 1fr 1fr; } }
</style>
