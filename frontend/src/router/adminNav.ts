/**
 * Admin sidebar and route permissions in one place. The router reads the
 * permission for each /admin/<path> from here, and AdminLayout renders only
 * the items the signed-in user may open. The backend enforces the same rules
 * independently in EnsureAdminPermission — this file only decides what to show.
 */
export interface AdminNavItem {
  path: string
  label: string
  permission: string
}

export interface AdminNavSection {
  title: string
  /** lucide icon name used by AdminLayout */
  icon: string
  items: AdminNavItem[]
}

/** Grouped by the job the admin is doing, most-used first. */
export const adminNav: AdminNavSection[] = [
  {
    title: 'Dashboard',
    icon: 'layout',
    items: [
      { path: 'overview', label: 'Overview', permission: 'view_dashboard' },
      { path: 'inbox', label: 'Requests inbox', permission: 'manage_support' },
      { path: 'live-analytics', label: 'Live analytics', permission: 'view_analytics' },
    ],
  },
  {
    title: 'Listings & catalog',
    icon: 'list',
    items: [
      { path: 'listings', label: 'All listings', permission: 'manage_listings' },
      { path: 'vehicles', label: 'Brands, models & property types', permission: 'manage_vehicles' },
    ],
  },
  {
    title: 'Users & sellers',
    icon: 'users',
    items: [
      { path: 'users', label: 'Users', permission: 'manage_users' },
      { path: 'agencies', label: 'Agencies & dealers', permission: 'manage_users' },
      { path: 'kyc', label: 'Identity checks (KYC)', permission: 'approve_kyc' },
    ],
  },
  {
    title: 'Messages & support',
    icon: 'message',
    items: [
      { path: 'inquiries', label: 'Buyer messages', permission: 'manage_support' },
      { path: 'support', label: 'Support tickets', permission: 'manage_support' },
      { path: 'notifications', label: 'Announcements', permission: 'manage_cms' },
    ],
  },
  {
    title: 'Pricing & AI',
    icon: 'sparkles',
    items: [
      { path: 'ai-status', label: 'AI price check status', permission: 'manage_ai' },
      { path: 'ai-insights', label: 'AI insights', permission: 'manage_ai' },
      { path: 'market-analytics', label: 'Market analytics', permission: 'view_analytics' },
      { path: 'competitors', label: 'Market data sources', permission: 'manage_competitors' },
    ],
  },
  {
    title: 'Money',
    icon: 'wallet',
    items: [
      { path: 'financial', label: 'Payments', permission: 'manage_payments' },
      { path: 'subscriptions', label: 'Subscription plans', permission: 'manage_subscriptions' },
      { path: 'advertisements', label: 'Advertisements', permission: 'manage_advertisements' },
    ],
  },
  {
    title: 'Safety & content',
    icon: 'shield',
    items: [
      { path: 'fraud', label: 'Fraud reports', permission: 'manage_fraud' },
      { path: 'reports', label: 'Reports', permission: 'view_analytics' },
      { path: 'content', label: 'Blog & FAQ', permission: 'manage_cms' },
      { path: 'social', label: 'Instagram auto-post', permission: 'manage_cms' },
    ],
  },
]

/** Pages reachable from inside another page rather than from the sidebar. */
const nestedPermissions: Record<string, string> = {
  'listings/:id/edit': 'manage_listings',
}

export const adminPermissionFor: Record<string, string> = Object.fromEntries([
  ...adminNav.flatMap((s) => s.items.map((i) => [i.path, i.permission])),
  ...Object.entries(nestedPermissions),
])

export const roleLabels: Record<string, string> = {
  super_admin: 'Super Admin',
  admin: 'Admin',
  moderator: 'Moderator',
  support: 'Support',
  finance_manager: 'Finance Manager',
  ai_analyst: 'AI Analyst',
}
