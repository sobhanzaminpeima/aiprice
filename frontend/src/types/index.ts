export interface User {
  id: string
  name: string
  username: string | null
  email: string
  phone: string | null
  avatar: string | null
  role: string
  is_active: boolean
  is_verified: boolean
  kyc_verified: boolean
  force_password_change: boolean
  preferred_language: string
  preferred_currency: string
  email_verified_at: string | null
  created_at: string
  profile?: UserProfile
}

export interface UserProfile {
  id: string
  user_id: string
  business_name: string | null
  bio: string | null
  website: string | null
  city: string | null
  country: string | null
  avg_trust_score: number
  rating: number
  review_count: number
  total_listings: number
}

export interface Listing {
  id: string
  listing_number: string
  type: 'vehicle' | 'property' | 'rental'
  title: string
  slug: string
  description: string | null
  price: number
  currency: string
  price_negotiable: boolean
  country: string | null
  city: string | null
  district: string | null
  status: string
  is_featured: boolean
  is_premium: boolean
  is_verified: boolean
  view_count: number
  inquiry_count: number
  favorite_count: number
  published_at: string | null
  // AI fields
  ai_price_score: number | null
  ai_trust_score: number | null
  ai_investment_score: number | null
  ai_demand_score: number | null
  ai_deal_score: number | null
  ai_price_status: 'fair' | 'slightly_overpriced' | 'overpriced' | null
  price_battery_percent: number | null
  market_min_price: number | null
  market_max_price: number | null
  market_avg_price: number | null
  ai_confidence_score: number | null
  ai_score_breakdown: { price?: string[]; trust?: string[]; demand?: string[]; investment?: string[] } | null
  ai_analysis: string | null
  // Media
  images: string[] | null
  thumbnail: string | null
  video_url: string | null
  // Relations
  user?: User
  listable?: Vehicle | Property | Rental
  created_at: string
}

export interface Vehicle {
  id: string
  brand_id: string
  model_id: string
  variant: string | null
  year: number
  fuel_type: string | null
  transmission: string | null
  body_type: string | null
  color: string | null
  mileage: number | null
  engine_size?: string | number | null
  features?: string[] | null
  condition: string
  brand?: VehicleBrand
  model?: VehicleModel
}

export interface VehicleBrand {
  id: string
  name: string
  slug: string
  logo: string | null
  country: string | null
  models?: VehicleModel[]
}

export interface VehicleModel {
  id: string
  brand_id: string
  name: string
  slug: string
  body_type: string | null
}

export interface Property {
  id: string
  property_type_id: string
  title: string
  bedrooms: number | null
  bathrooms: number | null
  area_sqm: number | null
  area_sqft?: number | null
  condition?: string | null
  floor_number?: number | null
  build_year?: number | null
  furnishing: string | null
  has_parking: boolean
  has_pool: boolean
  has_garden: boolean
  has_elevator: boolean
  property_type?: PropertyType
}

export interface PropertyType {
  id: string
  name: string
  slug: string
  icon: string | null
}

export interface Rental {
  id: string
  title: string
  rental_type: string
  bedrooms: number | null
  bathrooms: number | null
  area_sqm: number | null
}

export interface SubscriptionPlan {
  id: string
  name: string
  slug: string
  description: string | null
  type: string
  price_monthly: number
  price_yearly: number
  currency: string
  max_listings: number
  ai_price_analysis: boolean
  ai_trust_score: boolean
  ai_investment_score: boolean
  featured_listing: boolean
  priority_support: boolean
}

export interface PaginatedResponse<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number
  to: number
}

export interface MarketStats {
  total_listings: number
  vehicle_listings: number
  property_listings: number
  rental_listings: number
}
