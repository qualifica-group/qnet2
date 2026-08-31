import { clsx, type ClassValue } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

/**
 * Order-independent, duplicate-safe comparison of two id collections. Shared
 * by every sparse-PATCH builder whose payload key is a full-replace SET (the
 * opportunity's `products_of_interest`/`rewards`, the offer's `rewards`): the
 * key must travel only when the SET actually changed, and the row order those
 * chips render in carries no meaning.
 */
export function sameIdSet(a: number[], b: number[]): boolean {
  if (a.length !== b.length) {
    return false
  }
  const setB = new Set(b)
  return a.every((id) => setB.has(id))
}
