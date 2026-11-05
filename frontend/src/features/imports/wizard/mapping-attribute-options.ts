import { normalizeText } from '@/features/imports/wizard/normalize-text'
import type { EffectiveAttribute } from '@/features/product-categories/types'
import type { DetectedColumn } from '@/features/imports/wizard/types'
import { IGNORE_TARGET } from '@/features/imports/wizard/types'

/** Prefix of a column-mapping target feeding an offer attribute (spec 0348). */
export const ATTRIBUTE_TARGET_PREFIX = 'attribute.'

/** Stable empty fallback so memo dependencies keep their identity. */
export const NO_ATTRIBUTE_OPTIONS: AttributeOption[] = []

export interface AttributeOption {
  /** `attribute.<code>`, the value stored in `column_mapping`. */
  target: string
  name: string
}

export function toAttributeOptions(attributes: EffectiveAttribute[]): AttributeOption[] {
  return attributes.map((attribute) => ({ target: `${ATTRIBUTE_TARGET_PREFIX}${attribute.code}`, name: attribute.name }))
}

/**
 * Mapping edits implied by a freshly loaded attribute catalogue, keyed by
 * column index: `attribute.*` targets no longer applicable go back to ignore,
 * and a column that is not mapped yet and whose header equals an attribute
 * name (ignoring case, accents and punctuation) is suggested onto it.
 */
export function attributeMappingUpdates(
  columns: DetectedColumn[],
  current: Record<string, string>,
  options: AttributeOption[],
): Record<string, string> {
  const validTargets = new Set(options.map((option) => option.target))
  const byName = new Map(options.map((option) => [normalizeText(option.name), option.target]))
  const taken = new Set(Object.values(current))
  const updates: Record<string, string> = {}

  for (const column of columns) {
    const key = String(column.index)
    const target = current[key] ?? IGNORE_TARGET
    if (target.startsWith(ATTRIBUTE_TARGET_PREFIX) && !validTargets.has(target)) {
      updates[key] = IGNORE_TARGET
      continue
    }
    if (target !== IGNORE_TARGET) continue
    const suggested = byName.get(normalizeText(column.name))
    if (suggested && !taken.has(suggested)) {
      updates[key] = suggested
      taken.add(suggested)
    }
  }
  return updates
}
