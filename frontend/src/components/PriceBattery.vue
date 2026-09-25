<template>
  <div class="price-battery" :class="size">
    <div class="battery-container" :title="`Price Score: ${score}/100`">
      <!-- Battery body -->
      <div class="battery-body">
        <div class="battery-fill" :style="fillStyle"></div>
        <div class="battery-segments"></div>
        <span class="battery-label">{{ score }}%</span>
      </div>
      <!-- Battery tip -->
      <div class="battery-tip" :style="{background: fillColor}"></div>
    </div>
    <div v-if="showLabel" class="battery-text">
      <span class="battery-score-label" :style="{color: fillColor}">{{ label }}</span>
      <span v-if="showScores" class="battery-sub">
        Trust {{ trust }}% · Invest {{ invest }}%
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  score: number
  trust?: number
  invest?: number
  size?: 'sm' | 'md' | 'lg'
  showLabel?: boolean
  showScores?: boolean
}>(), {
  trust: 0,
  invest: 0,
  size: 'md',
  showLabel: true,
  showScores: false,
})

const fillColor = computed(() => {
  if (props.score >= 85) return '#6fffd2'
  if (props.score >= 60) return '#fbbf24'
  return '#ef4444'
})

const label = computed(() => {
  if (props.score >= 85) return 'Excellent'
  if (props.score >= 60) return 'Fair'
  return 'Overpriced'
})

const fillStyle = computed(() => ({
  width: `${Math.max(4, props.score)}%`,
  background: props.score >= 85
    ? 'linear-gradient(90deg, #6fffd2, #56d8ff)'
    : props.score >= 60
      ? 'linear-gradient(90deg, #fbbf24, #f97316)'
      : 'linear-gradient(90deg, #ef4444, #dc2626)',
}))
</script>

<style scoped>
.price-battery {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.battery-container {
  display: flex;
  align-items: center;
  gap: 2px;
}

.battery-body {
  position: relative;
  display: flex;
  align-items: center;
  border: 2px solid rgba(255,255,255,0.2);
  border-radius: 4px;
  overflow: hidden;
  background: var(--surface-overlay);
}

.price-battery.sm .battery-body { width: 40px; height: 18px; border-radius: 3px; }
.price-battery.md .battery-body { width: 60px; height: 24px; border-radius: 4px; }
.price-battery.lg .battery-body { width: 80px; height: 30px; border-radius: 5px; }

.battery-fill {
  height: 100%;
  transition: width 0.6s ease, background 0.3s ease;
  border-radius: inherit;
}

.battery-label {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 9px;
  font-weight: 800;
  color: rgba(255,255,255,0.9);
  letter-spacing: 0.02em;
  text-shadow: 0 1px 2px rgba(0,0,0,0.5);
  pointer-events: none;
}

.price-battery.lg .battery-label { font-size: 11px; }

.battery-tip {
  width: 3px;
  border-radius: 0 2px 2px 0;
  transition: background 0.3s;
}
.price-battery.sm .battery-tip { height: 8px; }
.price-battery.md .battery-tip { height: 12px; }
.price-battery.lg .battery-tip { height: 14px; }

.battery-text {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.battery-score-label {
  font-size: 11px;
  font-weight: 700;
  line-height: 1.2;
}

.battery-sub {
  font-size: 10px;
  color: var(--text-muted);
  line-height: 1.2;
}
</style>
