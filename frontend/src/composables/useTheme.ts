import { ref } from 'vue'

export type Theme = 'dark' | 'light'

// New key with the light redesign: earlier saved choices are reset once.
const STORAGE_KEY = 'aiprice_theme'
const theme = ref<Theme>('light')

function apply(t: Theme) {
  document.documentElement.setAttribute('data-theme', t)
  document.documentElement.style.colorScheme = t
}

export function initTheme() {
  const saved = (localStorage.getItem(STORAGE_KEY) as Theme | null)
  theme.value = saved === 'light' || saved === 'dark' ? saved : 'light'
  apply(theme.value)
}

export function useTheme() {
  function setTheme(t: Theme) {
    theme.value = t
    localStorage.setItem(STORAGE_KEY, t)
    apply(t)
  }
  function toggle() {
    setTheme(theme.value === 'dark' ? 'light' : 'dark')
  }
  return { theme, setTheme, toggle }
}
