import type { Listing } from '@/types'

/**
 * How close another listing is to this one (40–99), from what both listings
 * state: year, mileage, size, bedrooms and price. Shown as "AI match" on the
 * similar-listing cards.
 */
export function similarity(base: Listing, other: Listing): number | null {
  const a = (base.listable ?? {}) as Record<string, any>
  const b = (other.listable ?? {}) as Record<string, any>
  let score = 100
  let used = 0
  const num = (v: unknown) => (v === null || v === undefined || v === '' ? null : Number(v))
  if (num(a.year) !== null && num(b.year) !== null) { score -= Math.abs(num(a.year)! - num(b.year)!) * 4; used++ }
  if (num(a.mileage) !== null && num(b.mileage) !== null) { score -= (Math.abs(num(a.mileage)! - num(b.mileage)!) / 10000) * 2; used++ }
  if (num(a.bedrooms) !== null && num(b.bedrooms) !== null) { score -= Math.abs(num(a.bedrooms)! - num(b.bedrooms)!) * 10; used++ }
  if (num(a.area_sqm) && num(b.area_sqm)) { score -= (Math.abs(num(a.area_sqm)! - num(b.area_sqm)!) / num(a.area_sqm)!) * 40; used++ }
  if (Number(base.price) > 0 && Number(other.price) > 0) { score -= (Math.abs(Number(base.price) - Number(other.price)) / Number(base.price)) * 50; used++ }
  if (a.model_id && b.model_id) { if (a.model_id !== b.model_id) score -= 15; used++ }
  return used ? Math.round(Math.max(40, Math.min(99, score))) : null
}
