import { describe, expect, it } from 'vitest'
import { Circle, CreditCard, Files, LayoutTemplate, Shapes } from 'lucide-react'
import { resolveIcon } from '@/features/navigation/icon-map'

/**
 * Spec 0068 AC-115. `config/navigation.php` declares `'icon' => 'credit-card'`
 * for the `payment-methods` node; a missing entry here silently degrades to
 * the neutral `Circle` fallback (no crash, no lint/type error — the failure
 * mode `resolveIcon()` exists to swallow for genuinely unknown names). A
 * targeted assertion, not a hardcoded full-key-list one: the frontend cannot
 * read the backend's PHP config, so a "covers every expected icon" test
 * would just be a second, driftable source of truth.
 */
describe('resolveIcon — payment-methods navigation icon (AC-115)', () => {
  it('resolves "credit-card" to the CreditCard component, not the Circle fallback', () => {
    expect(resolveIcon('credit-card')).toBe(CreditCard)
    expect(resolveIcon('credit-card')).not.toBe(Circle)
  })
})

/**
 * Stesso failure mode per il nodo `document-layouts` (spec 0069), che in
 * `config/navigation.php` dichiara `'icon' => 'files'`: la voce mancava nella
 * mappa e il menu mostrava il fallback neutro invece dell'icona del modulo.
 */
describe('resolveIcon — document-layouts navigation icon', () => {
  it('resolves "files" to the Files component, not the Circle fallback', () => {
    expect(resolveIcon('files')).toBe(Files)
    expect(resolveIcon('files')).not.toBe(Circle)
  })
})

/**
 * Spec 0143 rev 2, AC-018. `config/navigation/products.php` declares
 * `'icon' => 'shapes'` (product-typologies) and `config/navigation/tasks.php`
 * declares `'icon' => 'layout-template'` (task-templates): both were missing
 * from the map and fell through to Circle, in the sidebar too.
 */
describe('resolveIcon — product-typologies and task-templates navigation icons (AC-018)', () => {
  it('resolves "shapes" to the Shapes component, not the Circle fallback', () => {
    expect(resolveIcon('shapes')).toBe(Shapes)
    expect(resolveIcon('shapes')).not.toBe(Circle)
  })

  it('resolves "layout-template" to the LayoutTemplate component, not the Circle fallback', () => {
    expect(resolveIcon('layout-template')).toBe(LayoutTemplate)
    expect(resolveIcon('layout-template')).not.toBe(Circle)
  })
})
