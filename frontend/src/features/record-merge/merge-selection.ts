import type {
  MergeChoice,
  MergeCollection,
  MergeCollectionItem,
  MergeCollectionKey,
  MergeField,
  MergeFieldScalar,
  MergeLinked,
  MergePayload,
  MergePreview,
} from '@/features/record-merge/types'

/** Operator choices layered over the defaults; reset whenever the survivor changes. */
export type FieldOverrides = Readonly<Record<string, MergeChoice>>

/** Ids of the items the operator unticked, per collection. */
export type DiscardedItems = Readonly<Partial<Record<MergeCollectionKey, readonly number[]>>>

export const COMBINE_CHOICE = 'combine'

export function isEmptyValue(value: MergeFieldScalar | undefined): boolean {
  return value === null || value === undefined || (typeof value === 'string' && value.trim() === '')
}

function valueOf(field: MergeField, recordId: number): MergeFieldScalar {
  return field.values.find((entry) => entry.record_id === recordId)?.value ?? null
}

/** Spec D-4: survivor value if not empty, else the first non-empty among the others in id order. */
export function defaultChoice(field: MergeField, survivorId: number): number {
  if (!isEmptyValue(valueOf(field, survivorId))) {
    return survivorId
  }
  const others = field.values
    .filter((entry) => entry.record_id !== survivorId && !isEmptyValue(entry.value))
    .sort((a, b) => a.record_id - b.record_id)
  return others[0]?.record_id ?? survivorId
}

export function effectiveChoice(field: MergeField, survivorId: number, overrides: FieldOverrides): MergeChoice {
  return overrides[field.key] ?? defaultChoice(field, survivorId)
}

function normalized(value: MergeFieldScalar): MergeFieldScalar {
  return typeof value === 'string' ? (value.trim() === '' ? null : value.trim()) : value
}

/** True when at least two records hold different values for the field. */
export function fieldDiffers(field: MergeField): boolean {
  return new Set(field.values.map((entry) => normalized(entry.value))).size > 1
}

/** Items of the duplicates only: the survivor's own items always stay. */
export function duplicateItems(collection: MergeCollection, survivorId: number): MergeCollectionItem[] {
  return collection.items.filter((item) => item.record_id !== survivorId)
}

/** Already on the CHOSEN survivor (same non-null match_key): not copied, so not selectable (spec 0394 D-6). */
export function isAlreadyPresent(collection: MergeCollection, item: MergeCollectionItem, survivorId: number): boolean {
  return (
    item.match_key !== null &&
    collection.items.some((other) => other.record_id === survivorId && other.match_key === item.match_key)
  )
}

/** The links that move to the survivor: the duplicates' counts and samples only, empty keys dropped. */
export function movingLinks(linked: readonly MergeLinked[], survivorId: number): MergeLinked[] {
  return linked
    .map((entry) => {
      const counts = entry.counts.filter((row) => row.record_id !== survivorId)
      return {
        ...entry,
        counts,
        count: counts.reduce((total, row) => total + row.count, 0),
        items: entry.items.filter((item) => item.record_id !== survivorId),
      }
    })
    .filter((entry) => entry.count > 0)
}

export function isKept(discarded: DiscardedItems, key: MergeCollectionKey, itemId: number): boolean {
  return !(discarded[key] ?? []).includes(itemId)
}

export function toggleDiscarded(discarded: DiscardedItems, key: MergeCollectionKey, itemId: number): DiscardedItems {
  const current = discarded[key] ?? []
  const next = current.includes(itemId) ? current.filter((id) => id !== itemId) : [...current, itemId]
  return { ...discarded, [key]: next }
}

/** Builds the contract payload: only deviations from the defaults travel (omitted field = survivor value). */
export function buildMergePayload(
  preview: MergePreview,
  survivorId: number,
  overrides: FieldOverrides,
  discarded: DiscardedItems,
): MergePayload {
  const mergedIds = preview.records.map((record) => record.id).filter((id) => id !== survivorId)

  const fields: Record<string, MergeChoice> = {}
  for (const field of preview.fields) {
    const choice = effectiveChoice(field, survivorId, overrides)
    if (choice !== survivorId) {
      fields[field.key] = choice
    }
  }

  const discard: Partial<Record<MergeCollectionKey, number[]>> = {}
  for (const collection of preview.collections) {
    const ids = duplicateItems(collection, survivorId)
      .filter((item) => !isAlreadyPresent(collection, item, survivorId) && !isKept(discarded, collection.key, item.id))
      .map((item) => item.id)
    if (ids.length > 0) {
      discard[collection.key] = ids
    }
  }

  return { survivor_id: survivorId, merged_ids: mergedIds, fields, discard }
}
