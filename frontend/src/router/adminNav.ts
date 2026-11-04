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
  items: AdminNavItem[]
}

export const adminNav: AdminNavSection[] = [
  {
    title: 'Main',
    items: [
      { path: 'overview', label: 'Overview', permission: 'view_dashboard' },
      { path: 'live-analytics', label: 'Live Analytics', permission: 'view_analytics' },
      { path: 'ai-insights', label: 'AI Insights', permission: 'manage_ai' },
    ],
  },
  {
    title: 'Marketplace',
    items: [
      { path: 'listings', label: 'All Listings', permission: 'manage_listings' },
      { path: 'vehicles', label: 'Vehicle & Property', permission: 'manage_vehicles' },
      { path: 'users', label: 'Users', permission: 'manage_users' },
      { path: 'kyc', label: 'KYC Review', permission: 'approve_kyc' },
      { path: 'inquiries', label: 'Buyer Inquiries', permission: 'manage_support' },
      { path: 'agencies', label: 'Agencies & Dealers', permission: 'manage_users' },
    ],
  },
  {
    title: 'Finance',
    items: [
      { path: 'financial', label: 'Financial', permission: 'manage_payments' },
      { path: 'market-analytics', label: 'Market Analytics', permission: 'view_analytics' },
      { path: 'subscriptions', label: 'Subscriptions', permission: 'manage_subscriptions' },
      { path: 'advertisements', label: 'Advertisements', permission: 'manage_advertisements' },
    ],
  },
  {
    title: 'Safety & Content',
    items: [
      { path: 'fraud', label: 'Fraud Detection', permission: 'manage_fraud' },
      { path: 'support', label: 'Support Tickets', permission: 'manage_support' },
      { path: 'reports', label: 'Reports', permission: 'view_analytics' },
      { path: 'content', label: 'Content', permission: 'manage_cms' },
      { path: 'notifications', label: 'Announcements', permission: 'manage_cms' },
      { path: 'competitors', label: 'Competitors', permission: 'manage_competitors' },
    ],
  },
  {
    title: 'AI',
    items: [
      { path: 'ai-status', label: 'AI Status', permission: 'manage_ai' },
    ],
  },
  {
    title: 'Social',
    items: [{ path: 'social', label: 'Instagram Auto-Post', permission: 'manage_cms' }],
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
