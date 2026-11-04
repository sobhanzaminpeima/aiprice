<template>
  <div style="min-height:100vh;background:var(--bg-base);color:var(--text-primary);display:flex;flex-direction:column;position:relative">
    <AnimatedBackground />

    <!-- Navbar -->
    <nav class="site-nav" style="background:var(--nav-bg);backdrop-filter:blur(20px);border-bottom:1px solid var(--border-subtle);position:sticky;top:0;z-index:50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div style="display:flex;align-items:center;justify-content:space-between;height:64px">

          <!-- Logo -->
          <RouterLink to="/" style="display:flex;align-items:center;gap:10px;text-decoration:none">
            <BrandLogo :size="30" />
          </RouterLink>

          <!-- Desktop Mega Nav -->
          <div class="hidden md:flex" style="align-items:center;gap:2px">

            <!-- Cars Mega Menu -->
            <div class="mega-wrapper" @mouseenter="openMenu('cars')" @mouseleave="scheduleClose('cars')" @focusin="openMenu('cars')" @focusout="onFocusOut($event, 'cars')" @keydown.esc="closeMenu(true)">
              <button type="button" aria-haspopup="true" :aria-expanded="showCars" aria-controls="menu-cars" @click="toggleMenu('cars')" style="display:flex;align-items:center;gap:6px;padding:8px 14px;border-radius:10px;font-size:13px;font-weight:600;background:none;border:none;cursor:pointer;transition:all .2s"
                :style="$route.path.startsWith('/cars') ? 'color:var(--accent-teal);background:rgba(var(--accent-teal-rgb),0.08)' : 'color:var(--text-secondary)'">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M8 6H5.5A2.5 2.5 0 003 8.5v2A1.5 1.5 0 004.5 12h15a1.5 1.5 0 001.5-1.5v-2A2.5 2.5 0 0018.5 6H16m-8 0l2-3h4l2 3M8 6h8M6 12v5a1 1 0 001 1h1m8-6v5a1 1 0 01-1 1h-1m-6 0h6"/>
                </svg>
                {{ $t('Cars') }}
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="transition:.2s" :style="showCars?'transform:rotate(180deg)':''"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
              </button>
              <!-- Cars Dropdown -->
              <div v-show="showCars" :id="'menu-cars'" class="mega-dropdown" style="left:0;width:520px">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
                  <div>
                    <p style="font-size:10px;font-weight:700;letter-spacing:.1em;color:var(--text-muted);text-transform:uppercase;margin-bottom:10px">{{ $t('By Body Type') }}</p>
                    <RouterLink v-for="bt in carBodyTypes" :key="bt.label" :to="bt.to" @click="closeMenu()"
                      style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:10px;text-decoration:none;transition:all .15s;margin-bottom:2px"
                      class="mega-item">
                      <div style="width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0" :style="'background:rgba('+bt.rgb+',0.12)'">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="1.6" :style="'stroke:rgb('+bt.rgb+')'"><path stroke-linecap="round" stroke-linejoin="round" :d="bt.icon"/></svg>
                      </div>
                      <div>
                        <p style="font-size:13px;font-weight:600;color:var(--text-primary)">{{ $t(bt.label) }}</p>
                        <p style="font-size:11px;color:var(--text-muted)">{{ $t(bt.sub) }}</p>
                      </div>
                    </RouterLink>
                  </div>
                  <div>
                    <p style="font-size:10px;font-weight:700;letter-spacing:.1em;color:var(--text-muted);text-transform:uppercase;margin-bottom:10px">{{ $t('Popular Brands') }}</p>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px">
                      <RouterLink v-for="b in popularBrands" :key="b" :to="'/cars?brand='+b" @click="closeMenu()"
                        style="padding:8px 12px;border-radius:8px;font-size:12px;font-weight:500;color:var(--text-secondary);text-decoration:none;transition:all .15s;border:1px solid var(--border-subtle)"
                        class="brand-chip">{{ b }}</RouterLink>
                    </div>
                    <RouterLink to="/cars" @click="closeMenu()" style="display:flex;align-items:center;gap:6px;margin-top:14px;padding:10px 14px;border-radius:10px;font-size:13px;font-weight:600;color:var(--accent-teal);text-decoration:none;background:rgba(var(--accent-teal-rgb),0.08);border:1px solid rgba(var(--accent-teal-rgb),0.15)">
                      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/></svg>
                      {{ $t('View All Cars →') }}
                    </RouterLink>
                  </div>
                </div>
              </div>
            </div>

            <!-- Properties Mega Menu -->
            <div class="mega-wrapper" @mouseenter="openMenu('props')" @mouseleave="scheduleClose('props')" @focusin="openMenu('props')" @focusout="onFocusOut($event, 'props')" @keydown.esc="closeMenu(true)">
              <button type="button" aria-haspopup="true" :aria-expanded="showProps" aria-controls="menu-props" @click="toggleMenu('props')" style="display:flex;align-items:center;gap:6px;padding:8px 14px;border-radius:10px;font-size:13px;font-weight:600;background:none;border:none;cursor:pointer;transition:all .2s"
                :style="$route.path.startsWith('/properties') ? 'color:var(--accent-violet);background:rgba(var(--accent-violet-rgb),0.08)' : 'color:var(--text-secondary)'">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                {{ $t('Properties') }}
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="transition:.2s" :style="showProps?'transform:rotate(180deg)':''"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
              </button>
              <!-- Properties Dropdown -->
              <div v-show="showProps" :id="'menu-props'" class="mega-dropdown" style="left:0;width:480px">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
                  <div>
                    <p style="font-size:10px;font-weight:700;letter-spacing:.1em;color:var(--text-muted);text-transform:uppercase;margin-bottom:10px">{{ $t('Property Types') }}</p>
                    <RouterLink v-for="pt in propTypes" :key="pt.label" :to="pt.to" @click="closeMenu()"
                      style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:10px;text-decoration:none;transition:all .15s;margin-bottom:2px"
                      class="mega-item">
                      <div style="width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0" :style="'background:rgba('+pt.rgb+',0.12)'">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="1.6" :style="'stroke:rgb('+pt.rgb+')'"><path stroke-linecap="round" stroke-linejoin="round" :d="pt.icon"/></svg>
                      </div>
                      <div>
                        <p style="font-size:13px;font-weight:600;color:var(--text-primary)">{{ $t(pt.label) }}</p>
                        <p style="font-size:11px;color:var(--text-muted)">{{ $t(pt.sub) }}</p>
                      </div>
                    </RouterLink>
                  </div>
                  <div>
                    <p style="font-size:10px;font-weight:700;letter-spacing:.1em;color:var(--text-muted);text-transform:uppercase;margin-bottom:10px">{{ $t('By Location') }}</p>
                    <RouterLink v-for="city in propertyCities" :key="city" :to="'/properties?city='+city" @click="closeMenu()"
                      style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;font-size:12px;font-weight:500;color:var(--text-secondary);text-decoration:none;transition:all .15s;margin-bottom:4px"
                      class="mega-item">
                      <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-tone="violet" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                      {{ city }}
                    </RouterLink>
                    <RouterLink to="/properties" @click="closeMenu()" style="display:flex;align-items:center;gap:6px;margin-top:14px;padding:10px 14px;border-radius:10px;font-size:13px;font-weight:600;color:var(--accent-violet);text-decoration:none;background:rgba(var(--accent-violet-rgb),0.08);border:1px solid rgba(var(--accent-violet-rgb),0.15)">
                      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/></svg>
                      {{ $t('View All Properties →') }}
                    </RouterLink>
                  </div>
                </div>
              </div>
            </div>

            <!-- Other links -->
            <RouterLink to="/rentals" style="padding:8px 14px;border-radius:10px;font-size:13px;font-weight:600;text-decoration:none;transition:all .2s"
              :style="$route.path.startsWith('/rentals') ? 'color:var(--accent-cyan);background:rgba(var(--accent-cyan-rgb),0.08)' : 'color:var(--text-secondary)'">{{ $t('Rentals') }}</RouterLink>
            <RouterLink class="hidden lg:inline-block" to="/market-insights" style="padding:8px 14px;border-radius:10px;font-size:13px;font-weight:600;text-decoration:none;transition:all .2s"
              :style="$route.path.startsWith('/market') ? 'color:var(--accent-cyan);background:rgba(var(--accent-cyan-rgb),0.08)' : 'color:var(--text-secondary)'">{{ $t('Market') }}</RouterLink>
            <RouterLink class="hidden lg:inline-block" to="/pricing" style="padding:8px 14px;border-radius:10px;font-size:13px;font-weight:600;text-decoration:none;transition:all .2s"
              :style="$route.path.startsWith('/pricing') ? 'color:var(--accent-teal);background:rgba(var(--accent-teal-rgb),0.08)' : 'color:var(--text-secondary)'">{{ $t('Pricing') }}</RouterLink>
          </div>

          <!-- Auth + Mobile -->
          <div style="display:flex;align-items:center;gap:10px">
            <RouterLink :to="auth.isAuthenticated ? '/dashboard/listings/new' : { name: 'register', query: { redirect: '/dashboard/listings/new' } }" class="hidden lg:flex post-ad">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
              {{ $t('Post an ad') }}
            </RouterLink>
            <LanguageSwitcher class="hidden md:inline-flex" />
            <ThemeToggle :size="36" />
            <template v-if="auth.isAuthenticated">
              <RouterLink :to="auth.homeRoute" class="hidden md:flex" style="align-items:center;gap:6px;padding:8px 14px;border-radius:10px;font-size:13px;font-weight:600;color:var(--text-secondary);text-decoration:none;transition:all .2s;background:var(--surface-overlay);border:1px solid var(--border-subtle)">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                {{ $t('Dashboard') }}
              </RouterLink>
              <button @click="handleLogout" class="hidden md:block" style="padding:8px 14px;border-radius:10px;font-size:13px;font-weight:600;color:var(--text-muted);background:none;border:none;cursor:pointer">{{ $t('Logout') }}</button>
            </template>
            <template v-else>
              <RouterLink to="/login" class="hidden md:block" style="padding:8px 14px;border-radius:10px;font-size:13px;font-weight:600;color:var(--text-secondary);text-decoration:none;transition:all .2s">{{ $t('Sign In') }}</RouterLink>
              <RouterLink to="/register" class="hidden md:flex" style="align-items:center;gap:6px;padding:8px 18px;border-radius:10px;font-size:13px;font-weight:700;background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent);text-decoration:none">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                {{ $t('Get Started') }}
              </RouterLink>
            </template>
            <!-- Mobile hamburger -->
            <button @click="mobileOpen=!mobileOpen" class="md:hidden" :aria-label="mobileOpen ? $t('Close menu') : $t('Open menu')" :aria-expanded="mobileOpen" style="padding:8px;border-radius:8px;background:var(--border-subtle);border:1px solid var(--border-strong);cursor:pointer">
              <svg v-if="!mobileOpen" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color:var(--text-primary)" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
              <svg v-else width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color:var(--text-primary)" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Mobile Menu -->
      <div v-show="mobileOpen" style="border-top:1px solid var(--border-subtle);padding:12px 16px;display:flex;flex-direction:column;gap:4px" class="md:hidden">
        <RouterLink to="/cars" @click="mobileOpen=false" style="display:flex;align-items:center;gap:10px;padding:12px;border-radius:10px;text-decoration:none;font-size:14px;font-weight:600;color:var(--text-primary)">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-tone="teal" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 6H5.5A2.5 2.5 0 003 8.5v2A1.5 1.5 0 004.5 12h15a1.5 1.5 0 001.5-1.5v-2A2.5 2.5 0 0018.5 6H16m-8 0l2-3h4l2 3M8 6h8M6 12v5a1 1 0 001 1h1m8-6v5a1 1 0 01-1 1h-1m-6 0h6"/></svg>
          {{ $t('Cars') }}
        </RouterLink>
        <RouterLink to="/properties" @click="mobileOpen=false" style="display:flex;align-items:center;gap:10px;padding:12px;border-radius:10px;text-decoration:none;font-size:14px;font-weight:600;color:var(--text-primary)">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-tone="violet" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
          {{ $t('Properties') }}
        </RouterLink>
        <RouterLink to="/rentals" @click="mobileOpen=false" style="display:flex;align-items:center;gap:10px;padding:12px;border-radius:10px;text-decoration:none;font-size:14px;font-weight:600;color:var(--text-primary)">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-tone="cyan" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
          {{ $t('Rentals') }}
        </RouterLink>
        <RouterLink to="/market-insights" @click="mobileOpen=false" style="padding:12px;border-radius:10px;text-decoration:none;font-size:14px;font-weight:600;color:var(--text-primary)">{{ $t('Market Insights') }}</RouterLink>
        <RouterLink to="/pricing" @click="mobileOpen=false" style="padding:12px;border-radius:10px;text-decoration:none;font-size:14px;font-weight:600;color:var(--text-primary)">{{ $t('Pricing') }}</RouterLink>
        <div style="padding:8px 12px"><LanguageSwitcher /></div>
        <div style="height:1px;background:var(--border-subtle);margin:8px 0"></div>
        <template v-if="auth.isAuthenticated">
          <RouterLink :to="auth.homeRoute" @click="mobileOpen=false" style="padding:12px;border-radius:10px;text-decoration:none;font-size:14px;font-weight:600;color:var(--accent-teal)">{{ $t('Dashboard') }}</RouterLink>
          <button @click="handleLogout" style="text-align:left;padding:12px;border-radius:10px;font-size:14px;font-weight:600;color:var(--danger);background:none;border:none;cursor:pointer">{{ $t('Logout') }}</button>
        </template>
        <template v-else>
          <RouterLink to="/login" @click="mobileOpen=false" style="padding:12px;border-radius:10px;text-decoration:none;font-size:14px;font-weight:600;color:var(--text-secondary)">{{ $t('Sign In') }}</RouterLink>
          <RouterLink to="/register" @click="mobileOpen=false" style="padding:12px;border-radius:10px;text-decoration:none;font-size:14px;font-weight:700;color:var(--on-accent);background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));text-align:center">{{ $t('Get Started') }}</RouterLink>
        </template>
      </div>
    </nav>

    <!-- Content -->
    <main style="flex:1;position:relative;z-index:10" class="pb-16 md:pb-0">
      <slot />
    </main>

    <!-- Footer -->
    <footer class="site-footer pb-16 md:pb-0" style="position:relative;z-index:10;margin-top:80px">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <!-- Inline grid-template-columns used to override the responsive classes and forced 5 columns on phones. -->
        <div class="footer-grid grid grid-cols-2 gap-8 mb-12">
          <div class="col-span-2 md:col-span-1">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
              <BrandLogo :size="28" />
            </div>
            <p style="font-size:13px;line-height:1.7;margin-bottom:12px">{{ $t('AI price estimates for cars and property in North Cyprus.') }}</p>
            <p class="footer-tagline">{{ $t('Know the Value. Not Just the Price.') }}</p>
            <div class="footer-lang"><LanguageSwitcher variant="footer" /></div>
          </div>
          <div>
            <p style="font-size:11px;font-weight:700;letter-spacing:.1em;color:var(--text-muted);text-transform:uppercase;margin-bottom:12px">{{ $t('Vehicles') }}</p>
            <div style="display:flex;flex-direction:column;gap:8px">
              <RouterLink to="/cars" style="font-size:13px;color:var(--text-muted);text-decoration:none;transition:.15s" class="footer-link">{{ $t('All Cars') }}</RouterLink>
              <RouterLink to="/cars?body=SUV" style="font-size:13px;color:var(--text-muted);text-decoration:none;transition:.15s" class="footer-link">{{ $t('SUVs') }}</RouterLink>
              <RouterLink to="/cars?body=Sedan" style="font-size:13px;color:var(--text-muted);text-decoration:none;transition:.15s" class="footer-link">{{ $t('Sedans') }}</RouterLink>
              <RouterLink to="/cars?fuel=Electric" style="font-size:13px;color:var(--text-muted);text-decoration:none;transition:.15s" class="footer-link">{{ $t('Electric') }}</RouterLink>
            </div>
          </div>
          <div>
            <p style="font-size:11px;font-weight:700;letter-spacing:.1em;color:var(--text-muted);text-transform:uppercase;margin-bottom:12px">{{ $t('Properties') }}</p>
            <div style="display:flex;flex-direction:column;gap:8px">
              <RouterLink to="/properties" style="font-size:13px;color:var(--text-muted);text-decoration:none" class="footer-link">{{ $t('All Properties') }}</RouterLink>
              <RouterLink to="/properties?type=Villa" style="font-size:13px;color:var(--text-muted);text-decoration:none" class="footer-link">{{ $t('Villas') }}</RouterLink>
              <RouterLink to="/properties?type=Apartment" style="font-size:13px;color:var(--text-muted);text-decoration:none" class="footer-link">{{ $t('Apartments') }}</RouterLink>
              <RouterLink to="/rentals" style="font-size:13px;color:var(--text-muted);text-decoration:none" class="footer-link">{{ $t('For Rent') }}</RouterLink>
            </div>
          </div>
          <div>
            <p style="font-size:11px;font-weight:700;letter-spacing:.1em;color:var(--text-muted);text-transform:uppercase;margin-bottom:12px">{{ $t('Company') }}</p>
            <div style="display:flex;flex-direction:column;gap:8px">
              <RouterLink to="/about" style="font-size:13px;color:var(--text-muted);text-decoration:none" class="footer-link">{{ $t('About') }}</RouterLink>
              <RouterLink to="/blog" style="font-size:13px;color:var(--text-muted);text-decoration:none" class="footer-link">{{ $t('Blog') }}</RouterLink>
              <RouterLink to="/contact" style="font-size:13px;color:var(--text-muted);text-decoration:none" class="footer-link">{{ $t('Contact') }}</RouterLink>
              <RouterLink to="/faq" style="font-size:13px;color:var(--text-muted);text-decoration:none" class="footer-link">{{ $t('FAQ') }}</RouterLink>
              <RouterLink to="/privacy" style="font-size:13px;color:var(--text-muted);text-decoration:none" class="footer-link">{{ $t('Privacy') }}</RouterLink>
              <RouterLink to="/terms" style="font-size:13px;color:var(--text-muted);text-decoration:none" class="footer-link">{{ $t('Terms') }}</RouterLink>
              <RouterLink to="/pricing" style="font-size:13px;color:var(--text-muted);text-decoration:none" class="footer-link">{{ $t('Pricing') }}</RouterLink>
            </div>
          </div>
          <div>
            <p style="font-size:11px;font-weight:700;letter-spacing:.1em;color:var(--text-muted);text-transform:uppercase;margin-bottom:12px">{{ $t('Sell') }}</p>
            <div style="display:flex;flex-direction:column;gap:8px">
              <RouterLink to="/register" style="font-size:13px;color:var(--text-muted);text-decoration:none" class="footer-link">{{ $t('Create Account') }}</RouterLink>
              <RouterLink to="/dashboard/listings/new" style="font-size:13px;color:var(--text-muted);text-decoration:none" class="footer-link">{{ $t('Post an ad') }}</RouterLink>
              <RouterLink to="/pricing" style="font-size:13px;color:var(--text-muted);text-decoration:none" class="footer-link">{{ $t('Subscription Plans') }}</RouterLink>
            </div>
          </div>
        </div>
        <div style="border-top:1px solid var(--border-subtle);padding-top:24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
          <p style="font-size:12px">{{ $t('© 2026 AIPrice.app · North Cyprus') }}</p>
          <p style="font-size:12px">{{ $t('Prices shown by AI are estimates, not valuations or guarantees.') }}</p>
        </div>
      </div>
    </footer>
    <FloatingAI />
    <BottomTabBar />
  </div>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import BottomTabBar from '@/components/BottomTabBar.vue'
import { useAuthStore } from '@/stores/auth'
import AnimatedBackground from '@/components/AnimatedBackground.vue'
import FloatingAI from '@/components/FloatingAI.vue'
import BrandLogo from '@/components/BrandLogo.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'
import LanguageSwitcher from '@/components/LanguageSwitcher.vue'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

type MenuKey = 'cars' | 'props'
const activeMenu = ref<MenuKey | null>(null)
const showCars = computed(() => activeMenu.value === 'cars')
const showProps = computed(() => activeMenu.value === 'props')
let closeTimer: ReturnType<typeof setTimeout> | null = null
// Pointer leaving the trigger must not close the menu instantly: the user is
// usually travelling diagonally to an option. A short grace period plus the
// invisible hover bridge in CSS keeps the dropdown reachable.
const CLOSE_DELAY_MS = 220

function cancelClose() {
  if (closeTimer) { clearTimeout(closeTimer); closeTimer = null }
}
let openedAt = 0
function openMenu(key: MenuKey) {
  cancelClose()
  if (activeMenu.value !== key) openedAt = Date.now()
  activeMenu.value = key
}
function scheduleClose(key: MenuKey) {
  cancelClose()
  closeTimer = setTimeout(() => { if (activeMenu.value === key) activeMenu.value = null }, CLOSE_DELAY_MS)
}
function closeMenu(restoreFocus = false) {
  cancelClose()
  const key = activeMenu.value
  activeMenu.value = null
  if (restoreFocus && key) {
    (document.querySelector(`[aria-controls="menu-${key}"]`) as HTMLElement | null)?.focus()
  }
}
// Click/tap toggles, so touch devices (no hover) can open and close it.
// Hover/focus already opened it moments before the click lands, so ignore a
// "close" toggle that arrives right after opening (tap = focus + click).
function toggleMenu(key: MenuKey) {
  cancelClose()
  if (activeMenu.value === key) {
    if (Date.now() - openedAt > 400) activeMenu.value = null
  } else {
    openMenu(key)
  }
}
function onFocusOut(e: FocusEvent, key: MenuKey) {
  const wrapper = e.currentTarget as HTMLElement
  if (!wrapper.contains(e.relatedTarget as Node | null)) scheduleClose(key)
}
function onDocPointer(e: PointerEvent) {
  if (activeMenu.value && !(e.target as HTMLElement)?.closest?.('.mega-wrapper')) closeMenu()
}
onMounted(() => document.addEventListener('pointerdown', onDocPointer))
onBeforeUnmount(() => { document.removeEventListener('pointerdown', onDocPointer); cancelClose() })
watch(() => route.fullPath, () => { closeMenu(); mobileOpen.value = false })
const mobileOpen = ref(false)

const carBodyTypes = [
  { label: 'Sedans', sub: 'Classic 4-door style', to: '/cars?body=Sedan', rgb: 'var(--accent-teal-rgb)', icon: 'M8 6H5.5A2.5 2.5 0 003 8.5v2A1.5 1.5 0 004.5 12h15a1.5 1.5 0 001.5-1.5v-2A2.5 2.5 0 0018.5 6H16m-8 0l2-3h4l2 3M8 6h8M6 12v5a1 1 0 001 1h1m8-6v5a1 1 0 01-1 1h-1m-6 0h6' },
  { label: 'SUVs', sub: 'Sport utility vehicles', to: '/cars?body=SUV', rgb: 'var(--accent-cyan-rgb)', icon: 'M9 17a2 2 0 11-4 0 2 2 0 014 0zm10 0a2 2 0 11-4 0 2 2 0 014 0zM3 8h2l1.5-4h11L19 8h2v6H3V8z' },
  { label: 'Hatchbacks', sub: 'Compact & practical', to: '/cars?body=Hatchback', rgb: 'var(--accent-violet-rgb)', icon: 'M7 6H5.5A2.5 2.5 0 003 8.5v2A1.5 1.5 0 004.5 12h15a1.5 1.5 0 001.5-1.5v-2A2.5 2.5 0 0018.5 6H7zm0 0l2-3h6l2 3M5 12v4h14v-4' },
  { label: 'Electric', sub: 'Zero emission vehicles', to: '/cars?fuel=Electric', rgb: 'var(--warning-rgb)', icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
]

const popularBrands = ['BMW', 'Mercedes', 'Toyota', 'Honda', 'Audi', 'Volkswagen', 'Ford', 'Hyundai']

const propTypes = [
  { label: 'Apartments', sub: 'City & seaside flats', to: '/properties?type=Apartment', rgb: 'var(--accent-teal-rgb)', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
  { label: 'Villas', sub: 'Luxury standalone homes', to: '/properties?type=Villa', rgb: 'var(--accent-violet-rgb)', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
  { label: 'Townhouses', sub: 'Family homes & terraces', to: '/properties?type=Townhouse', rgb: 'var(--accent-cyan-rgb)', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0h6' },
  { label: 'Land', sub: 'Plots & development land', to: '/properties?type=Land', rgb: 'var(--warning-rgb)', icon: 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7' },
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
  background:var(--dropdown-bg);
  border:1px solid var(--border-subtle);box-shadow:var(--shadow-lg);
  z-index:100;animation:fadeDown .15s ease;
}
/* Invisible bridge over the 8px gap so the pointer never "leaves" the menu
   on its way from the trigger to the options. */
.mega-dropdown::before { content:'';position:absolute;left:0;right:0;top:-12px;height:12px; }
.mega-item:focus-visible, .brand-chip:focus-visible { outline:2px solid var(--focus-ring);outline-offset:1px }
@media (prefers-reduced-motion: reduce) { .mega-dropdown { animation:none } }
@keyframes fadeDown { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }
.mega-item:hover { background:var(--surface-overlay) }
.brand-chip:hover { background:var(--border-subtle);color:var(--text-primary);border-color:var(--border-strong) }
.site-footer { background:#081827;color:#a9bbcf }
.site-footer :deep(.brand-logo-img) { filter:brightness(0) invert(1) }
.site-footer p, .site-footer .footer-link { color:#a9bbcf !important }
.site-footer .footer-grid > div > p:first-child { color:#e6eef7 !important }
.site-footer .footer-link:hover { color:#fff !important }
.site-footer > div > div:last-child { border-top-color:rgba(255,255,255,.12) !important }
.footer-tagline { font-weight:800;font-size:14px;background:linear-gradient(90deg,#4fb8ff,#16C784);-webkit-background-clip:text;background-clip:text;color:transparent !important;margin-bottom:16px }
:root:not([data-theme="dark"]) .site-nav { box-shadow:0 1px 0 rgba(8,24,39,.04),0 4px 16px rgba(8,24,39,.05) }
.post-ad { white-space:nowrap;align-items:center;gap:6px;padding:8px 14px;border-radius:10px;font-size:13px;font-weight:700;text-decoration:none;color:var(--accent-cyan);border:1.5px solid var(--accent-cyan) }
.post-ad:hover { background:rgba(var(--accent-cyan-rgb),0.08) }
@media (min-width: 768px) { .footer-grid { grid-template-columns: 2fr 1fr 1fr 1fr 1fr; } }
</style>
