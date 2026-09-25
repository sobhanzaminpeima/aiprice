<template>
  <div class="p-8">
    <div class="mb-8">
      <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">Market <span class="gradient-text">Analytics</span></h1>
      <p class="text-sm" style="color:var(--text-muted)">Price heatmaps, trends, and investment analysis</p>
    </div>

    <!-- Market Trust Index -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
      <div v-for="idx in trustIndexes" :key="idx.market" class="rounded-2xl p-5" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-sm font-bold" style="color:var(--text-primary)">{{ idx.market }}</h3>
          <span class="text-2xl font-black" :style="{color: idx.color}">{{ idx.score }}</span>
        </div>
        <!-- Circular gauge representation -->
        <div class="flex items-center gap-3 mb-3">
          <div class="relative w-16 h-16">
            <svg viewBox="0 0 36 36" class="w-16 h-16 -rotate-90">
              <circle cx="18" cy="18" r="15.9" fill="none" stroke="var(--border-subtle)" stroke-width="3"/>
              <circle cx="18" cy="18" r="15.9" fill="none" :stroke="idx.color" stroke-width="3" stroke-linecap="round"
                :stroke-dasharray="`${idx.score} ${100-idx.score}`" stroke-dashoffset="0"/>
            </svg>
            <span class="absolute inset-0 flex items-center justify-center text-xs font-black" :style="{color: idx.color}">{{ idx.score }}</span>
          </div>
          <div class="space-y-1.5 flex-1">
            <div v-for="sub in idx.subs" :key="sub.name" class="flex items-center gap-2">
              <span class="text-xs w-16 truncate" style="color:var(--text-muted)">{{ sub.name }}</span>
              <div class="flex-1 h-1 rounded-full" style="background:var(--border-subtle)">
                <div class="h-1 rounded-full" :style="`width:${sub.val}%;background:${idx.color}`"></div>
              </div>
            </div>
          </div>
        </div>
        <div class="text-xs px-3 py-2 rounded-lg" :style="`background:rgba(${idx.rgbStr},0.06);color:rgb(${idx.rgbStr})`">
          {{ idx.verdict }}
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
      <!-- Price by City Heatmap -->
      <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <h2 class="font-bold mb-5" style="color:var(--text-primary)">🗺 Price by City (Vehicles)</h2>
        <div class="space-y-3">
          <div v-for="city in cityVehiclePrices" :key="city.name" class="rounded-xl p-3" :style="`background:rgba(${city.rgb},0.06);border:1px solid rgba(${city.rgb},0.15)`">
            <div class="flex items-center justify-between mb-2">
              <span class="text-sm font-semibold" :style="`color:rgb(${city.rgb})`">{{ city.name }}</span>
              <span class="text-sm font-black" style="color:var(--text-primary)">{{ city.avg }}</span>
            </div>
            <div class="flex gap-2 text-xs" style="color:var(--text-muted)">
              <span>Min: {{ city.min }}</span>
              <span>·</span>
              <span>Max: {{ city.max }}</span>
              <span>·</span>
              <span class="font-medium" :style="`color:rgb(${city.rgb})`">{{ city.listings }} listings</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Property Price Trends -->
      <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <h2 class="font-bold mb-5 flex items-center gap-2" style="color:var(--text-primary)">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#56d8ff" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
          Property Price Trends (£/m²)
        </h2>
        <div class="space-y-4">
          <div v-for="area in propertyTrends" :key="area.name">
            <div class="flex justify-between text-xs mb-1.5">
              <span style="color:var(--text-secondary)">{{ area.name }}</span>
              <div class="flex items-center gap-2">
                <span style="color:var(--text-primary)" class="font-bold">£{{ area.price }}/m²</span>
                <span class="font-bold" :style="area.trend > 0 ? 'color:var(--accent-teal)' : 'color:#ef4444'">
                  {{ area.trend > 0 ? '↑' : '↓' }} {{ Math.abs(area.trend) }}% YoY
                </span>
              </div>
            </div>
            <div class="h-2 rounded-full" style="background:var(--border-subtle)">
              <div class="h-2 rounded-full" :style="`width:${area.pct}%;background:linear-gradient(90deg,#56d8ff,#a78bfa)`"></div>
            </div>
          </div>
        </div>
        <div class="mt-5 pt-4 grid grid-cols-2 gap-3" style="border-top:1px solid var(--border-subtle)">
          <div class="text-center p-3 rounded-xl" style="background:rgba(86,216,255,0.06);border:1px solid rgba(86,216,255,0.15)">
            <p class="text-xl font-black" style="color:var(--accent-cyan)">+18%</p>
            <p class="text-xs" style="color:var(--text-muted)">Avg YoY Growth</p>
          </div>
          <div class="text-center p-3 rounded-xl" style="background:rgba(167,139,250,0.06);border:1px solid rgba(167,139,250,0.15)">
            <p class="text-xl font-black" style="color:var(--accent-violet)">Girne</p>
            <p class="text-xs" style="color:var(--text-muted)">Highest Growth Area</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Investment Opportunities -->
    <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <h2 class="font-bold mb-5 flex items-center gap-2" style="color:var(--text-primary)">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#fbbf24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
        Top Investment Opportunities
      </h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div v-for="opp in opportunities" :key="opp.title" class="rounded-xl p-4" :style="`background:rgba(${opp.rgb},0.04);border:1px solid rgba(${opp.rgb},0.15)`">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-3" :style="'background:rgba('+opp.rgb+',0.12)'">
            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" :stroke="'rgb('+opp.rgb+')'" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" :d="opp.iconPath"/></svg>
          </div>
          <h3 class="text-sm font-bold mb-1" style="color:var(--text-primary)">{{ opp.title }}</h3>
          <p class="text-xs mb-3" style="color:var(--text-muted)">{{ opp.desc }}</p>
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold" :style="`color:rgb(${opp.rgb})`">ROI: {{ opp.roi }}</span>
            <span class="text-xs px-2 py-0.5 rounded-full font-bold" :style="`background:rgba(${opp.rgb},0.1);color:rgb(${opp.rgb})`">{{ opp.tag }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const trustIndexes = [
  { market: 'Cars Market', score: 84, color: '#6fffd2', rgbStr: '111,255,210', verdict: 'Strong buyer confidence. High liquidity. Good time to sell.', subs: [{ name: 'Confidence', val: 88 }, { name: 'Demand', val: 82 }, { name: 'Liquidity', val: 87 }] },
  { market: 'Properties Market', score: 76, color: '#56d8ff', rgbStr: '86,216,255', verdict: 'Growing market. Long Beach & Kyrenia outperforming.', subs: [{ name: 'Confidence', val: 78 }, { name: 'Demand', val: 81 }, { name: 'Growth', val: 84 }] },
  { market: 'Rental Market', score: 91, color: '#a78bfa', rgbStr: '167,139,250', verdict: 'Exceptional demand. 8-12% yields available. Very hot.', subs: [{ name: 'Confidence', val: 94 }, { name: 'Demand', val: 96 }, { name: 'Yields', val: 86 }] },
]
const cityVehiclePrices = [
  { name: 'Girne (Kyrenia)', avg: '420,000 TRY', min: '230K', max: '650K', listings: 8, rgb: '111,255,210' },
  { name: 'Lefkosa (Nicosia)', avg: '380,000 TRY', min: '285K', max: '590K', listings: 7, rgb: '86,216,255' },
  { name: 'Gazimağusa', avg: '360,000 TRY', min: '245K', max: '520K', listings: 3, rgb: '167,139,250' },
  { name: 'Iskele', avg: '395,000 TRY', min: '295K', max: '530K', listings: 2, rgb: '251,191,36' },
]
const propertyTrends = [
  { name: 'Girne — Seafront', price: '1,850', trend: 24, pct: 100 },
  { name: 'Iskele — Long Beach', price: '1,620', trend: 31, pct: 88 },
  { name: 'Lefkosa — City Centre', price: '980', trend: 12, pct: 53 },
  { name: 'Gazimağusa — Old City', price: '720', trend: 8, pct: 39 },
  { name: 'Guzelyurt — Rural', price: '480', trend: 5, pct: 26 },
]
const opportunities = [
  { iconPath: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0h6', title: 'Iskele Long Beach Apts', desc: 'Off-plan prices still available. 31% YoY growth. High tourist demand.', roi: '10-13%', tag: 'HOT', rgb: '111,255,210' },
  { iconPath: 'M8 6H5.5A2.5 2.5 0 003 8.5v2A1.5 1.5 0 004.5 12h15a1.5 1.5 0 001.5-1.5v-2A2.5 2.5 0 0018.5 6H16m-8 0l2-3h4l2 3M8 6h8M6 12v5a1 1 0 001 1h1m8-6v5a1 1 0 01-1 1h-1m-6 0h6', title: 'Japanese Used Cars', desc: 'Toyota/Honda under 350K TRY selling in <7 days. Strong resale value.', roi: '15-20%', tag: 'FAST', rgb: '86,216,255' },
  { iconPath: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', title: 'Kyrenia Studio Rentals', desc: 'University + tourism = 96% occupancy. Stable rental income.', roi: '8-11%', tag: 'STABLE', rgb: '167,139,250' },
]
</script>
