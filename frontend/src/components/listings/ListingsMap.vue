<template>
  <div class="map-shell" :style="{ height: height + 'px' }">
    <!-- Type filter -->
    <div class="map-filters">
      <button v-for="f in typeFilters" :key="f.value" @click="setType(f.value)"
        class="map-filter-chip inline-flex items-center gap-1.5" :class="{ 'map-filter-chip--active': activeType === f.value }"
        :aria-pressed="activeType === f.value">
        <AssetIcon :type="(f.value || 'all') as any" :size="13" /> {{ t('map.' + (f.value || 'all')) }}
      </button>
      <span v-if="!loading" class="map-count">{{ t('map.count', { n: markers.length }) }}</span>
    </div>

    <div ref="mapEl" class="map-canvas"></div>

    <div v-if="loading" class="map-loading">
      <div class="map-loading-pulse"></div>
      <span>{{ t('map.loading') }}</span>
    </div>
    <div v-else-if="!markers.length" class="map-empty" role="status">{{ t('map.empty') }}</div>

    <RouterLink v-if="compact" to="/search?view=map" class="map-expand-link">{{ t('map.open') }}</RouterLink>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { RouterLink } from 'vue-router'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import client from '@/api/client'
import AssetIcon from '@/components/ui/AssetIcon.vue'
import { useI18n } from '@/i18n'

const { t } = useI18n()

const props = withDefaults(defineProps<{ height?: number; compact?: boolean; initialType?: string }>(), {
  height: 520,
  compact: false,
  initialType: '',
})

interface MapListing {
  id: string; slug: string; title: string; type: string; price: number; currency: string
  city: string | null; latitude: number; longitude: number
  thumbnail: string | null; price_battery_percent: number | null; ai_price_status: string | null
}

const typeFilters = [
  { value: '', label: 'All', color: 'var(--accent-cyan)' },
  { value: 'vehicle', label: 'Cars', color: 'var(--accent-teal)' },
  { value: 'property', label: 'Properties', color: 'var(--accent-violet)' },
]

const mapEl = ref<HTMLElement | null>(null)
const loading = ref(true)
const activeType = ref(props.initialType)
const markers = ref<MapListing[]>([])

let map: L.Map | null = null
let markerLayer: L.LayerGroup | null = null

const NORTH_CYPRUS_BOUNDS: L.LatLngBoundsExpression = [[35.00, 32.25], [35.75, 34.60]]
const NORTH_CYPRUS_CENTER: L.LatLngExpression = [35.28, 33.45]

const TYPE_COLOR: Record<string, string> = { vehicle: '#159BF2', property: '#16C784', rental: '#12C5E8' }

function shortPrice(price: number, currency: string): string {
  const sym: Record<string, string> = { GBP: '£', EUR: '€', USD: '$', TRY: '₺' }
  const n = Number(price)
  const v = n >= 1_000_000 ? (n / 1_000_000).toFixed(n >= 10_000_000 ? 0 : 1) + 'M' : n >= 1000 ? Math.round(n / 1000) + 'k' : String(Math.round(n))
  return (sym[currency] ?? currency + ' ') + v
}

function buildIcon(listing: MapListing): L.DivIcon {
  const color = TYPE_COLOR[listing.type] ?? TYPE_COLOR.vehicle
  const label = esc(shortPrice(listing.price, listing.currency))
  return L.divIcon({
    className: 'ai-map-marker-wrap',
    html: `<span class="ai-pin" style="--pin:${color}"><span class="ai-pin-dot"></span>${label}</span>`,
    iconSize: [72, 30],
    iconAnchor: [36, 30],
    popupAnchor: [0, -28],
  })
}

/** Pins at the same spot are fanned out in a small circle so each stays clickable. */
function spread(list: MapListing[]): [number, number][] {
  const seen = new Map<string, number>()
  return list.map((l) => {
    const key = `${l.latitude.toFixed(3)},${l.longitude.toFixed(3)}`
    const i = seen.get(key) ?? 0
    seen.set(key, i + 1)
    if (i === 0) return [l.latitude, l.longitude]
    const angle = i * 2.4
    const r = 0.0025 * Math.sqrt(i)
    return [l.latitude + r * Math.sin(angle), l.longitude + r * Math.cos(angle)]
  })
}

function esc(v: unknown): string {
  return String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c] as string)
}

function popupHtml(listing: MapListing): string {
  const price = Number(listing.price).toLocaleString() + ' ' + esc(listing.currency)
  return `
    <a href="/listings/${encodeURIComponent(String(listing.slug))}" class="ai-map-popup">
      ${listing.thumbnail ? `<img src="${esc(listing.thumbnail)}" alt="" class="ai-map-popup-img" />` : ''}
      <div class="ai-map-popup-body">
        <p class="ai-map-popup-title">${esc(listing.title)}</p>
        <p class="ai-map-popup-price">${price}</p>
        <div class="ai-map-popup-meta">
          <span>${esc(listing.city)}</span>

        </div>
        <span class="ai-map-popup-cta">${esc(t('map.view'))}</span>
      </div>
    </a>`
}

async function loadMarkers() {
  loading.value = true
  try {
    const { data } = await client.get('/listings-map', { params: activeType.value ? { type: activeType.value } : {} })
    markers.value = (data.listings ?? []).map((l: MapListing) => ({ ...l, latitude: Number(l.latitude), longitude: Number(l.longitude) }))
    renderMarkers()
  } catch {
    markers.value = []
  } finally {
    loading.value = false
  }
}

function renderMarkers() {
  if (!map || !markerLayer) return
  markerLayer.clearLayers()

  const points: L.LatLngExpression[] = []
  const positions = spread(markers.value)
  markers.value.forEach((listing, i) => {
    const latlng = positions[i] as L.LatLngExpression
    points.push(latlng)
    const marker = L.marker(latlng, { icon: buildIcon(listing) })
    marker.bindPopup(popupHtml(listing), { closeButton: true, className: 'ai-map-popup-wrap', maxWidth: 220 })
    markerLayer!.addLayer(marker)
  })

  if (points.length) {
    map.flyToBounds(L.latLngBounds(points), { padding: [40, 40], maxZoom: 13, duration: 0.8 })
  }
}

function setType(value: string) {
  activeType.value = value
  loadMarkers()
}

onMounted(() => {
  if (!mapEl.value) return

  map = L.map(mapEl.value, {
    center: NORTH_CYPRUS_CENTER,
    zoom: 10,
    minZoom: 9,
    maxZoom: 16,
    maxBounds: NORTH_CYPRUS_BOUNDS,
    maxBoundsViscosity: 0.8,
    zoomControl: !props.compact,
    scrollWheelZoom: !props.compact,
    dragging: true,
    attributionControl: false,
  })

  // OpenStreetMap standard tiles: no API key, attribution required by the
  // OSM tile policy. Override with VITE_MAP_TILE_URL for a commercial provider
  // once traffic grows (the public OSM servers are for light use only).
  const tileUrl = import.meta.env.VITE_MAP_TILE_URL || 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'
  L.tileLayer(tileUrl, {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
  }).addTo(map)
  L.control.attribution({ prefix: false }).addTo(map)

  markerLayer = L.layerGroup().addTo(map)
  loadMarkers()
})

onBeforeUnmount(() => {
  map?.remove()
  map = null
})

watch(() => props.initialType, (v) => { if (v !== undefined) setType(v) })
</script>

<style scoped>
.map-shell {
  position: relative;
  border-radius: 24px;
  overflow: hidden;
  border: 1px solid rgba(var(--accent-teal-rgb), 0.18);
  background: var(--bg-base);
}
.map-canvas { width: 100%; height: 100%; }
.map-filters {
  position: absolute; top: 14px; left: 14px; z-index: 500;
  display: flex; align-items: center; gap: 8px;
  background: var(--bg-surface); box-shadow: 0 6px 20px rgba(8,24,39,0.12);
  padding: 6px; border-radius: 14px; border: 1px solid var(--border-subtle);
}
.map-filter-chip {
  font-size: 12px; font-weight: 700; padding: 6px 12px; border-radius: 10px;
  background: var(--surface-overlay); color: var(--text-secondary); border: none; cursor: pointer; transition: all 0.15s;
}
.map-filter-chip--active { background: var(--brand-blue, #159BF2); color: #fff; }
.map-empty { position: absolute; inset: auto 0 18px 0; margin: 0 auto; width: max-content; max-width: 90%; z-index: 450; background: var(--bg-surface); color: var(--text-secondary); border: 1px solid var(--border-subtle); padding: 8px 14px; border-radius: 12px; font-size: 13px; box-shadow: 0 6px 20px rgba(8,24,39,0.12); }
.map-count { font-size: 11px; color: var(--text-muted); padding-left: 6px; }
.map-expand-link {
  position: absolute; bottom: 14px; right: 14px; z-index: 500;
  background: var(--brand-blue, #159BF2); color: #fff;
  font-size: 12px; font-weight: 800; padding: 8px 14px; border-radius: 10px; text-decoration: none;
}
.map-loading {
  position: absolute; inset: 0; z-index: 400; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px;
  background: var(--bg-base); color: var(--accent-teal); font-size: 13px; font-weight: 600;
}
.map-loading-pulse {
  width: 40px; height: 40px; border-radius: 50%; border: 2px solid rgba(var(--accent-teal-rgb),0.3); border-top-color: var(--accent-teal);
  animation: map-spin 0.9s linear infinite;
}
@keyframes map-spin { to { transform: rotate(360deg); } }
</style>

<style>
/* Global (unscoped) — Leaflet renders markers/popups outside this component's scoped tree. */
.ai-map-marker-wrap { background: none; border: none; }
.ai-pin {
  position: absolute; left: 50%; bottom: 6px; transform: translateX(-50%);
  display: inline-flex; align-items: center; gap: 5px; white-space: nowrap;
  padding: 4px 9px; border-radius: 999px; font: 800 12px/1.2 inherit;
  background: #fff; color: #081827; border: 2px solid var(--pin);
  box-shadow: 0 3px 10px rgba(8,24,39,0.25); transition: transform .15s;
}
.ai-pin::after { content: ''; position: absolute; left: 50%; bottom: -7px; transform: translateX(-50%); border: 6px solid transparent; border-top-color: var(--pin); border-bottom: 0; }
.ai-pin-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--pin); }
.ai-map-marker-wrap:hover .ai-pin, .ai-map-marker-wrap:focus .ai-pin { transform: translateX(-50%) scale(1.08); z-index: 1000; }
.leaflet-popup-content-wrapper { background: var(--bg-surface) !important; color: var(--text-primary) !important; border-radius: 14px !important; border: 1px solid rgba(var(--accent-teal-rgb),0.25); padding: 0 !important; overflow: hidden; }
.leaflet-popup-content { margin: 0 !important; width: 220px !important; }
.leaflet-popup-tip { background: var(--bg-surface) !important; }
.ai-map-popup { display: block; text-decoration: none; color: inherit; }
.ai-map-popup-img { width: 100%; height: 110px; object-fit: cover; display: block; }
.ai-map-popup-body { padding: 10px 12px; }
.ai-map-popup-title { font-size: 12px; font-weight: 700; color: var(--text-primary); margin: 0 0 4px; }
.ai-map-popup-price { font-size: 14px; font-weight: 900; color: var(--accent-teal); margin: 0 0 6px; }
.ai-map-popup-meta { display: flex; justify-content: space-between; font-size: 10px; color: var(--text-muted); margin-bottom: 8px; }
.ai-map-popup-cta { display: block; font-size: 11px; font-weight: 800; color: var(--accent-cyan); }
.leaflet-container { background: #e8eef3 !important; font-family: inherit !important; }
.leaflet-control-zoom a { background: var(--bg-surface) !important; color: var(--accent-teal) !important; border-color: rgba(var(--accent-teal-rgb),0.2) !important; }
</style>
