import axios from 'axios'

const client = axios.create({
  baseURL: '/api/v1',
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

client.interceptors.request.use((config) => {
  const token = localStorage.getItem('hp_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

client.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err.response?.status === 403 && err.response?.data?.code === 'password_change_required') {
      if (window.location.pathname !== '/change-password') window.location.href = '/change-password'
    }
    if (err.response?.status === 401) {
      localStorage.removeItem('hp_token')
      localStorage.removeItem('hp_user')
      window.location.href = '/login'
    }
    return Promise.reject(err)
  }
)

export default client
