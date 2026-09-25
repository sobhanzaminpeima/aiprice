import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import client from '@/api/client'
import type { User } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(JSON.parse(localStorage.getItem('hp_user') || 'null'))
  const token = ref<string | null>(localStorage.getItem('hp_token'))

  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const isAdmin = computed(() => !!user.value && ['admin', 'super_admin'].includes(user.value.role))
  // Any staff role (moderator, support, …) may enter /admin; which pages they
  // see comes from the permission list the API returns with the user.
  const isStaff = computed(() => !!user.value && (!!(user.value as any).is_staff || isAdmin.value))
  const permissions = computed<string[]>(() => (user.value as any)?.permissions ?? [])
  const can = (permission: string) => user.value?.role === 'super_admin' || permissions.value.includes(permission)
  const homeRoute = computed(() => (isStaff.value ? '/admin' : '/dashboard'))
  const isSeller = computed(() => user.value && ['seller', 'dealer', 'agency'].includes(user.value.role))

  async function login(email: string, password: string) {
    const res = await client.post('/auth/login', { email, password })
    setSession(res.data.token, res.data.user)
    return res.data
  }

  async function register(data: { name: string; email: string; password: string; password_confirmation: string; role?: string }) {
    const res = await client.post('/auth/register', data)
    setSession(res.data.token, res.data.user)
    return res.data
  }

  async function logout() {
    try { await client.post('/auth/logout') } catch {}
    clearSession()
  }

  async function fetchMe() {
    const res = await client.get('/auth/me')
    user.value = res.data
    localStorage.setItem('hp_user', JSON.stringify(res.data))
  }

  function setSession(newToken: string, newUser: User) {
    token.value = newToken
    user.value = newUser
    localStorage.setItem('hp_token', newToken)
    localStorage.setItem('hp_user', JSON.stringify(newUser))
  }

  function clearSession() {
    token.value = null
    user.value = null
    localStorage.removeItem('hp_token')
    localStorage.removeItem('hp_user')
  }

  return { user, token, isAuthenticated, isAdmin, isStaff, permissions, can, homeRoute, isSeller, login, register, logout, fetchMe, setSession, clearSession }
})
