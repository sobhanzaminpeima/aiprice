<template>
  <div class="map-shell" :style="{ height: height + 'px' }">
    <!-- Type filter -->
    <div class="map-filters">
      <button v-for="t in typeFilters" :key="t.value" @click="setType(t.value)"
        class="map-filter-chip inline-flex items-center gap-1.5" :class="{ 'map-filter-chip--active': activeType === t.value }"
        :style="activeType === t.value ? `background:${t.color};color:#05070b` : ''">
        <AssetIcon :type="(t.value || 'all') as any" :size="13" /> {{ t.label }}
      </button>
      <span v-if="!loading" class="map-count">{{ markers.length }} on map</span>
    </div>

    <div ref="mapEl" class="map-canvas"></div>

    <div v-if="loading" class="map-loading">
      <div class="map-loading-pulse"></div>
      <span>Loading AI Marketplace Map…</span>
    </div>

    <RouterLink v-if="compact" to="/search?view=map" class="map-expand-link">Open Full Map →</RouterLink>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { RouterLink } from 'vue-router'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import client from '@/api/client'
import AssetIcon from '@/components/ui/AssetIcon.vue'

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
  { value: '', label: 'All', color: '#56d8ff' },
  { value: 'vehicle', label: 'Cars', color: '#6fffd2' },
  { value: 'property', label: 'Properties', color: '#a78bfa' },
]

const mapEl = ref<HTMLElement | null>(null)
const loading = ref(true)
const activeType = ref(props.initialType)
const markers = ref<MapListing[]>([])

let map: L.Map | null = null
let markerLayer: L.LayerGroup | null = null

const NORTH_CYPRUS_BOUNDS: L.LatLngBoundsExpression = [[35.00, 32.25], [35.75, 34.60]]
const NORTH_CYPRUS_CENTER: L.LatLngExpression = [35.28, 33.45]

const batteryColor = (pct: number | null) => {
  if (pct === null) return '#6b7f96'
  if (pct >= 80) return '#6fffd2'
  if (pct >= 55) return '#fbbf24'
  return '#ef4444'
}

const MARKER_ICON_PATHS: Record<string, string> = {
  vehicle: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" stroke="currentColor" d="M8 6H5.5A2.5 2.5 0 003 8.5v2A1.5 1.5 0 004.5 12h15a1.5 1.5 0 001.5-1.5v-2A2.5 2.5 0 0018.5 6H16m-8 0l2-3h4l2 3M8 6h8M6 12v5a1 1 0 001 1h1m8-6v5a1 1 0 01-1 1h-1m-6 0h6"/>',
  property: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" stroke="currentColor" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>',
  rental: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" stroke="currentColor" d="M3 21h18M5 21V7l7-4 7 4v14M9 9h1m4 0h1m-6 4h1m4 0h1m-6 4h1m4 0h1"/>',
}

function buildIcon(listing: MapListing): L.DivIcon {
  const iconSvg = `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">${MARKER_ICON_PATHS[listing.type] ?? MARKER_ICON_PATHS.vehicle}</svg>`
  const ring = batteryColor(listing.price_battery_percent)
  return L.divIcon({
    className: 'ai-map-marker-wrap',
    html: `
      <div class="ai-map-marker" style="--ring:${ring}">
        <span class="ai-map-marker-pulse" style="background:${ring}"></span>
        <span class="ai-map-marker-badge">${iconSvg}</span>
      </div>`,
    iconSize: [40, 40],
    iconAnchor: [20, 20],
    popupAnchor: [0, -18],
  })
}

function popupHtml(listing: MapListing): string {
  const price = Number(listing.price).toLocaleString() + ' ' + listing.currency
  const battery = listing.price_battery_percent ? Math.round(listing.price_battery_percent) : null
  const ring = batteryColor(listing.price_battery_percent)
  return `
    <a href="/listings/${listing.slug}" class="ai-map-popup">
      ${listing.thumbnail ? `<img src="${listing.thumbnail}" class="ai-map-popup-img" />` : ''}
      <div class="ai-map-popup-body">
        <p class="ai-map-popup-title">${listing.title}</p>
        <p class="ai-map-popup-price">${price}</p>
        <div class="ai-map-popup-meta">
          <span>${listing.city ?? ''}</span>
          ${battery !== null ? `<span style="color:${ring};font-weight:800">${battery}% battery</span>` : ''}
        </div>
        <span class="ai-map-popup-cta">View Details →</span>
      </div>
    </a>`
}

async function loadMarkers() {
  loading.value = true
  try {
    const { data } = await client.get('/listings-map', { params: activeType.value ? { type: activeType.value } : {} })
    markers.value = data.listings ?? []
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
  for (const listing of markers.value) {
    const latlng: L.LatLngExpression = [listing.latitude, listing.longitude]
    points.push(latlng)
    const marker = L.marker(latlng, { icon: buildIcon(listing) })
    marker.bindPopup(popupHtml(listing), { closeButton: true, className: 'ai-map-popup-wrap', maxWidth: 220 })
    markerLayer.addLayer(marker)
  }

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

  // Dark tiles to match the site's theme — free, no API key required.
  L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
    subdomains: 'abcd',
    maxZoom: 19,
  }).addTo(map)

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
  border: 1px solid rgba(111, 255, 210, 0.18);
  background: #05070b;
}
.map-canvas { width: 100%; height: 100%; }
.map-filters {
  position: absolute; top: 14px; left: 14px; z-index: 500;
  display: flex; align-items: center; gap: 8px;
  background: rgba(5, 7, 11, 0.75); backdrop-filter: blur(8px);
  padding: 8px; border-radius: 14px; border: 1px solid rgba(111, 255, 210, 0.15);
}
.map-filter-chip {
  font-size: 12px; font-weight: 700; padding: 6px 12px; border-radius: 10px;
  background: rgba(255,255,255,0.05); color: #9fb3c8; border: none; cursor: pointer; transition: all 0.15s;
}
.map-filter-chip--active { box-shadow: 0 0 16px rgba(111,255,210,0.4); }
.map-count { font-size: 11px; color: #6b7f96; padding-left: 6px; }
.map-expand-link {
  position: absolute; bottom: 14px; right: 14px; z-index: 500;
  background: linear-gradient(135deg,#6fffd2,#56d8ff); color: #05070b;
  font-size: 12px; font-weight: 800; padding: 8px 14px; border-radius: 10px; text-decoration: none;
}
.map-loading {
  position: absolute; inset: 0; z-index: 400; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px;
  background: #05070b; color: #6fffd2; font-size: 13px; font-weight: 600;
}
.map-loading-pulse {
  width: 40px; height: 40px; border-radius: 50%; border: 2px solid rgba(111,255,210,0.3); border-top-color: #6fffd2;
  animation: map-spin 0.9s linear infinite;
}
@keyframes map-spin { to { transform: rotate(360deg); } }
</style>

<style>
/* Global (unscoped) — Leaflet renders markers/popups outside this component's scoped tree. */
.ai-map-marker-wrap { background: none; border: none; }
.ai-map-marker { position: relative; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; }
.ai-map-marker-pulse {
  position: absolute; inset: 6px; border-radius: 50%; opacity: 0.55;
  animation: ai-marker-pulse 2.2s ease-out infinite;
}
.ai-map-marker-badge {
  position: relative; width: 32px; height: 32px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center; font-size: 15px;
  background: rgba(5,7,11,0.92); border: 2px solid var(--ring, #6fffd2);
  box-shadow: 0 0 14px var(--ring, #6fffd2);
  transition: transform 0.15s;
}
.ai-map-marker:hover .ai-map-marker-badge { transform: scale(1.18); }
@keyframes ai-marker-pulse {
  0% { transform: scale(0.6); opacity: 0.6; }
  70% { transform: scale(1.9); opacity: 0; }
  100% { opacity: 0; }
}
.leaflet-popup-content-wrapper { background: #0d1117 !important; color: #f0f6fc !important; border-radius: 14px !important; border: 1px solid rgba(111,255,210,0.25); padding: 0 !important; overflow: hidden; }
.leaflet-popup-content { margin: 0 !important; width: 220px !important; }
.leaflet-popup-tip { background: #0d1117 !important; }
.ai-map-popup { display: block; text-decoration: none; color: inherit; }
.ai-map-popup-img { width: 100%; height: 110px; object-fit: cover; display: block; }
.ai-map-popup-body { padding: 10px 12px; }
.ai-map-popup-title { font-size: 12px; font-weight: 700; color: #f0f6fc; margin: 0 0 4px; }
.ai-map-popup-price { font-size: 14px; font-weight: 900; color: #6fffd2; margin: 0 0 6px; }
.ai-map-popup-meta { display: flex; justify-content: space-between; font-size: 10px; color: #6e7f96; margin-bottom: 8px; }
.ai-map-popup-cta { display: block; font-size: 11px; font-weight: 800; color: #56d8ff; }
.leaflet-container { background: #05070b !important; font-family: inherit !important; }
.leaflet-control-zoom a { background: #0d1117 !important; color: #6fffd2 !important; border-color: rgba(111,255,210,0.2) !important; }
</style>
