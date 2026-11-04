<template>
  <MainLayout>
    <!-- HERO -->
    <section class="hero relative overflow-hidden pt-16 sm:pt-20 pb-20 px-4">
      <div class="hero-glow" aria-hidden="true"></div>
      <div class="max-w-7xl mx-auto relative z-10">
        <div class="text-center mb-12">
          <div class="hero-badge inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-semibold mb-6">
            <span class="w-1.5 h-1.5 rounded-full" style="background:#16C784;animation:pulse-glow 2s infinite"></span>
            {{ $t('AI-Powered Marketplace · North Cyprus') }}
          </div>
          <h1 class="hero-title text-4xl sm:text-6xl font-black leading-tight mb-4">{{ $t('Find the best priced cars & properties') }}</h1>
          <p class="hero-tagline text-xl sm:text-2xl font-extrabold mb-4">{{ $t('Know the Value. Not Just the Price.') }}</p>
          <p class="hero-sub text-base sm:text-lg max-w-2xl mx-auto mb-10">
            {{ $t('Instant AI price estimates for cars and property in North Cyprus, based on comparable listings.') }}
          </p>

          <!-- Welcome back — AI Preference Graph memory. Only for signed-in
               users with a real prior search on record. -->
          <div v-if="lastPreference" class="max-w-lg mx-auto mb-6 rounded-2xl p-4 flex items-center justify-between gap-3"
            style="background:rgba(var(--accent-teal-rgb),0.06);border:1px solid rgba(var(--accent-teal-rgb),0.2)">
            <div class="text-left">
              <p class="text-xs font-bold" style="color:var(--text-primary)">{{ $t('Welcome back — your last search') }}</p>
              <p class="text-xs" style="color:var(--text-muted)">
                {{ lastPreference.asset_type }}<span v-if="lastPreference.budget_max"> under {{ Number(lastPreference.budget_max).toLocaleString() }}</span><span v-if="lastPreference.city"> in {{ lastPreference.city }}</span>
                <template v-if="newMatchesCount > 0"> · <strong style="color:var(--accent-teal)">{{ newMatchesCount }} new match{{ newMatchesCount === 1 ? '' : 'es' }}</strong></template>
              </p>
            </div>
            <button @click="rerunLastSearch" class="px-3 py-1.5 rounded-lg text-xs font-bold shrink-0" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">{{ $t('View →') }}</button>
          </div>

          <!-- AI Intent Composer — replaces a plain search bar. Choose what you're
               after, what matters to you, and your budget; AI ranks results
               instead of you scrolling through hundreds of listings. -->
          <div class="composer max-w-2xl mx-auto rounded-3xl p-6">
            <p class="text-sm font-bold mb-3" style="color:var(--text-primary)">{{ $t('I\'m looking for') }}</p>
            <div class="flex flex-wrap justify-center gap-2 mb-5">
              <button v-for="t in assetTypes" :key="t.value" @click="intent.type = t.value"
                class="px-4 py-2 rounded-xl text-sm font-bold transition inline-flex items-center gap-2"
                :style="intent.type === t.value
                  ? 'background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)'
                  : 'background:var(--surface-overlay);color:var(--text-secondary);border:1px solid var(--border-subtle)'">
                <AssetIcon :type="t.value as any" :size="16" /> {{ $t(t.label) }}
              </button>
            </div>

            <p class="text-sm font-bold mb-3" style="color:var(--text-primary)">{{ $t('What\'s important to you?') }}</p>
            <div class="flex flex-wrap justify-center gap-2 mb-5">
              <button v-for="p in priorityOptions" :key="p.value" @click="togglePriority(p.value)"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition"
                :style="intent.priorities.includes(p.value)
                  ? 'background:rgba(var(--accent-teal-rgb),0.16);border:1px solid rgba(var(--accent-teal-rgb),0.5);color:var(--accent-teal)'
                  : 'background:var(--surface-overlay);color:var(--text-muted);border:1px solid var(--border-subtle)'">
                {{ $t(p.label) }}
              </button>
            </div>

            <p class="text-sm font-bold mb-3" style="color:var(--text-primary)">{{ $t('Budget') }}</p>
            <div class="flex flex-wrap justify-center gap-2 mb-5">
              <button v-for="b in budgetOptions" :key="b.label" @click="intent.budgetMax = b.value"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition"
                :style="intent.budgetMax === b.value
                  ? 'background:rgba(var(--accent-cyan-rgb),0.16);border:1px solid rgba(var(--accent-cyan-rgb),0.5);color:var(--accent-cyan)'
                  : 'background:var(--surface-overlay);color:var(--text-muted);border:1px solid var(--border-subtle)'">
                {{ $t(b.label) }}
              </button>
              <input v-if="intent.budgetMax === 'custom'" v-model="intent.budgetCustom" type="number" :placeholder="$t('Max amount')"
                class="px-3 py-1.5 rounded-lg text-xs w-32 outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-subtle);color:var(--text-primary)" />
              <select v-model="intent.city" :aria-label="$t('City')" class="px-3 py-1.5 rounded-lg text-xs outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-subtle);color:var(--text-muted)">
                <option value="">{{ $t('Any City') }}</option>
                <option v-for="c in cities" :key="c" :value="c">{{ c }}</option>
              </select>
            </div>

            <button @click="findMyMatch" class="btn-primary w-full py-3 rounded-xl text-sm font-bold">
              {{ $t('✦ Find My Best Match →') }}
            </button>
          </div>
        </div>

        <!-- Stats bar -->
        <div v-if="activeCount === 0" class="composer max-w-2xl mx-auto rounded-2xl p-5 text-center mt-8">
          <p class="font-bold mb-1" style="color:var(--text-primary)">{{ $t('We\'re just launching in North Cyprus') }}</p>
          <p class="text-sm mb-4" style="color:var(--text-muted)">{{ $t('List your car or property free and get an instant AI price check — early listings get the most visibility.') }}</p>
          <RouterLink :to="sellLink" class="inline-block px-6 py-2.5 rounded-xl text-sm font-bold" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">{{ $t('List for free') }}</RouterLink>
        </div>
        <div v-else-if="activeCount !== null" class="grid grid-cols-2 md:grid-cols-4 gap-4 max-w-3xl mx-auto mt-10">
          <div v-for="s in stats" :key="s.label" class="hero-stat text-center p-4 rounded-2xl">
            <p class="text-2xl font-black mb-1">{{ s.value }}</p>
            <p class="text-xs">{{ $t(s.label) }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- BROWSE BY CATEGORY (bama-style taxonomy: type → body/use → price class → brand) -->
    <section class="px-4 pt-14 pb-16 max-w-7xl mx-auto" aria-labelledby="browse-heading">
      <h2 id="browse-heading" class="text-xl font-black mb-5" style="color:var(--text-primary)">{{ $t('Browse by') }} <span class="gradient-text">{{ $t('category') }}</span></h2>
      <div class="grid gap-6 lg:grid-cols-2">
        <div class="browse-card">
          <div class="browse-head"><AppIcon name="car" :size="18" /> {{ $t('Cars') }}</div>
          <p class="browse-label">{{ $t('Body type') }}</p>
          <div class="tile-grid">
            <RouterLink v-for="b in carBodies" :key="b" :to="{ path: '/cars', query: { body: b } }" class="tile">{{ $t(b) }}</RouterLink>
          </div>
          <p class="browse-label">{{ $t('Price class') }}</p>
          <div class="chip-row">
            <RouterLink v-for="p in carPriceClasses" :key="p.label" :to="{ path: '/cars', query: p.query }" class="chip">{{ $t(p.label) }}</RouterLink>
          </div>
          <p class="browse-label">{{ $t('Popular brands') }}</p>
          <div class="chip-row">
            <RouterLink v-for="b in carBrands" :key="b" :to="{ path: '/cars', query: { brand: b } }" class="chip">{{ b }}</RouterLink>
          </div>
          <p class="browse-label">{{ $t('Quick filters') }}</p>
          <div class="chip-row">
            <RouterLink v-for="q in carQuick" :key="q.label" :to="{ path: '/cars', query: q.query }" class="chip chip--soft">{{ $t(q.label) }}</RouterLink>
          </div>
        </div>
        <div class="browse-card">
          <div class="browse-head"><AppIcon name="home" :size="18" /> {{ $t('Property') }}</div>
          <p class="browse-label">{{ $t('Property type') }}</p>
          <div class="tile-grid">
            <RouterLink v-for="t in propertyKinds" :key="t" :to="{ path: '/properties', query: { type: t } }" class="tile">{{ $t(t) }}</RouterLink>
          </div>
          <p class="browse-label">{{ $t('Price class') }}</p>
          <div class="chip-row">
            <RouterLink v-for="p in propertyPriceClasses" :key="p.label" :to="{ path: '/properties', query: p.query }" class="chip">{{ $t(p.label) }}</RouterLink>
          </div>
          <p class="browse-label">{{ $t('By city') }}</p>
          <div class="chip-row">
            <RouterLink v-for="c in propertyCities" :key="c.q" :to="{ path: '/properties', query: { city: c.q } }" class="chip">{{ c.label }}</RouterLink>
          </div>
          <p class="browse-label">{{ $t('Rent') }}</p>
          <div class="chip-row">
            <RouterLink to="/rentals" class="chip chip--soft">{{ $t('Rentals: long and short term') }}</RouterLink>
          </div>
        </div>
      </div>
    </section>

    <!-- AI MARKETPLACE MAP -->
    <section v-if="activeCount" class="px-4 pb-20 max-w-7xl mx-auto">
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:rgba(var(--accent-cyan-rgb),0.1);border:1px solid rgba(var(--accent-cyan-rgb),0.2)">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-tone="cyan" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
          </div>
          <div>
            <h2 class="text-xl font-black" style="color:var(--text-primary)">{{ $t('AI Marketplace') }} <span style="color:var(--accent-cyan)">{{ $t('Map') }}</span></h2>
            <p class="text-xs" style="color:var(--text-muted)">{{ $t('Every listing, live on the map of North Cyprus') }}</p>
          </div>
        </div>
        <RouterLink to="/search?view=map" class="text-xs font-semibold" style="color:var(--accent-teal)">{{ $t('Open full map →') }}</RouterLink>
      </div>
      <ListingsMap :height="420" compact />
    </section>

    <!-- TOP 10 SPONSORED LISTINGS (Advertisements) -->
    <section v-if="ads.length > 0" class="px-4 pb-16 max-w-7xl mx-auto">
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:rgba(var(--warning-rgb),0.1);border:1px solid rgba(var(--warning-rgb),0.2)">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-tone="warning" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
          </div>
          <div>
            <h2 class="text-xl font-black" style="color:var(--text-primary)">{{ $t('Top 10') }} <span style="color:var(--warning)">{{ $t('Sponsored') }}</span></h2>
            <p class="text-xs" style="color:var(--text-muted)">{{ $t('Premium promoted listings') }}</p>
          </div>
        </div>
        <RouterLink to="/search?sponsored=1" class="text-xs font-semibold" style="color:var(--accent-teal)">{{ $t('View all →') }}</RouterLink>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
        <div v-for="(ad, i) in ads.slice(0,10)" :key="ad.id"
          class="rounded-2xl overflow-hidden cursor-pointer transition-all hover:scale-[1.02]"
          style="background:var(--bg-surface);border:1px solid rgba(var(--warning-rgb),0.2)"
          @click="trackAdClick(ad)">
          <div class="relative aspect-[4/3] overflow-hidden" style="background:var(--surface-overlay-soft)">
            <img v-if="ad.listing?.thumbnail" :src="ad.listing.thumbnail" class="w-full h-full object-cover" />
            <div v-else class="w-full h-full flex items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-tone="muted" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
            </div>
            <div class="absolute top-2 left-2 px-2 py-0.5 rounded-full text-xs font-bold" style="background:var(--warning);color:var(--on-accent)">#{{ i+1 }}</div>
          </div>
          <div class="p-3">
            <p class="text-xs font-semibold truncate" style="color:var(--text-primary)">{{ ad.listing?.title || ad.name }}</p>
            <p class="text-sm font-black mt-1" style="color:var(--warning)">{{ ad.listing?.price ? fmt(ad.listing.price, ad.listing.currency) : 'Sponsored' }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- AI BEST PRICES — VEHICLES -->
    <section class="px-4 pb-20 max-w-7xl mx-auto">
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:rgba(var(--accent-teal-rgb),0.1);border:1px solid rgba(var(--accent-teal-rgb),0.2)">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-tone="teal" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2"/></svg>
          </div>
          <div>
            <h2 class="text-xl font-black" style="color:var(--text-primary)">{{ $t('Best Price') }} <span class="gradient-text">{{ $t('Cars') }}</span></h2>
            <p class="text-xs" style="color:var(--text-muted)">{{ $t('Best value vehicles by AI estimate') }}</p>
          </div>
        </div>
        <RouterLink to="/cars" class="text-xs font-semibold" style="color:var(--accent-teal)">{{ $t('View all →') }}</RouterLink>
      </div>
      <div v-if="loadingVehicles" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <div v-for="i in 4" :key="i" class="h-64 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>
      </div>
      <div v-else-if="bestVehicles.length===0" class="text-center py-16 rounded-2xl" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <p class="font-semibold mb-1" style="color:var(--text-primary)">{{ $t('No cars listed yet') }}</p>
        <p class="text-sm mb-5" style="color:var(--text-muted)">{{ $t('Be the first — your listing gets an instant AI price check.') }}</p>
        <RouterLink :to="sellLink" class="inline-block px-5 py-2.5 rounded-xl text-sm font-bold" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">{{ $t('List your car free') }}</RouterLink>
      </div>
      <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <ListingCard v-for="l in bestVehicles" :key="l.id" :listing="l" />
      </div>
    </section>

    <!-- NEWEST VEHICLES -->
    <section v-if="loadingVehicles || newestVehicles.length" class="px-4 pb-20 max-w-7xl mx-auto">
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:rgba(var(--accent-cyan-rgb),0.1);border:1px solid rgba(var(--accent-cyan-rgb),0.2)">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-tone="cyan" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          </div>
          <div>
            <h2 class="text-xl font-black" style="color:var(--text-primary)">{{ $t('Newest') }} <span style="color:var(--accent-cyan)">{{ $t('Vehicles') }}</span></h2>
            <p class="text-xs" style="color:var(--text-muted)">{{ $t('Latest car listings') }}</p>
          </div>
        </div>
        <RouterLink to="/cars?sort=newest" class="text-xs font-semibold" style="color:var(--accent-teal)">{{ $t('View all →') }}</RouterLink>
      </div>
      <div v-if="loadingVehicles" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <div v-for="i in 4" :key="i" class="h-64 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>
      </div>
      <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <ListingCard v-for="l in newestVehicles" :key="l.id" :listing="l" />
      </div>
    </section>

    <!-- AI BEST PRICES — PROPERTIES -->
    <section class="px-4 pb-20 max-w-7xl mx-auto">
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:rgba(var(--accent-violet-rgb),0.1);border:1px solid rgba(var(--accent-violet-rgb),0.2)">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-tone="violet" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
          </div>
          <div>
            <h2 class="text-xl font-black" style="color:var(--text-primary)">{{ $t('Best Price') }} <span style="color:var(--accent-violet)">{{ $t('Properties') }}</span></h2>
            <p class="text-xs" style="color:var(--text-muted)">{{ $t('Best value real estate by AI estimate') }}</p>
          </div>
        </div>
        <RouterLink to="/properties" class="text-xs font-semibold" style="color:var(--accent-teal)">{{ $t('View all →') }}</RouterLink>
      </div>
      <div v-if="loadingProperties" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <div v-for="i in 4" :key="i" class="h-64 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>
      </div>
      <div v-else-if="bestProperties.length===0" class="text-center py-16 rounded-2xl" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <p class="font-semibold mb-1" style="color:var(--text-primary)">{{ $t('No properties listed yet') }}</p>
        <p class="text-sm mb-5" style="color:var(--text-muted)">{{ $t('Be the first — your listing gets an instant AI price check.') }}</p>
        <RouterLink :to="sellLink" class="inline-block px-5 py-2.5 rounded-xl text-sm font-bold" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">{{ $t('List your property free') }}</RouterLink>
      </div>
      <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <ListingCard v-for="l in bestProperties" :key="l.id" :listing="l" />
      </div>
    </section>

    <!-- NEWEST PROPERTIES -->
    <section v-if="loadingProperties || newestProperties.length" class="px-4 pb-20 max-w-7xl mx-auto">
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:rgba(var(--accent-cyan-rgb),0.1);border:1px solid rgba(var(--accent-cyan-rgb),0.2)">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-tone="cyan" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          </div>
          <div>
            <h2 class="text-xl font-black" style="color:var(--text-primary)">{{ $t('Newest') }} <span style="color:var(--accent-cyan)">{{ $t('Properties') }}</span></h2>
            <p class="text-xs" style="color:var(--text-muted)">{{ $t('Latest real estate listings') }}</p>
          </div>
        </div>
        <RouterLink to="/properties?sort=newest" class="text-xs font-semibold" style="color:var(--accent-teal)">{{ $t('View all →') }}</RouterLink>
      </div>
      <div v-if="loadingProperties" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <div v-for="i in 4" :key="i" class="h-64 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>
      </div>
      <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <ListingCard v-for="l in newestProperties" :key="l.id" :listing="l" />
      </div>
    </section>

    <!-- SUBSCRIPTION PLANS -->
    <section class="px-4 pb-24 max-w-7xl mx-auto">
      <div class="text-center mb-12">
        <h2 class="text-3xl font-black mb-3" style="color:var(--text-primary)">{{ $t('Start Selling') }} <span class="gradient-text">{{ $t('Today') }}</span></h2>
        <p class="text-base max-w-lg mx-auto" style="color:var(--text-muted)">{{ $t('Choose a plan to list your car or property. AI price analysis included on all paid plans.') }}</p>
      </div>
      <div v-if="loadingPlans" class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div v-for="i in 4" :key="i" class="h-64 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>
      </div>
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div v-for="(plan, i) in plans" :key="plan.id" class="rounded-2xl p-6 flex flex-col" :style="i===1?'background:var(--bg-surface);border:2px solid rgba(var(--accent-teal-rgb),0.4)':'background:var(--bg-surface);border:1px solid var(--border-subtle)'">
          <div v-if="i===1" class="text-center mb-3">
            <span class="text-xs font-bold px-3 py-1 rounded-full" style="background:rgba(var(--accent-teal-rgb),0.1);color:var(--accent-teal);border:1px solid rgba(var(--accent-teal-rgb),0.3)">{{ $t('Most Popular') }}</span>
          </div>
          <h3 class="font-black text-lg mb-1" style="color:var(--text-primary)">{{ plan.name }}</h3>
          <p class="text-xs mb-4" style="color:var(--text-muted)">{{ plan.description || planTagline(plan) }}</p>
          <div class="mb-4">
            <span class="text-3xl font-black" :style="{color: i===1?'var(--accent-teal)':'var(--text-primary)'}">£{{ plan.price_monthly }}</span>
            <span class="text-sm" style="color:var(--text-muted)">{{ $t('/month') }}</span>
          </div>
          <ul class="space-y-2 text-xs mb-6 flex-1" style="color:var(--text-secondary)">
            <li class="flex items-center gap-2">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-tone="teal" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
              {{ plan.max_listings }} listings
            </li>
            <li v-if="plan.ai_price_analysis" class="flex items-center gap-2">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-tone="teal" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
              {{ $t('AI price analysis') }}
            </li>
            <li v-if="plan.featured_listing" class="flex items-center gap-2">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-tone="teal" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
              {{ $t('Featured placement') }}
            </li>
            <li v-if="plan.priority_support" class="flex items-center gap-2">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-tone="teal" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
              {{ $t('Priority support') }}
            </li>
          </ul>
          <RouterLink :to="auth.isAuthenticated ? '/dashboard' : '/register'" class="block text-center py-3 rounded-xl font-bold text-sm" :style="i===1?'background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)':'background:var(--border-subtle);color:var(--text-secondary);border:1px solid var(--border-strong)'">
            {{ plan.price_monthly === 0 ? 'Get Started Free' : 'Choose ' + plan.name }}
          </RouterLink>
        </div>
      </div>
    </section>
  </MainLayout>
</template>
<script setup lang="ts">
import { usePageMeta } from '@/composables/usePageMeta'
usePageMeta(() => ({ title: undefined, description: 'Every car and property listing in North Cyprus checked against the market by AI. See the fair price before you buy or sell.' }))
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import MainLayout from '@/layouts/MainLayout.vue'
import ListingCard from '@/components/listings/ListingCard.vue'
import ListingsMap from '@/components/listings/ListingsMap.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import AssetIcon from '@/components/ui/AssetIcon.vue'
import { useAuthStore } from '@/stores/auth'
import client from '@/api/client'

const router = useRouter()
const auth = useAuthStore()

const lastPreference = ref<{ asset_type: string; budget_max: number | null; city: string | null } | null>(null)
const newMatchesCount = ref(0)

const cities = ['Girne', 'Lefkosa', 'Gazimağusa', 'Iskele', 'Guzelyurt', 'Lefke']

const assetTypes = [
  { value: 'vehicle', label: 'Car' },
  { value: 'property', label: 'Property' },
  { value: 'rental', label: 'Rental' },
]

// Each priority chip nudges the search toward one of the three ranking
// profiles the backend actually supports (RankingEngineService: find/deal/invest).
// This is a coarse mapping today — a per-chip weighted profile is a future
// enhancement once the ranking engine supports custom weight vectors.
const priorityOptions = [
  { value: 'best_price', label: 'Best Price', mode: 'deal' },
  { value: 'best_deal', label: 'Best Deal', mode: 'deal' },
  { value: 'low_cost', label: 'Low Monthly Cost', mode: 'deal' },
  { value: 'family', label: 'Family', mode: 'find' },
  { value: 'luxury', label: 'Luxury', mode: 'find' },
  { value: 'low_risk', label: 'Low Risk', mode: 'find' },
  { value: 'near_me', label: 'Near Me', mode: 'find' },
  { value: 'investment', label: 'Investment', mode: 'invest' },
  { value: 'high_resale', label: 'High Resale', mode: 'invest' },
]

const budgetOptions = [
  { label: 'Under £10K', value: '10000' },
  { label: '£10K–20K', value: '20000' },
  { label: '£20K–30K', value: '30000' },
  { label: 'Custom', value: 'custom' },
]

const intent = ref({
  type: 'vehicle',
  priorities: [] as string[],
  budgetMax: '',
  budgetCustom: '',
  city: '',
})

function togglePriority(value: string) {
  const i = intent.value.priorities.indexOf(value)
  if (i === -1) intent.value.priorities.push(value)
  else intent.value.priorities.splice(i, 1)
}

function resolveMode(): string {
  if (!intent.value.priorities.length) return ''
  const counts: Record<'find' | 'deal' | 'invest', number> = { find: 0, deal: 0, invest: 0 }
  for (const p of intent.value.priorities) {
    const opt = priorityOptions.find(o => o.value === p)
    if (opt) counts[opt.mode as 'find' | 'deal' | 'invest']++
  }
  const sorted = Object.entries(counts).sort((a, b) => b[1] - a[1])
  return sorted[0]?.[0] ?? 'find'
}

async function findMyMatch() {
  const params = new URLSearchParams()
  params.set('type', intent.value.type)
  const mode = resolveMode()
  if (mode) params.set('mode', mode)
  const budget = intent.value.budgetMax === 'custom' ? intent.value.budgetCustom : intent.value.budgetMax
  if (budget) params.set('budget_max', String(budget))
  if (intent.value.city) params.set('city', intent.value.city)

  // AI Preference Graph — remember this search for signed-in users so the
  // homepage can say "welcome back" next visit. Best-effort: never blocks navigation.
  if (auth.isAuthenticated) {
    client.post('/me/search-preferences', {
      asset_type: intent.value.type,
      budget_max: budget || null,
      city: intent.value.city || null,
      priorities: intent.value.priorities,
    }).catch(() => {})
  }

  router.push('/search?' + params.toString())
}

function rerunLastSearch() {
  if (!lastPreference.value) return
  const params = new URLSearchParams()
  params.set('type', lastPreference.value.asset_type)
  if (lastPreference.value.budget_max) params.set('budget_max', String(lastPreference.value.budget_max))
  if (lastPreference.value.city) params.set('city', lastPreference.value.city)
  router.push('/search?' + params.toString())
}

const bestVehicles = ref<any[]>([])
const newestVehicles = ref<any[]>([])
const bestProperties = ref<any[]>([])
const newestProperties = ref<any[]>([])
const plans = ref<any[]>([])
const ads = ref<any[]>([])
const loadingVehicles = ref(true)
const loadingProperties = ref(true)
const loadingPlans = ref(true)

const activeCount = ref<number | null>(null)
const sellLink = computed(() => (auth.isAuthenticated ? '/dashboard/listings/new' : { name: 'register', query: { redirect: '/dashboard/listings/new' } }))
function planTagline(plan: any) {
  const limit = Number(plan.max_listings)
  if (Number(plan.price_monthly) <= 0) return 'Try it free — no card needed'
  if (limit >= 999) return 'For dealers and agencies at scale'
  if (limit >= 50) return 'For active sellers and small dealers'
  return 'For private sellers'
}
const stats = ref([
  { label: 'Active Listings', value: '...', color: 'var(--accent-teal)' },
  { label: 'Cars Listed', value: '...', color: 'var(--accent-cyan)' },
  { label: 'Properties', value: '...', color: 'var(--accent-violet)' },
  { label: 'AI Analyses', value: '...', color: 'var(--warning)' },
])

onMounted(async () => {
  if (auth.isAuthenticated) {
    client.get('/me/search-preferences/last').then(({ data }) => {
      if (data.preference) {
        lastPreference.value = data.preference
        newMatchesCount.value = data.new_matches ?? 0
      }
    }).catch(() => {})
  }

  const [statsRes, vehiclesRes, propsRes, plansRes, adsRes] = await Promise.allSettled([
    client.get('/market/stats').catch(() => null),
    client.get('/listings?type=vehicle&per_page=8&sort=price_asc'),
    client.get('/listings?type=property&per_page=8&sort=price_asc'),
    client.get('/subscription-plans'),
    client.get('/advertisements?slot=top10'),
  ])

  if (statsRes.status === 'fulfilled' && statsRes.value) {
    const s = statsRes.value.data
    activeCount.value = s.active_listings ?? 0
    stats.value = [
      { label: 'Active Listings', value: (s.active_listings || 0).toLocaleString(), color: 'var(--accent-teal)' },
      { label: 'Cars Listed', value: (s.vehicle_listings || 0).toLocaleString(), color: 'var(--accent-cyan)' },
      { label: 'Properties', value: (s.property_listings || 0).toLocaleString(), color: 'var(--accent-violet)' },
      { label: 'AI-scored', value: (s.ai_scored_listings || 0).toLocaleString(), color: 'var(--warning)' },
    ]
  }

  if (vehiclesRes.status === 'fulfilled') {
    const data = vehiclesRes.value.data?.data || vehiclesRes.value.data || []
    bestVehicles.value = data.slice(0, 4)
    newestVehicles.value = data.slice(4, 8)
  }
  loadingVehicles.value = false

  if (propsRes.status === 'fulfilled') {
    const data = propsRes.value.data?.data || propsRes.value.data || []
    bestProperties.value = data.slice(0, 4)
    newestProperties.value = data.slice(4, 8)
  }
  loadingProperties.value = false

  if (plansRes.status === 'fulfilled') {
    plans.value = plansRes.value.data || []
  }
  loadingPlans.value = false

  if (adsRes.status === 'fulfilled') {
    ads.value = adsRes.value.data || []
  }
})

function fmt(v: number, cur = 'GBP') {
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency: cur || 'GBP', maximumFractionDigits: 0 }).format(v)
}

async function trackAdClick(ad: any) {
  await client.post('/advertisements/' + ad.id + '/click').catch(() => {})
  if (ad.listing?.slug) router.push('/listings/' + ad.listing.slug)
  else if (ad.link) window.open(ad.link, '_blank')
}

// Category taxonomy for the "Browse by category" section (mirrors the /cars and /properties filters).
const carBodies = ['Sedan', 'SUV', 'Hatchback', 'Crossover', 'Pickup', 'Coupe', 'Convertible', 'Van']
const carBrands = ['Toyota', 'Honda', 'Nissan', 'Mercedes', 'BMW', 'Audi', 'Volkswagen', 'Hyundai']
const carPriceClasses = [
  { label: 'Under £7,500', query: { max_price: '7500' } },
  { label: 'Under £15,000', query: { max_price: '15000' } },
  { label: 'Under £25,000', query: { max_price: '25000' } },
  { label: 'Under £40,000', query: { max_price: '40000' } },
]
const carQuick = [
  { label: 'Automatic', query: { transmission: 'automatic' } },
  { label: 'Electric', query: { fuel: 'electric' } },
  { label: 'Hybrid', query: { fuel: 'hybrid' } },
  { label: '2020 or newer', query: { min_year: '2020' } },
  { label: 'Under 50,000 km', query: { max_mileage: '50000' } },
]
const propertyKinds = ['Apartment', 'Villa', 'Townhouse', 'Penthouse', 'Studio', 'Duplex', 'Land', 'Commercial']
const propertyPriceClasses = [
  { label: 'Under £75,000', query: { max_price: '75000' } },
  { label: 'Under £150,000', query: { max_price: '150000' } },
  { label: 'Under £300,000', query: { max_price: '300000' } },
]
const propertyCities = [
  { label: 'Girne (Kyrenia)', q: 'Girne' }, { label: 'Lefkoşa (Nicosia)', q: 'Lefkosa' },
  { label: 'Gazimağusa (Famagusta)', q: 'Gazimağusa' }, { label: 'İskele', q: 'Iskele' }, { label: 'Güzelyurt', q: 'Guzelyurt' },
]
</script>
<style scoped>
.hero { background:linear-gradient(160deg,#081827 0%,#0a2540 55%,#0c3354 100%);color:#fff }
.hero-glow { position:absolute;inset:-20% -10% auto -10%;height:120%;pointer-events:none;
  background:radial-gradient(ellipse 50% 40% at 20% 10%,rgba(21,155,242,.35),transparent 70%),radial-gradient(ellipse 40% 35% at 85% 20%,rgba(22,199,132,.22),transparent 70%) }
.hero-badge { background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.18);color:#d7e6f5 }
.hero-title { color:#fff;letter-spacing:-.02em }
.hero-tagline { background:linear-gradient(90deg,#4fb8ff,#12C5E8 50%,#16C784);-webkit-background-clip:text;background-clip:text;color:transparent }
.hero-sub { color:#c5d3e4 }
.composer { background:var(--bg-surface);border:1px solid var(--border-subtle);box-shadow:0 24px 60px rgba(4,12,24,.35);color:var(--text-primary) }
.hero-stat { background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.14) }
.hero-stat p:first-child { color:#fff }
.hero-stat p:last-child { color:#b9c8d9 }
.browse-card { background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:20px;padding:20px;box-shadow:0 1px 2px rgba(8,24,39,.04),0 8px 24px rgba(8,24,39,.06) }
.browse-head { display:flex;align-items:center;gap:8px;font-weight:800;font-size:16px;color:var(--text-primary);margin-bottom:8px }
.browse-label { font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--text-muted);margin:14px 0 8px }
.tile-grid { display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px }
@media (max-width: 480px) { .tile-grid { grid-template-columns:repeat(2,minmax(0,1fr)) } }
.tile { text-align:center;padding:12px 6px;border-radius:12px;font-size:13px;font-weight:700;text-decoration:none;color:var(--text-primary);background:var(--surface-overlay);border:1px solid var(--border-subtle) }
.tile:hover, .chip:hover { border-color:var(--accent-cyan);color:var(--accent-cyan) }
.chip-row { display:flex;flex-wrap:wrap;gap:8px }
.chip { padding:6px 12px;border-radius:999px;font-size:12.5px;font-weight:600;text-decoration:none;color:var(--text-secondary);border:1px solid var(--border-strong) }
.chip--soft { background:var(--surface-overlay);border-color:var(--border-subtle) }
</style>
