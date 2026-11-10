import { apiClient } from '@/api/client'

/** Resolves when the API accepts the caller; rejects with a 503 during maintenance. */
export async function fetchAvailability(): Promise<true> {
  await apiClient.get('/availability')
  return true
}
