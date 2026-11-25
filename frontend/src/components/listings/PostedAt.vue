<template>
  <time v-if="iso" :datetime="iso" :title="exact + ' · ' + $t('Cyprus time')" class="inline-flex items-center gap-1">
    <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"/></svg>
    <span>{{ ago }}</span>
    <span v-if="full" style="opacity:.8">· {{ exact }}</span>
  </time>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { postedAt, timeAgo, exactTime } from '@/composables/useListingTime'

const props = defineProps<{ listing: { published_at?: string | null; created_at?: string | null }; full?: boolean }>()
const iso = computed(() => postedAt(props.listing))
const ago = computed(() => timeAgo(iso.value))
const exact = computed(() => exactTime(iso.value))
</script>
