<template>
  <img
    src="@/assets/logo.png"
    alt="AIPrice.app"
    class="brand-logo-img"
    :style="{ height: size + 'px', width: 'auto', display: 'block' }"
  />
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  size?: number
  badge?: boolean
  dark?: boolean
}>(), {
  size: 34,
  badge: true,
  dark: false,
})
</script>
