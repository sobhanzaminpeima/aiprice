import { createRouter, createWebHistory } from "vue-router"
import { useAuthStore } from "@/stores/auth"
import { adminNav, adminPermissionFor } from "@/router/adminNav"

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior: () => ({ top: 0 }),
  routes: [
    { path: "/", name: "home", component: () => import("@/views/HomeView.vue") },
    { path: "/cars", name: "cars", component: () => import("@/views/marketplace/CarsView.vue") },
    { path: "/properties", name: "properties", component: () => import("@/views/marketplace/PropertiesView.vue") },
    { path: "/rentals", name: "rentals", component: () => import("@/views/marketplace/RentalsView.vue") },
    { path: "/listings/:slug", name: "listing-detail", component: () => import("@/views/marketplace/ListingDetailView.vue") },
    { path: "/search", name: "search", component: () => import("@/views/marketplace/SearchView.vue") },
    { path: "/pricing", name: "pricing", component: () => import("@/views/PricingView.vue") },
    { path: "/about", name: "about", component: () => import("@/views/AboutView.vue") },
    { path: "/contact", name: "contact", component: () => import("@/views/ContactView.vue") },
    { path: "/blog", name: "blog", component: () => import("@/views/BlogView.vue") },
    { path: "/blog/:slug", name: "blog-post", component: () => import("@/views/BlogPostView.vue") },
    { path: "/faq", name: "faq", component: () => import("@/views/FaqView.vue") },
    { path: "/market-insights", name: "market-insights", component: () => import("@/views/MarketInsightsView.vue") },
    { path: "/investment-center", name: "investment", component: () => import("@/views/InvestmentCenterView.vue") },
    { path: "/login", name: "login", component: () => import("@/views/auth/LoginView.vue"), meta: { guest: true } },
    { path: "/register", name: "register", component: () => import("@/views/auth/RegisterView.vue"), meta: { guest: true } },
    { path: "/forgot-password", name: "forgot-password", component: () => import("@/views/auth/ForgotPasswordView.vue"), meta: { guest: true } },
    { path: "/billing/success", name: "billing-success", component: () => import("@/views/BillingSuccessView.vue"), meta: { requiresAuth: true } },
    { path: "/verify-email", name: "verify-email", component: () => import("@/views/auth/VerifyEmailView.vue") },
    { path: "/change-password", name: "change-password", component: () => import("@/views/auth/ChangePasswordView.vue"), meta: { requiresAuth: true } },
    { path: "/reset-password", name: "reset-password", component: () => import("@/views/auth/ResetPasswordView.vue"), meta: { guest: true } },
    {
      path: "/dashboard",
      component: () => import("@/layouts/DashboardLayout.vue"),
      meta: { requiresAuth: true },
      children: [
        { path: "", redirect: "/dashboard/overview" },
        { path: "overview", name: "dashboard-overview", component: () => import("@/views/dashboard/OverviewView.vue") },
        { path: "listings", name: "dashboard-listings", component: () => import("@/views/dashboard/MyListingsView.vue") },
        { path: "listings/new", name: "create-listing", component: () => import("@/views/dashboard/CreateListingView.vue") },
        { path: "listings/:id/edit", name: "edit-listing", component: () => import("@/views/dashboard/EditListingView.vue") },
        { path: "messages", name: "dashboard-messages", component: () => import("@/views/dashboard/MessagesView.vue") },
        { path: "analytics", name: "dashboard-analytics", component: () => import("@/views/dashboard/AnalyticsView.vue") },
        { path: "profile", name: "dashboard-profile", component: () => import("@/views/dashboard/ProfileView.vue") },
        { path: "kyc", name: "dashboard-kyc", component: () => import("@/views/dashboard/KycView.vue") },
        { path: "support", name: "dashboard-support", component: () => import("@/views/dashboard/SupportView.vue") },
        { path: "invoices/:id", name: "dashboard-invoice", component: () => import("@/views/dashboard/InvoiceView.vue") },
        { path: "subscription", name: "dashboard-subscription", component: () => import("@/views/dashboard/SubscriptionView.vue") },
        { path: "favorites", name: "dashboard-favorites", component: () => import("@/views/dashboard/FavoritesView.vue") },
      ],
    },
    {
      path: "/admin",
      component: () => import("@/layouts/AdminLayout.vue"),
      meta: { requiresAuth: true, requiresStaff: true },
      children: [
        { path: "", redirect: "/admin/overview" },
        { path: "kyc",              name: "admin-kyc",           component: () => import("@/views/admin/AdminKycView.vue") },
        { path: "overview",         name: "admin-overview",      component: () => import("@/views/admin/AdminOverviewView.vue") },
        { path: "live-analytics",   name: "admin-live",          component: () => import("@/views/admin/AdminLiveAnalyticsView.vue") },
        { path: "ai-insights",      name: "admin-ai-insights",   component: () => import("@/views/admin/AdminAiInsightsView.vue") },
        { path: "users",            name: "admin-users",         component: () => import("@/views/admin/AdminUsersView.vue") },
        { path: "inquiries",        name: "admin-inquiries",     component: () => import("@/views/admin/AdminInquiriesView.vue") },
        { path: "listings",         name: "admin-listings",      component: () => import("@/views/admin/AdminListingsView.vue") },
        { path: "listings/:id/edit", name: "admin-listing-edit", component: () => import("@/views/admin/AdminListingEditView.vue") },
        { path: "vehicles",         name: "admin-vehicles",      component: () => import("@/views/admin/AdminVehiclesView.vue") },
        { path: "financial",        name: "admin-financial",     component: () => import("@/views/admin/AdminFinancialView.vue") },
        { path: "market-analytics", name: "admin-market",        component: () => import("@/views/admin/AdminMarketAnalyticsView.vue") },
        { path: "fraud",            name: "admin-fraud",         component: () => import("@/views/admin/AdminFraudView.vue") },
        { path: "reports",          name: "admin-reports",       component: () => import("@/views/admin/AdminReportsView.vue") },
        { path: "content",          name: "admin-content",       component: () => import("@/views/admin/AdminContentView.vue") },
        { path: "notifications",    name: "admin-notifications", component: () => import("@/views/admin/AdminNotificationsView.vue") },
        { path: "competitors",      name: "admin-competitors",   component: () => import("@/views/admin/AdminCompetitorsView.vue") },
        { path: "subscriptions",    name: "admin-subscriptions", component: () => import("@/views/admin/AdminSubscriptionsView.vue") },
        { path: "agencies",         name: "admin-agencies",      component: () => import("@/views/admin/AdminAgenciesView.vue") },
        { path: "settings",         name: "admin-settings",      component: () => import("@/views/admin/AdminSettingsView.vue") },
        { path: "advertisements",   name: "admin-advertisements",component: () => import("@/views/admin/AdminAdvertisementsView.vue") },
        { path: "ai-intelligence",  name: "admin-ai",            component: () => import("@/views/admin/AdminAiView.vue") },
        { path: "llm-management",   name: "admin-llm",           component: () => import("@/views/admin/AdminLlmManagementView.vue") },
        { path: "social",           name: "admin-social",        component: () => import("@/views/admin/AdminSocialView.vue") },
        { path: "support",          name: "admin-support",       component: () => import("@/views/admin/AdminSupportView.vue") },
        { path: "analytics",        name: "admin-analytics",     component: () => import("@/views/admin/AdminAnalyticsView.vue") },
      ],
    },
    { path: "/:pathMatch(.*)*", name: "not-found", component: () => import("@/views/NotFoundView.vue") },
  ],
})

router.beforeEach((to, _from, next) => {
  const auth = useAuthStore()
  if (to.meta.requiresAuth && !auth.isAuthenticated) return next({ name: "login", query: { redirect: to.fullPath } })
  if (auth.isAuthenticated && auth.user?.force_password_change && to.name !== "change-password") {
    return next({ name: "change-password" })
  }
  if (to.meta.requiresStaff && !auth.isStaff) return next({ name: "dashboard-overview" })

  const leaf = to.matched[to.matched.length - 1]?.path ?? ""
  const permission = leaf.startsWith("/admin/") ? adminPermissionFor[leaf.slice("/admin/".length)] : undefined
  if (permission && !auth.can(permission)) {
    // Send staff to the first admin page they may open instead of a dead end.
    const fallback = adminNav.flatMap((s) => s.items).find((i) => auth.can(i.permission))
    return next(fallback && `/admin/${fallback.path}` !== to.path ? `/admin/${fallback.path}` : { name: "dashboard-overview" })
  }

  if (to.meta.guest && auth.isAuthenticated) return next(auth.homeRoute)
  next()
})

export default router