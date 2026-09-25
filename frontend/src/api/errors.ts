/**
 * Turns an axios error into one line a user can act on: 429 gets the retry
 * wait, 422 gets the validation messages, anything else the server message.
 */
export function apiErrorMessage(e: any, fallback = 'Something went wrong. Please try again.'): string {
  const res = e?.response
  if (!res) return 'Network error — check your connection.'

  if (res.status === 429) {
    const wait = Number(res.headers?.['retry-after'])
    return wait > 0
      ? `Too many attempts. Please wait ${wait < 60 ? `${wait} seconds` : `${Math.ceil(wait / 60)} minutes`} and try again.`
      : 'Too many attempts. Please wait a moment and try again.'
  }

  const errors = res.data?.errors
  if (errors && typeof errors === 'object') {
    const first = Object.values(errors).flat()[0]
    if (first) return String(first)
  }

  return res.data?.message || fallback
}
