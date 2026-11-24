export interface ListingDraft {
  type?: 'vehicle'|'property'|'rental'|null
  title?: string|null; description?: string|null
  seo_title?: string|null; seo_description?: string|null
  price?: number|null; currency?: string|null; city?: string|null; district?: string|null
  brand?: string|null; model?: string|null; brand_match?: string|null; model_match?: string|null
  brand_id?: string|null; model_id?: string|null; property_type_id?: string|null
  property_type?: string|null; property_type_match?: string|null
  year?: number|null; mileage?: number|null; fuel_type?: string|null; transmission?: string|null
  body_type?: string|null; color?: string|null; condition?: string|null; engine_size?: string|null
  bedrooms?: number|null; bathrooms?: number|null; area_sqm?: number|null; floor_number?: number|null; build_year?: number|null
  furnishing?: string|null; has_parking?: boolean|null; has_garden?: boolean|null; has_pool?: boolean|null; has_elevator?: boolean|null
  rental_type?: string|null; missing_required?: string[]; review_required?: boolean
}
export function applyDraftFields(target:Record<string,unknown>,draft:ListingDraft) {
  for(const [key,value] of Object.entries(draft)) {
    if(value!==null&&value!==undefined&&key!=='missing_required'&&key!=='review_required')target[key]=value
  }
}
export function listingMultipart(payload:Record<string,unknown>,photos:File[],update=false):FormData {
  const data=new FormData()
  for(const [key,value] of Object.entries(payload)) {
    if(value===null||value===undefined||typeof value==='object'||value==='')continue
    data.append(key,typeof value==='boolean'?(value?'1':'0'):String(value))
  }
  if(update)data.append('_method','PUT')
  photos.forEach(photo=>data.append('images[]',photo))
  return data
}
