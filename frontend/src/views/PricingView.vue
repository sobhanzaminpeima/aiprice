<template>
  <div style="background:var(--bg-base);min-height:100vh">
    <div class="text-center py-20 px-4" style="background:radial-gradient(ellipse 60% 40% at 50% -10%,rgba(111,255,210,0.08),transparent)">
      <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-xs font-semibold mb-6" style="background:rgba(111,255,210,0.08);border:1px solid rgba(111,255,210,0.2);color:var(--accent-teal)">
        AI-Powered Pricing
      </div>
      <h1 class="text-5xl font-black mb-4" style="color:var(--text-primary)">Simple, <span class="gradient-text">Transparent</span> Pricing</h1>
      <p class="text-lg mb-8 max-w-xl mx-auto" style="color:var(--text-muted)">Start free forever. Upgrade when you need AI pricing intelligence, featured listings, or dealer tools.</p>
      <div class="inline-flex items-center gap-1 p-1 rounded-2xl mb-12" style="background:var(--surface-overlay);border:1px solid var(--border-subtle)">
        <button @click="yearly = false" class="px-5 py-2 rounded-xl text-sm font-semibold transition-all" :style="!yearly ? 'background:rgba(111,255,210,0.12);color:var(--accent-teal)' : 'color:var(--text-muted)'">Monthly</button>
        <button @click="yearly = true" class="px-5 py-2 rounded-xl text-sm font-semibold transition-all" :style="yearly ? 'background:rgba(111,255,210,0.12);color:var(--accent-teal)' : 'color:var(--text-muted)'">
          Yearly <span class="text-xs ml-1 px-1.5 py-0.5 rounded-full" style="background:rgba(111,255,210,0.15);color:var(--accent-teal)">-20%</span>
        </button>
      </div>
    </div>
    <div class="max-w-7xl mx-auto px-4 pb-20">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-16">
        <div v-for="plan in plans" :key="plan.name" class="rounded-2xl p-6 relative flex flex-col"
          :style="plan.featured ? 'background:linear-gradient(135deg,rgba(111,255,210,0.07),rgba(86,216,255,0.04));border:2px solid rgba(111,255,210,0.3)' : 'background:var(--bg-surface);border:1px solid var(--border-subtle)'">
          <div v-if="plan.featured" class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 rounded-full text-xs font-black" style="background:linear-gradient(90deg,#6fffd2,#56d8ff);color:var(--on-accent)">MOST POPULAR</div>
          <div class="mb-5">
            <div class="text-2xl mb-2">{{ plan.icon }}</div>
            <h3 class="text-lg font-black mb-1" style="color:var(--text-primary)">{{ plan.name }}</h3>
            <p class="text-xs" style="color:var(--text-muted)">{{ plan.tagline }}</p>
          </div>
          <div class="mb-5">
            <div class="flex items-end gap-1">
              <span class="text-3xl font-black" :style="{color: plan.color}">{{ plan.price === 0 ? 'Free' : `${yearly ? Math.round(plan.price * 0.8) : plan.price}` }}</span>
              <span v-if="plan.price > 0" class="text-xs mb-1" style="color:var(--text-muted)">/mo</span>
            </div>
            <p v-if="plan.price > 0 && yearly" class="text-xs mt-1" style="color:var(--accent-teal)">Billed {{ Math.round(plan.price * 0.8 * 12) }}/year</p>
          </div>
          <ul class="space-y-2.5 flex-1 mb-6">
            <li v-for="f in plan.features" :key="f.text" class="flex items-start gap-2">
              <span class="mt-0.5 shrink-0" :style="{color: f.included ? plan.color : '#3a4a5a'}">{{ f.included ? 'v' : 'o' }}</span>
              <span class="text-xs leading-relaxed" :style="{color: f.included ? 'var(--text-secondary)' : '#3a4a5a'}">{{ f.text }}</span>
            </li>
          </ul>
          <button class="w-full py-3 rounded-xl font-bold text-sm transition-all"
            :style="plan.featured ? 'background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)' : `background:rgba(${plan.rgb},0.1);color:rgb(${plan.rgb});border:1px solid rgba(${plan.rgb},0.25)`">
            {{ plan.cta }}
          </button>
        </div>
      </div>
      <div class="rounded-2xl p-8" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <h2 class="text-2xl font-black mb-8 text-center" style="color:var(--text-primary)">Full Feature Comparison</h2>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr>
                <th class="text-left pb-5 text-sm font-medium" style="color:var(--text-muted);width:30%">Feature</th>
                <th v-for="p in plans" :key="p.name" class="pb-5 text-center text-xs font-bold" :style="{color: p.color}">{{ p.name }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in comparison" :key="row.feature" class="border-t" style="border-color:var(--surface-overlay)">
                <td class="py-3 text-xs" style="color:var(--text-secondary)">{{ row.feature }}</td>
                <td v-for="(val, i) in row.values" :key="i" class="py-3 text-center text-xs font-medium">
                  <span v-if="val === true" style="color:var(--accent-teal)">v</span>
                  <span v-else-if="val === false" style="color:#3a4a5a">-</span>
                  <span v-else style="color:var(--text-secondary)">{{ val }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup lang="ts">
import { ref } from "vue"
const yearly = ref(false)
const plans = [
  { name: "Free", icon: "S", tagline: "Get started, no card needed", price: 0, color: "var(--text-muted)", rgb: "110,127,150", featured: false, cta: "Get Started Free",
    features: [{ text: "3 active listings", included: true },{ text: "Basic AI price check", included: true },{ text: "Standard listing visibility", included: true },{ text: "Email inquiries", included: true },{ text: "Featured placement", included: false },{ text: "Full AI analysis", included: false },{ text: "Analytics dashboard", included: false }] },
  { name: "Premium", icon: "P", tagline: "For serious private sellers", price: 29, color: "#6fffd2", rgb: "111,255,210", featured: true, cta: "Start Premium",
    features: [{ text: "20 active listings", included: true },{ text: "Full AI Price Battery", included: true },{ text: "Featured in search results", included: true },{ text: "2 featured listings/mo", included: true },{ text: "Seller analytics", included: true },{ text: "AI recommendations", included: true },{ text: "Priority support", included: false }] },
  { name: "Dealer", icon: "D", tagline: "For car dealerships", price: 99, color: "#56d8ff", rgb: "86,216,255", featured: false, cta: "Start Dealer Plan",
    features: [{ text: "Unlimited listings", included: true },{ text: "Full AI Price Battery", included: true },{ text: "10 featured listings/mo", included: true },{ text: "Dealer badge + profile", included: true },{ text: "Advanced analytics", included: true },{ text: "Bulk CSV import", included: true },{ text: "Priority support", included: true }] },
  { name: "Agency", icon: "A", tagline: "For real estate agencies", price: 149, color: "#a78bfa", rgb: "167,139,250", featured: false, cta: "Start Agency Plan",
    features: [{ text: "Unlimited property listings", included: true },{ text: "Full AI Price Battery", included: true },{ text: "15 featured listings/mo", included: true },{ text: "Agency branding + page", included: true },{ text: "Market trend reports", included: true },{ text: "Investment scoring", included: true },{ text: "Dedicated account manager", included: true }] },
  { name: "Enterprise", icon: "E", tagline: "White-label + API access", price: 499, color: "#fbbf24", rgb: "251,191,36", featured: false, cta: "Contact Sales",
    features: [{ text: "Everything in Agency", included: true },{ text: "API access", included: true },{ text: "White-label option", included: true },{ text: "Custom AI models", included: true },{ text: "SLA guarantee", included: true },{ text: "Dedicated infrastructure", included: true },{ text: "24/7 phone support", included: true }] },
]
const comparison = [
  { feature: "Active Listings", values: ["3","20","Unlimited","Unlimited","Unlimited"] },
  { feature: "AI Price Battery", values: ["Basic",true,true,true,true] },
  { feature: "Featured Listings / mo", values: [false,"2","10","15","Unlimited"] },
  { feature: "Analytics Dashboard", values: [false,true,true,true,true] },
  { feature: "AI Recommendations", values: [false,true,true,true,true] },
  { feature: "Dealer/Agency Badge", values: [false,false,true,true,true] },
  { feature: "Bulk CSV Import", values: [false,false,true,true,true] },
  { feature: "Market Trend Reports", values: [false,false,false,true,true] },
  { feature: "API Access", values: [false,false,false,false,true] },
  { feature: "White Label", values: [false,false,false,false,true] },
  { feature: "Support", values: ["Email","Email","Priority","Dedicated","24/7 Phone"] },
]
</script>