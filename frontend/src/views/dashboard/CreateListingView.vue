<template>
  <div class="p-6 max-w-3xl">
    <div class="mb-8">
      <h1 class="text-3xl font-black mb-2" style="color:var(--text-primary)">New <span class="gradient-text">Listing</span></h1>
      <p class="text-sm" style="color:var(--text-muted)">{{ step === 0 ? 'Optional — let AI draft the form for you' : `Step ${step} of 5 — ${stepLabels[step - 1]}` }}</p>
    </div>

    <div v-if="missingFields.length && step >= 1 && step <= 5" class="mb-6 p-3 rounded-xl text-xs flex items-start gap-2" style="background:rgba(251,191,36,0.08);border:1px solid rgba(251,191,36,0.25);color:#fbbf24">
      <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
      <span>AI filled this form from your description but couldn't determine: <strong>{{ missingFields.join(', ') }}</strong>. Please check and complete those fields.</span>
    </div>

    <!-- Progress -->
    <div v-if="step >= 1" class="flex items-center gap-2 mb-10">
      <div v-for="i in 5" :key="i" class="flex items-center gap-2 flex-1">
        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-black transition-all"
          :style="i < step ? 'background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)' : i === step ? 'background:rgba(111,255,210,0.15);color:var(--accent-teal);border:2px solid #6fffd2' : 'background:var(--surface-overlay);color:var(--text-muted);border:2px solid var(--border-strong)'">
          <svg v-if="i < step" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          <span v-else>{{ i }}</span>
        </div>
        <div v-if="i < 5" class="flex-1 h-0.5 rounded-full" :style="i < step ? 'background:#6fffd2' : 'background:var(--border-subtle)'"></div>
      </div>
    </div>

    <!-- Error banner -->
    <div v-if="error" class="mb-4 p-3 rounded-xl text-sm" style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);color:#ef4444">{{ error }}</div>

    <!-- STEP 0: AI free-text import -->
    <div v-if="step === 0" class="mb-10">
      <div class="rounded-2xl p-6 mb-4" style="background:linear-gradient(135deg,rgba(111,255,210,0.06),rgba(86,216,255,0.03));border:2px solid rgba(111,255,210,0.25)">
        <div class="flex items-center gap-3 mb-3">
          <div class="w-8 h-8 rounded-xl flex items-center justify-center text-xs font-black shrink-0" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">AI</div>
          <div>
            <p class="font-bold" style="color:var(--text-primary)">Describe it in your own words</p>
            <p class="text-xs" style="color:var(--text-muted)">Write it as one paragraph — brand, year, price, location, condition, anything you'd tell a buyer. AI fills the form for you; you review every field before it's submitted.</p>
          </div>
        </div>
        <textarea v-model="freeText" rows="6" placeholder="e.g. 2019 BMW 3 Series, 45000 km, automatic, diesel, silver, excellent condition, asking £18,500 negotiable, located in Girne..."
          class="field-input resize-none mb-3" style="min-height:140px"></textarea>
        <p v-if="extractError" class="text-xs mb-3" style="color:#f87171">{{ extractError }}</p>
        <div class="flex items-center gap-3">
          <button @click="runExtraction" :disabled="freeText.trim().length < 20 || extracting"
            class="px-6 py-3 rounded-xl font-bold text-sm disabled:opacity-40 flex items-center gap-2" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">
            <svg v-if="extracting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            {{ extracting ? 'Reading your description...' : 'Fill form with AI' }}
          </button>
          <span class="text-xs" style="color:var(--text-muted)">{{ freeText.trim().length }}/5000</span>
        </div>
      </div>
      <div class="text-center">
        <button @click="step = 1" class="text-sm font-semibold underline" style="color:var(--text-muted)">Skip — I'll fill the form manually</button>
      </div>
    </div>

    <!-- STEP 1: Type -->
    <div v-if="step === 1">
      <h2 class="text-lg font-bold mb-6" style="color:var(--text-primary)">What are you listing?</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <button v-for="t in types" :key="t.value" @click="form.type = t.value"
          class="rounded-2xl p-6 text-left transition-all"
          :style="form.type === t.value ? 'background:rgba(111,255,210,0.08);border:2px solid rgba(111,255,210,0.4)' : 'background:var(--bg-surface);border:2px solid var(--border-subtle)'">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-3" :style="'background:rgba('+t.rgb+',0.1)'">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" :stroke="`rgb(${t.rgb})`" stroke-width="1.5" v-html="t.iconPath"></svg>
          </div>
          <h3 class="font-bold mb-1" :style="{color: form.type === t.value ? 'rgb('+t.rgb+')' : 'var(--text-primary)'}">{{ t.label }}</h3>
          <p class="text-xs" style="color:var(--text-muted)">{{ t.desc }}</p>
        </button>
      </div>
      <div class="flex justify-end">
        <button @click="nextStep" :disabled="!form.type" class="px-8 py-3 rounded-xl font-bold text-sm disabled:opacity-40" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">Continue</button>
      </div>
    </div>

    <!-- STEP 2: Details -->
    <div v-if="step === 2">
      <h2 class="text-lg font-bold mb-6" style="color:var(--text-primary)">{{ form.type === 'vehicle' ? 'Vehicle' : 'Property' }} Details</h2>

      <div v-if="form.type === 'vehicle'" class="space-y-4 mb-8">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="field-label">Brand *</label>
            <select v-model="form.brand_name" class="field-input">
              <option value="">Select brand</option>
              <option v-for="b in brandsFromApi" :key="b.id" :value="b.name">{{ b.name }}</option>
            </select>
          </div>
          <div>
            <label class="field-label">Model *</label>
            <select v-model="form.model_name" class="field-input">
              <option value="">Select model</option>
              <option v-for="m in modelsForBrand" :key="m.id" :value="m.name">{{ m.name }}</option>
              <option value="_other">Other (type below)</option>
            </select>
            <input v-if="form.model_name === '_other'" v-model="form.model_name_custom" placeholder="Model name" class="field-input mt-2" />
          </div>
          <div>
            <label class="field-label">Year *</label>
            <select v-model="form.year" class="field-input">
              <option value="">Year</option>
              <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
          </div>
          <div><label class="field-label">Mileage (km)</label><input v-model.number="form.mileage" type="number" placeholder="e.g. 45000" class="field-input" /></div>
          <div>
            <label class="field-label">Fuel Type</label>
            <select v-model="form.fuel_type" class="field-input">
              <option value="">Select</option>
              <option v-for="f in fuels" :key="f" :value="f">{{ f }}</option>
            </select>
          </div>
          <div>
            <label class="field-label">Transmission</label>
            <select v-model="form.transmission" class="field-input">
              <option value="">Select</option>
              <option value="Automatic">Automatic</option>
              <option value="Manual">Manual</option>
            </select>
          </div>
          <div>
            <label class="field-label">Body Type</label>
            <select v-model="form.body_type" class="field-input">
              <option value="">Select</option>
              <option v-for="b in bodies" :key="b" :value="b">{{ b }}</option>
            </select>
          </div>
          <div><label class="field-label">Color</label><input v-model="form.color" placeholder="e.g. Silver" class="field-input" /></div>
          <div><label class="field-label">Engine Size</label><input v-model="form.engine_size" placeholder="e.g. 1.6L" class="field-input" /></div>
          <div>
            <label class="field-label">Condition *</label>
            <select v-model="form.condition" class="field-input">
              <option value="">Select</option>
              <option v-for="c in conditions" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>
        </div>
        <div><label class="field-label">Title *</label><input v-model="form.title" placeholder="e.g. 2020 Toyota Corolla — Full Service History" class="field-input" /></div>
        <div><label class="field-label">Description</label><textarea v-model="form.description" rows="3" placeholder="Describe the vehicle..." class="field-input resize-none"></textarea></div>
      </div>

      <div v-if="form.type === 'property'" class="space-y-4 mb-8">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="field-label">Property Type *</label>
            <select v-model="form.property_type_name" class="field-input">
              <option value="">Select</option>
              <option v-for="pt in propTypesFromApi" :key="pt.id" :value="pt.name">{{ pt.name }}</option>
            </select>
          </div>
          <div>
            <label class="field-label">Listing For *</label>
            <select v-model="form.listing_for" class="field-input">
              <option value="sale">For Sale</option>
              <option value="rent">For Rent</option>
            </select>
          </div>
          <div><label class="field-label">Bedrooms</label><input v-model.number="form.bedrooms" type="number" min="0" max="20" class="field-input" /></div>
          <div><label class="field-label">Bathrooms</label><input v-model.number="form.bathrooms" type="number" min="1" max="20" class="field-input" /></div>
          <div><label class="field-label">Area (m²)</label><input v-model.number="form.area_sqm" type="number" placeholder="e.g. 90" class="field-input" /></div>
          <div><label class="field-label">Build Year</label><input v-model.number="form.build_year" type="number" placeholder="e.g. 2018" class="field-input" /></div>
        </div>
        <div><label class="field-label">Title *</label><input v-model="form.title" placeholder="e.g. 2BR Sea View Apartment in Kyrenia" class="field-input" /></div>
        <div><label class="field-label">Description</label><textarea v-model="form.description" rows="3" placeholder="Describe the property..." class="field-input resize-none"></textarea></div>
        <div class="grid grid-cols-2 gap-3">
          <label v-for="a in amenities" :key="a.key" class="flex items-center gap-2 text-sm cursor-pointer p-3 rounded-xl" style="background:var(--surface-overlay-soft);border:1px solid var(--border-subtle);color:var(--text-secondary)">
            <input type="checkbox" v-model="(form as any)[a.key]" class="accent-teal-400" />
            {{ a.label }}
          </label>
        </div>
      </div>

      <div class="flex justify-between">
        <button @click="step--" class="px-6 py-3 rounded-xl font-semibold text-sm" style="background:var(--border-subtle);color:var(--text-secondary)">Back</button>
        <button @click="nextStep" :disabled="!canGoStep2" class="px-8 py-3 rounded-xl font-bold text-sm disabled:opacity-40" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">Continue</button>
      </div>
    </div>

    <!-- STEP 3: Photos -->
    <div v-if="step === 3">
      <h2 class="text-lg font-bold mb-2" style="color:var(--text-primary)">Photos</h2>
      <p class="text-xs mb-6" style="color:var(--text-muted)">Upload at least 3 photos. More photos = higher Trust Score.</p>

      <!-- Upload zone -->
      <div class="border-2 border-dashed rounded-2xl p-10 text-center mb-4 cursor-pointer transition-all"
        style="border-color:rgba(111,255,210,0.2);background:rgba(111,255,210,0.02)"
        :style="dragOver ? 'border-color:var(--accent-teal);background:rgba(111,255,210,0.06)' : ''"
        @dragover.prevent="dragOver=true" @dragleave="dragOver=false" @drop.prevent="handleDrop"
        @click="fileInputRef?.click()">
        <div class="flex justify-center mb-3">
          <svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="#6fffd2" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        </div>
        <p class="font-semibold mb-2" style="color:var(--text-primary)">Drag &amp; drop photos here</p>
        <p class="text-xs mb-4" style="color:var(--text-muted)">JPG, PNG, WebP up to 10MB each</p>
        <span class="px-6 py-2.5 rounded-xl text-sm font-semibold" style="background:rgba(111,255,210,0.1);color:var(--accent-teal);border:1px solid rgba(111,255,210,0.2)">Browse Files</span>
        <input ref="fileInputRef" type="file" multiple accept="image/*" class="hidden" @change="handleFileSelect" />
      </div>

      <!-- Preview grid -->
      <div v-if="photoFiles.length > 0" class="grid grid-cols-3 md:grid-cols-5 gap-3 mb-6">
        <div v-for="(f, i) in photoFiles" :key="i" class="relative aspect-square rounded-xl overflow-hidden" style="background:var(--surface-overlay)">
          <img :src="f.preview" class="w-full h-full object-cover" />
          <div v-if="i === 0" class="absolute top-1 left-1 px-1.5 py-0.5 rounded text-xs font-bold" style="background:#6fffd2;color:var(--on-accent)">Cover</div>
          <button @click="removePhoto(i)" class="absolute top-1 right-1 w-6 h-6 rounded-full flex items-center justify-center" style="background:rgba(239,68,68,0.8);color:#fff">
            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
      </div>
      <p class="text-xs mb-6" style="color:var(--text-muted)">{{ photoFiles.length }} photo(s) selected</p>

      <div>
        <label class="field-label">Video URL (YouTube/Vimeo) — Optional</label>
        <input v-model="form.video_url" placeholder="https://youtube.com/..." class="field-input" />
      </div>
      <div class="flex justify-between mt-6">
        <button @click="step--" class="px-6 py-3 rounded-xl font-semibold text-sm" style="background:var(--border-subtle);color:var(--text-secondary)">Back</button>
        <button @click="nextStep" class="px-8 py-3 rounded-xl font-bold text-sm" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">Continue</button>
      </div>
    </div>

    <!-- STEP 4: Location -->
    <div v-if="step === 4">
      <h2 class="text-lg font-bold mb-6" style="color:var(--text-primary)">Location</h2>
      <div class="grid grid-cols-2 gap-4 mb-8">
        <div>
          <label class="field-label">City *</label>
          <select v-model="form.city" class="field-input">
            <option value="">Select city</option>
            <option v-for="c in cities" :key="c" :value="c">{{ c }}</option>
          </select>
        </div>
        <div><label class="field-label">District / Area</label><input v-model="form.district" placeholder="e.g. Bellapais" class="field-input" /></div>
      </div>
      <div class="flex justify-between">
        <button @click="step--" class="px-6 py-3 rounded-xl font-semibold text-sm" style="background:var(--border-subtle);color:var(--text-secondary)">Back</button>
        <button @click="nextStep" :disabled="!form.city" class="px-8 py-3 rounded-xl font-bold text-sm disabled:opacity-40" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">Continue</button>
      </div>
    </div>

    <!-- STEP 5: Price + AI -->
    <div v-if="step === 5">
      <h2 class="text-lg font-bold mb-2" style="color:var(--text-primary)">Price &amp; AI Analysis</h2>
      <p class="text-xs mb-6" style="color:var(--text-muted)">Enter your price. AI will analyze the market and give you a score. You can change the price and re-analyze anytime.</p>

      <div class="grid grid-cols-2 gap-4 mb-4">
        <div>
          <label class="field-label">Currency</label>
          <select v-model="form.currency" class="field-input">
            <option value="TRY">TRY</option>
            <option value="GBP">GBP</option>
            <option value="USD">USD</option>
            <option value="EUR">EUR</option>
          </select>
        </div>
        <div>
          <label class="field-label">Asking Price *</label>
          <input v-model.number="form.price" type="number" placeholder="0" class="field-input" />
        </div>
      </div>

      <label class="flex items-center gap-3 mb-6 cursor-pointer">
        <input type="checkbox" v-model="form.price_negotiable" />
        <span class="text-sm" style="color:var(--text-secondary)">Price is negotiable</span>
      </label>

      <!-- AI Button — shows if no result OR if price changed -->
      <button @click="runAi" :disabled="!form.price || aiLoading" class="w-full py-4 rounded-2xl font-bold mb-6 disabled:opacity-40 flex items-center justify-center gap-3"
        style="background:linear-gradient(135deg,rgba(111,255,210,0.1),rgba(86,216,255,0.1));border:2px solid rgba(111,255,210,0.3);color:var(--accent-teal)">
        <svg v-if="!aiLoading" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c1 1 .23 2.717-1.07 2.717H3.866c-1.3 0-2.07-1.716-1.07-2.717L4.17 15.3"/></svg>
        <svg v-else class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
        {{ aiLoading ? 'AI Analyzing...' : aiResult ? 'Re-Analyze Price' : 'Run AI Price Analysis' }}
      </button>

      <!-- AI Result -->
      <div v-if="aiResult" class="rounded-2xl p-6 mb-6" style="background:linear-gradient(135deg,rgba(111,255,210,0.06),rgba(86,216,255,0.03));border:2px solid rgba(111,255,210,0.25)">
        <div class="flex items-center gap-3 mb-5">
          <div class="w-8 h-8 rounded-xl flex items-center justify-center text-xs font-black" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">AI</div>
          <div>
            <p class="font-bold" style="color:var(--text-primary)">AI Price Analysis</p>
            <p class="text-xs" style="color:var(--text-muted)">{{ aiResult.comparables }} comparable listings · {{ new Date().toLocaleTimeString('en-GB') }}</p>
          </div>
        </div>
        <div class="flex items-center gap-4 p-4 rounded-xl mb-5" style="background:var(--surface-overlay-soft);border:1px solid var(--border-subtle)">
          <div class="shrink-0">
            <p class="text-xs mb-1" style="color:var(--text-muted)">Price Battery</p>
            <p class="text-4xl font-black" :style="{color: batteryColor}">{{ aiResult.battery_score }}%</p>
            <p class="text-sm font-bold mt-1" :style="{color: batteryColor}">{{ aiResult.battery_label }}</p>
          </div>
          <div class="flex-1">
            <div class="h-6 rounded-lg overflow-hidden mb-2" style="background:var(--border-subtle)">
              <div class="h-full rounded-lg transition-all duration-1000" :style="'width:'+aiResult.battery_score+'%;background:'+batteryColor"></div>
            </div>
            <div class="flex justify-between text-xs" style="color:var(--text-muted)">
              <span>Min: {{ fmtPrice(aiResult.market_min) }}</span>
              <span>Avg: {{ fmtPrice(aiResult.market_avg) }}</span>
              <span>Max: {{ fmtPrice(aiResult.market_max) }}</span>
            </div>
          </div>
        </div>
        <div class="grid grid-cols-3 gap-3">
          <div class="text-center p-3 rounded-xl" style="background:var(--surface-overlay-soft)">
            <p class="text-xl font-black" style="color:var(--accent-teal)">{{ aiResult.trust_score ?? '—' }}<span v-if="aiResult.trust_score !== null">%</span></p>
            <p class="text-xs" style="color:var(--text-muted)">Trust</p>
          </div>
          <div class="text-center p-3 rounded-xl" style="background:var(--surface-overlay-soft)">
            <p class="text-xl font-black" style="color:var(--accent-cyan)">{{ aiResult.invest_score ?? '—' }}<span v-if="aiResult.invest_score !== null">%</span></p>
            <p class="text-xs" style="color:var(--text-muted)">Invest</p>
          </div>
          <div class="text-center p-3 rounded-xl" style="background:var(--surface-overlay-soft)">
            <p class="text-xl font-black" style="color:var(--accent-violet)">{{ aiResult.demand_score ?? '—' }}<span v-if="aiResult.demand_score !== null">%</span></p>
            <p class="text-xs" style="color:var(--text-muted)">Demand</p>
          </div>
        </div>
        <p v-if="aiResult.trust_score === null" class="text-xs text-center mt-3" style="color:var(--text-muted)">
          Trust/Invest/Demand need at least one scored comparable listing in this market — showing price data only for now.
        </p>
      </div>
      <p v-if="error" class="text-xs text-center mb-4" style="color:#f87171">{{ error }}</p>

      <div class="flex justify-between">
        <button @click="step--" class="px-6 py-3 rounded-xl font-semibold text-sm" style="background:var(--border-subtle);color:var(--text-secondary)">Back</button>
        <button @click="submitListing" :disabled="!form.price || submitting" class="px-8 py-3 rounded-xl font-bold text-sm disabled:opacity-40" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">
          {{ submitting ? 'Submitting...' : 'Submit Listing' }}
        </button>
      </div>
    </div>

    <!-- Success -->
    <div v-if="step === 6" class="text-center py-20">
      <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" style="background:rgba(111,255,210,0.1);border:2px solid #6fffd2">
        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="#6fffd2" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
      </div>
      <h2 class="text-2xl font-black mb-2" style="color:var(--text-primary)">Listing Submitted!</h2>
      <p class="text-sm mb-6" style="color:var(--text-muted)">Your listing is under review. Admin will approve it shortly.</p>
      <RouterLink to="/dashboard/listings" class="px-6 py-3 rounded-xl font-bold text-sm" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">View My Listings</RouterLink>
    </div>
  </div>
</template>
<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import client from '@/api/client'

interface PhotoFile { file: File; preview: string }
interface AiResult {
  battery_score: number
  battery_label: string
  market_avg: number
  market_min: number
  market_max: number
  comparables: number
  confidence?: number
  reasons?: string[]
  trust_score: number | null
  invest_score: number | null
  demand_score: number | null
}
interface ListingForm {
  type: string
  title: string
  description: string
  brand_name: string
  model_name: string
  model_name_custom: string
  year: number | ''
  mileage: number | ''
  fuel_type: string
  transmission: string
  body_type: string
  color: string
  engine_size: string
  condition: string
  property_type_name: string
  listing_for: string
  bedrooms: number | ''
  bathrooms: number | ''
  area_sqm: number | ''
  build_year: number | ''
  has_parking: boolean
  has_garden: boolean
  has_pool: boolean
  has_elevator: boolean
  city: string
  district: string
  price: number | ''
  currency: string
  price_negotiable: boolean
  video_url: string
}

const router = useRouter()
const step = ref(0)
const aiLoading = ref(false)
const submitting = ref(false)
const error = ref('')
const aiResult = ref<AiResult | null>(null)
const freeText = ref('')
const extracting = ref(false)
const extractError = ref('')
const missingFields = ref<string[]>([])
const lastAnalyzedPrice = ref<number | ''>('')
const fileInputRef = ref<HTMLInputElement | null>(null)
const dragOver = ref(false)
const photoFiles = ref<PhotoFile[]>([])
const brandsFromApi = ref<Array<{ id: string; name: string; models: Array<{ id: string; name: string }> }>>([])
const propTypesFromApi = ref<Array<{ id: string; name: string }>>([])

const form = ref<ListingForm>({
  type: '', title: '', description: '',
  brand_name: '', model_name: '', model_name_custom: '',
  year: '', mileage: '', fuel_type: '', transmission: '',
  body_type: '', color: '', engine_size: '', condition: '',
  property_type_name: '', listing_for: 'sale',
  bedrooms: '', bathrooms: '', area_sqm: '', build_year: '',
  has_parking: false, has_garden: false, has_pool: false, has_elevator: false,
  city: '', district: '',
  price: '', currency: 'GBP', price_negotiable: false, video_url: '',
})

const stepLabels = ['Select Type', 'Details', 'Photos', 'Location', 'Price & AI']
const years = Array.from({ length: 35 }, (_, i) => 2025 - i)
const fuels = ['Petrol', 'Diesel', 'Hybrid', 'Electric', 'LPG', 'CNG']
const bodies = ['Sedan', 'Hatchback', 'SUV', 'Crossover', 'MPV', 'Coupe', 'Pickup', 'Van', 'Wagon', 'Convertible']
const conditions = ['Excellent', 'Very Good', 'Good', 'Fair', 'Needs Work']
const cities = ['Girne', 'Lefkosa', 'Gazimağusa', 'Iskele', 'Guzelyurt', 'Lefke']
const amenities = [
  { key: 'has_parking', label: 'Parking' },
  { key: 'has_garden', label: 'Garden' },
  { key: 'has_pool', label: 'Swimming Pool' },
  { key: 'has_elevator', label: 'Elevator' },
]
const types = [
  { value: 'vehicle', label: 'Car / Vehicle', desc: 'Cars, vans, motorcycles', rgb: '111,255,210', iconPath: '<path stroke-linecap="round" stroke-linejoin="round" stroke="rgb(111,255,210)" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>' },
  { value: 'property', label: 'Property', desc: 'Apartments, villas, houses', rgb: '86,216,255', iconPath: '<path stroke-linecap="round" stroke-linejoin="round" stroke="rgb(86,216,255)" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>' },
  { value: 'rental', label: 'Rental', desc: 'Short or long-term rental', rgb: '167,139,250', iconPath: '<path stroke-linecap="round" stroke-linejoin="round" stroke="rgb(167,139,250)" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/>' },
]

// Load brands + property types on mount
import { onMounted } from 'vue'
onMounted(async () => {
  const [b, p] = await Promise.all([
    client.get('/vehicle-brands').catch(() => ({ data: [] })),
    client.get('/property-types').catch(() => ({ data: [] })),
  ])
  brandsFromApi.value = b.data || []
  propTypesFromApi.value = p.data || []
})

const modelsForBrand = computed(() => {
  if (!form.value.brand_name) return []
  const b = brandsFromApi.value.find(b => b.name === form.value.brand_name)
  return b?.models ?? []
})

const canGoStep2 = computed(() => {
  if (form.value.type === 'vehicle') return !!(form.value.brand_name && form.value.model_name && form.value.year && form.value.title)
  if (form.value.type === 'property') return !!(form.value.property_type_name && form.value.title)
  return !!form.value.title
})

const batteryColor = computed(() => {
  if (!aiResult.value) return 'var(--text-muted)'
  if (aiResult.value.battery_score >= 85) return '#6fffd2'
  if (aiResult.value.battery_score >= 60) return '#fbbf24'
  return '#ef4444'
})

// Reset AI result when price changes
watch(() => form.value.price, (newVal) => {
  if (newVal !== lastAnalyzedPrice.value) {
    // Don't clear result — just mark it as outdated so user sees Re-Analyze
  }
})

function nextStep() { if (step.value < 5) step.value++ }

function titleCase(s: string) {
  return s.charAt(0).toUpperCase() + s.slice(1).toLowerCase()
}

interface ExtractedListing {
  type: 'vehicle' | 'property' | null
  title: string | null
  description: string | null
  price: number | null
  currency: string | null
  city: string | null
  district: string | null
  brand_match?: string
  model_match?: string
  year: number | null
  mileage: number | null
  fuel_type: string | null
  transmission: string | null
  body_type: string | null
  color: string | null
  condition: string | null
  property_type_match?: string
  bedrooms: number | null
  bathrooms: number | null
  area_sqm: number | null
  build_year: number | null
  has_parking: boolean | null
  has_garden: boolean | null
  has_pool: boolean | null
  missing_required?: string[]
}

async function runExtraction() {
  if (freeText.value.trim().length < 20) return
  extracting.value = true
  extractError.value = ''
  try {
    const r = await client.post('/listings/extract', { text: freeText.value.trim() })
    const x = r.data.extracted as ExtractedListing

    if (x.type === 'vehicle' || x.type === 'property') form.value.type = x.type
    if (x.title) form.value.title = x.title
    if (x.description) form.value.description = x.description
    if (x.price) form.value.price = x.price
    if (x.currency && ['TRY', 'GBP', 'USD', 'EUR'].includes(x.currency)) form.value.currency = x.currency
    if (x.city) form.value.city = x.city
    if (x.district) form.value.district = x.district

    if (x.type === 'vehicle') {
      if (x.brand_match) form.value.brand_name = x.brand_match
      if (x.year) form.value.year = x.year
      if (x.mileage) form.value.mileage = x.mileage
      if (x.fuel_type) form.value.fuel_type = titleCase(x.fuel_type)
      if (x.transmission) form.value.transmission = titleCase(x.transmission)
      if (x.body_type) form.value.body_type = titleCase(x.body_type)
      if (x.color) form.value.color = titleCase(x.color)
      // Model select depends on the brand's option list being loaded — wait a tick.
      if (x.model_match) {
        await new Promise(res => setTimeout(res, 0))
        const known = modelsForBrand.value.some(m => m.name === x.model_match)
        if (known) form.value.model_name = x.model_match!
        else { form.value.model_name = '_other'; form.value.model_name_custom = x.model_match! }
      }
    }
    if (x.type === 'property') {
      if (x.property_type_match) form.value.property_type_name = x.property_type_match
      if (x.bedrooms !== null) form.value.bedrooms = x.bedrooms
      if (x.bathrooms !== null) form.value.bathrooms = x.bathrooms
      if (x.area_sqm !== null) form.value.area_sqm = x.area_sqm
      if (x.build_year !== null) form.value.build_year = x.build_year
      if (x.has_parking !== null) form.value.has_parking = x.has_parking
      if (x.has_garden !== null) form.value.has_garden = x.has_garden
      if (x.has_pool !== null) form.value.has_pool = x.has_pool
    }

    missingFields.value = x.missing_required ?? []
    step.value = x.type ? 2 : 1
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    extractError.value = err?.response?.data?.message || 'Could not read that description. Try adding more detail, or fill the form manually.'
  } finally {
    extracting.value = false
  }
}

function handleFileSelect(e: Event) {
  const input = e.target as HTMLInputElement
  if (!input.files) return
  addFiles(Array.from(input.files))
}

function handleDrop(e: DragEvent) {
  dragOver.value = false
  if (!e.dataTransfer?.files) return
  addFiles(Array.from(e.dataTransfer.files))
}

function addFiles(files: File[]) {
  for (const file of files) {
    if (!file.type.startsWith('image/')) continue
    photoFiles.value.push({ file, preview: URL.createObjectURL(file) })
  }
}

function removePhoto(i: number) {
  URL.revokeObjectURL(photoFiles.value[i].preview)
  photoFiles.value.splice(i, 1)
}

async function runAi() {
  if (!form.value.price) return
  aiLoading.value = true
  error.value = ''
  try {
    const r = await client.post('/ai/price-estimate', {
      type: form.value.type,
      price: form.value.price,
      city: form.value.city,
    })
    aiResult.value = r.data as AiResult
    lastAnalyzedPrice.value = form.value.price
  } catch {
    // Honest failure state — never fabricate scores. See DEVELOPMENT.md
    // "no static/random data in production flows."
    aiResult.value = null
    error.value = 'AI price analysis is temporarily unavailable. You can still submit — pricing will be analyzed once the service is back.'
  } finally {
    aiLoading.value = false
  }
}

async function submitListing() {
  if (!form.value.price) return
  submitting.value = true
  error.value = ''
  try {
    const modelName = form.value.model_name === '_other' ? form.value.model_name_custom : form.value.model_name
    const payload = {
      type: form.value.type,
      title: form.value.title,
      description: form.value.description,
      price: form.value.price,
      currency: form.value.currency,
      city: form.value.city,
      district: form.value.district,
      price_negotiable: form.value.price_negotiable,
      video_url: form.value.video_url || undefined,
      // vehicle
      brand_name: form.value.brand_name || undefined,
      model_name: modelName || undefined,
      year: form.value.year || undefined,
      mileage: form.value.mileage || undefined,
      fuel_type: form.value.fuel_type || undefined,
      transmission: form.value.transmission || undefined,
      body_type: form.value.body_type || undefined,
      color: form.value.color || undefined,
      engine_size: form.value.engine_size || undefined,
      condition: form.value.condition || undefined,
      // property
      property_type_name: form.value.property_type_name || undefined,
      listing_for: form.value.listing_for || undefined,
      bedrooms: form.value.bedrooms || undefined,
      bathrooms: form.value.bathrooms || undefined,
      area_sqm: form.value.area_sqm || undefined,
      build_year: form.value.build_year || undefined,
      has_parking: form.value.has_parking,
      has_garden: form.value.has_garden,
      has_pool: form.value.has_pool,
      has_elevator: form.value.has_elevator,
    }
    const r = await client.post('/listings/create', payload)
    const listingId: string = r.data.listing.id

    // Upload images if any
    if (photoFiles.value.length > 0) {
      const fd = new FormData()
      photoFiles.value.forEach(f => fd.append('images[]', f.file))
      await client.post('/listings/' + listingId + '/upload-images', fd, {
        headers: { 'Content-Type': 'multipart/form-data' }
      }).catch(() => {}) // non-fatal
    }

    step.value = 6
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    const msg = err?.response?.data?.message
    const errs = err?.response?.data?.errors
    if (errs) {
      error.value = Object.values(errs).flat().join(', ')
    } else {
      error.value = msg || 'Submission failed. Please try again.'
    }
  } finally {
    submitting.value = false
  }
}

function fmtPrice(v: number) {
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency: form.value.currency || 'GBP', maximumFractionDigits: 0 }).format(v)
}
</script>
<style>
.field-label { display:block; font-size:12px; font-weight:500; margin-bottom:6px; color:var(--text-muted); }
.field-input { width:100%; padding:10px 14px; border-radius:12px; font-size:13px; outline:none; background:var(--surface-overlay); border:1px solid var(--border-strong); color:var(--text-primary); }
.field-input option { background:var(--bg-surface); }
</style>