import { defineStore } from 'pinia'
import { ref } from 'vue'
import client from '@/api/client'
import type { Listing } from '@/types'

export const useListingsStore = defineStore('listings', () => {
  const listings = ref<Listing[]>([])
  const currentListing = ref<Listing | null>(null)
  const pagination = ref({ current_page: 1, last_page: 1, total: 0, per_page: 12 })
  const loading = ref(false)
  const featured = ref<Listing[]>([])

  async function fetchListings(params: Record<string, any> = {}) {
    loading.value = true
    try {
      const res = await client.get('/listings', { params })
      listings.value = res.data.data
      pagination.value = {
        current_page: res.data.current_page,
        last_page: res.data.last_page,
        total: res.data.total,
        per_page: res.data.per_page,
      }
    } finally {
      loading.value = false
    }
  }

  async function fetchFeatured() {
    const res = await client.get('/listings/featured')
    featured.value = res.data
  }

  async function fetchListing(slug: string) {
    loading.value = true
    try {
      const res = await client.get(`/listings/${slug}`)
      currentListing.value = res.data.listing
      return res.data
    } finally {
      loading.value = false
    }
  }

  async function createListing(data: Record<string, any>) {
    const res = await client.post('/listings', data)
    return res.data
  }

  async function updateListing(id: string, data: Record<string, any>) {
    const res = await client.put(`/listings/${id}`, data)
    return res.data
  }

  async function deleteListing(id: string) {
    await client.delete(`/listings/${id}`)
  }

  async function toggleFavorite(id: string) {
    const res = await client.post(`/listings/${id}/favorite`)
    return res.data
  }

  async function sendInquiry(id: string, message: string) {
    const res = await client.post(`/listings/${id}/inquire`, { message })
    return res.data
  }

  return {
    listings, currentListing, pagination, loading, featured,
    fetchListings, fetchFeatured, fetchListing, createListing, updateListing, deleteListing, toggleFavorite, sendInquiry,
  }
})
