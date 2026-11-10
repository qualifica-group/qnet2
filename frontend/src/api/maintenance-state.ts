import axios from 'axios'
import { useSyncExternalStore } from 'react'

const HTTP_SERVICE_UNAVAILABLE = 503

let isActive = false
const listeners = new Set<() => void>()

function setActive(next: boolean): void {
  if (isActive !== next) {
    isActive = next
    listeners.forEach((listener) => listener())
  }
}

export function reportMaintenance(): void {
  setActive(true)
}

export function clearMaintenance(): void {
  setActive(false)
}

function subscribe(listener: () => void): () => void {
  listeners.add(listener)
  return () => listeners.delete(listener)
}

/**
 * A store rather than a DOM event like the 401 one: the first request starts in
 * main.tsx, before any React listener exists.
 */
export function useMaintenanceActive(): boolean {
  return useSyncExternalStore(subscribe, () => isActive)
}

/** The marker tells maintenance apart from other 503s, such as a query timeout (spec 0376). */
export function isMaintenanceError(error: unknown): boolean {
  return (
    axios.isAxiosError<{ maintenance?: boolean }>(error) &&
    error.response?.status === HTTP_SERVICE_UNAVAILABLE &&
    error.response.data?.maintenance === true
  )
}
