/** Modules that can merge duplicates; the value is the API base path (spec 0394). */
export type MergeModule = 'registries' | 'referents'

/** Selection bounds of the bulk action (spec 0394 D-1). */
export const MERGE_MIN_RECORDS = 2
export const MERGE_MAX_RECORDS = 5

export type MergeFieldType = 'text' | 'number' | 'boolean' | 'date' | 'enum' | 'relation'
export type MergeFieldScalar = string | number | boolean | null

export interface MergeRecord {
  id: number
  name: string
  old_id: number | null
  created_at: string
}

export interface MergeFieldValue {
  record_id: number
  value: MergeFieldScalar
  display: string | null
}

export interface MergeField {
  key: string
  type: MergeFieldType
  combinable: boolean
  values: MergeFieldValue[]
}

export type MergeCollectionKey =
  | 'contacts'
  | 'addresses'
  | 'sectors'
  | 'referents'
  | 'managers'
  | 'notes'
  | 'documents'
  | 'registries'

export interface MergeCollectionItem {
  id: number
  record_id: number
  label: string
  detail: string | null
  match_key: string | null
}

export interface MergeCollection {
  key: MergeCollectionKey
  items: MergeCollectionItem[]
}

export interface MergeLinkedItem {
  id: number
  record_id: number
  label: string
}

export interface MergeLinkedCount {
  record_id: number
  count: number
}

export interface MergeLinked {
  key: string
  count: number
  counts: MergeLinkedCount[]
  items: MergeLinkedItem[]
}

export interface MergePreview {
  suggested_survivor_id: number
  records: MergeRecord[]
  fields: MergeField[]
  collections: MergeCollection[]
  linked: MergeLinked[]
}

/** A field choice: the record to take the value from, or the combined texts. */
export type MergeChoice = number | 'combine'

export interface MergePayload {
  survivor_id: number
  merged_ids: number[]
  fields?: Record<string, MergeChoice>
  discard?: Partial<Record<MergeCollectionKey, number[]>>
}
