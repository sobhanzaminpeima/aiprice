<template>
  <component :is="iconComponent" :size="size" :stroke-width="strokeWidth" aria-hidden="true" />
</template>

<script setup lang="ts">
import { computed } from 'vue'
import {
  AlertTriangle, BarChart3, Bath, BedDouble, Bell, Building2, CalendarDays,
  CarFront, Check, CircleHelp, ClipboardList, Cpu, CreditCard, Eye, FileText,
  Fuel, Gauge, Globe2, Heart, Home, KeyRound, List, Mail, Map, MapPin,
  MessageCircle, Palette, Paperclip, Phone, Plane, Plus, Ruler, Search, Send, Settings,
  ShieldCheck, SlidersHorizontal, Sparkles, Star, Trash2, TrendingUp, User,
  Users, Wrench, X,
} from 'lucide-vue-next'

const props = withDefaults(defineProps<{ name: string; size?: number; strokeWidth?: number }>(), {
  size: 20,
  strokeWidth: 1.8,
})

const icons: Record<string, typeof Home> = {
  alert: AlertTriangle, bath: Bath, bed: BedDouble, bell: Bell, building: Building2,
  calendar: CalendarDays, car: CarFront, chart: BarChart3, check: Check,
  clipboard: ClipboardList, cpu: Cpu, creditCard: CreditCard, eye: Eye, file: FileText,
  fuel: Fuel, gauge: Gauge, globe: Globe2, heart: Heart, help: CircleHelp, home: Home,
  key: KeyRound, list: List, mail: Mail, map: Map, mapPin: MapPin, message: MessageCircle,
  palette: Palette, paperclip: Paperclip, phone: Phone, plane: Plane, plus: Plus, ruler: Ruler, send: Send,
  search: Search, settings: Settings, shield: ShieldCheck, sliders: SlidersHorizontal,
  sparkles: Sparkles, star: Star, trash: Trash2, trending: TrendingUp, user: User,
  users: Users, wrench: Wrench, x: X,
}

const iconComponent = computed(() => icons[props.name] ?? CircleHelp)
</script>
