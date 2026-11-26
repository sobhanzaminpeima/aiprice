/** Filter keys each listing page keeps in its URL, and how they map to the /listings API. */
export const ADVANCED_KEYS = {
  vehicle: ['min_price', 'max_price', 'brand', 'model', 'body', 'min_year', 'max_year', 'max_mileage', 'ai', 'posted', 'photos'],
  property: ['min_price', 'max_price', 'beds', 'baths', 'min_area', 'max_area', 'furnishing', 'ai', 'posted', 'photos', 'pool', 'garden', 'parking'],
  rental: ['min_price', 'max_price', 'beds', 'baths', 'min_area', 'max_area', 'furnishing', 'ai', 'posted', 'photos'],
} as const

const API_NAME: Record<string, string> = {
  beds: 'min_bedrooms', baths: 'min_bathrooms', posted: 'posted_within', photos: 'with_photos',
  pool: 'has_pool', garden: 'has_garden', parking: 'has_parking',
}

export function advancedParams(f: Record<string, string>, kind: keyof typeof ADVANCED_KEYS): Record<string, string> {
  const out: Record<string, string> = {}
  for (const k of ADVANCED_KEYS[kind]) if (f[k]) out[API_NAME[k] ?? k] = f[k]
  return out
}

/** Sort options shared by the listing pages; values are what the API accepts. */
export const SORTS = [
  { value: 'newest', label: 'Newest First' },
  { value: 'price_asc', label: 'Price: Low → High' },
  { value: 'price_desc', label: 'Price: High → Low' },
  { value: 'most_viewed', label: 'Most viewed' },
  { value: 'battery', label: 'Best price score' },
  { value: 'trust_score', label: 'AI Trust Score' },
] as const
