<template>
  <MainLayout>
    <!-- Route to the right detail component based on listing type -->
    <component :is="detailComponent" v-if="!loading && listingType" />

    <!-- Loading state -->
    <div v-if="loading" class="min-h-screen flex items-center justify-center">
      <div class="text-center">
        <div class="w-12 h-12 rounded-full border-2 animate-spin mx-auto mb-4" style="border-color:rgba(111,255,210,0.2);border-top-color:var(--accent-teal)"></div>
        <p style="color:var(--text-muted)">Loading...</p>
      </div>
    </div>

    <!-- Unknown type fallback -->
    <GenericDetailView v-if="!loading && listingType === 'other'" />
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import MainLayout from '@/layouts/MainLayout.vue'
import VehicleDetailView from '@/views/VehicleDetailView.vue'
import PropertyDetailView from '@/views/PropertyDetailView.vue'
import GenericDetailView from '@/views/GenericDetailView.vue'
import client from '@/api/client'

const route = useRoute()
const loading = ref(true)
const listingType = ref<string | null>(null)

const detailComponent = computed(() => {
  if (listingType.value === 'vehicle') return VehicleDetailView
  if (listingType.value === 'property') return PropertyDetailView
  return null
})

async function detectType() {
  loading.value = true
  try {
    const { data } = await client.get(`/listings/${route.params.slug}`)
    const l = data.listing ?? data
    listingType.value = l.type ?? 'other'
  } catch {
    listingType.value = 'other'
  } finally {
    loading.value = false
  }
}

onMounted(detectType)
watch(() => route.params.slug, detectType)
</script>
