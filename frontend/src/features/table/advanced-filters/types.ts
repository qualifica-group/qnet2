/**
 * Types for the generic "advanced filters" panel (spec 0032): a second,
 * backend-driven filter level mounted above every domain's grid. One panel +
 * field registry serves all 21 domains — the frontend never hardcodes a
 * domain's filter shape, it renders whatever descriptor catalog the backend
 * returns in `TableConfig.advancedFilters`.
 */

/**
 * Advanced filter field type. The backend declares which of these 17 types a
 * given filter uses; the field registry (`field-registry.ts`) maps each to a
 * concrete component.
 */
export type AdvancedFilterType =
  | 'text'
  | 'textarea'
  | 'number'
  | 'number_range'
  | 'date'
  | 'date_range'
  | 'datetime'
  | 'select'
  | 'multiselect'
  | 'autocomplete'
  | 'autocomplete_multi'
  | 'checkbox'
  | 'switch'
  | 'radio'
  | 'enum'
  | 'relation'
  | 'async_search'

/** Layout width token for an advanced filter field; maps to a compact max-w class. */
export type AdvancedFilterWidth = 'sm' | 'md' | 'lg' | 'full'

/** Static option for `select`/`radio` inline options (not for-select-backed). */
export interface AdvancedFilterOption {
  value: string | number
  label: string
}

/**
 * Declares that this filter is only usable once another filter (`on`) has a
 * value: the field disables and clears whenever its parent is empty. When
 * `param` is set, the parent's current value is forwarded as that query
 * parameter to the child's for-select request (e.g. a campaign filtered by
 * the selected project).
 */
export interface AdvancedFilterDependency {
  on: string
  param?: string
}

/** A range value as sent for `number_range`/`date_range` filters. */
export interface AdvancedFilterRange<T> {
  from?: T
  to?: T
}

/**
 * Value shape for one advanced filter, keyed by `AdvancedFilterDescriptor.name`
 * in both `appliedAdvancedFilters` and the `advancedFilters` request body. The
 * concrete shape depends on the descriptor's `type` (see spec 0032
 * data_contract); kept as a union here since the frontend renders generically.
 */
export type AdvancedFilterValue =
  | string
  | number
  | boolean
  | (string | number)[]
  | AdvancedFilterRange<number>
  | AdvancedFilterRange<string>
  | null

/** Map of filter name -> value, as applied/persisted/sent for one domain. */
export type AdvancedFilterValues = Record<string, AdvancedFilterValue>

/**
 * One advanced filter as declared by the backend catalog for a domain
 * (`GET /tables/{domain}/columns` -> `advancedFilters[]`). FE-facing only:
 * the backend's internal `target`/`operator` are never emitted here.
 */
export interface AdvancedFilterDescriptor {
  /** Unique id within the domain; the key used in `AdvancedFilterValues`. */
  name: string
  /** i18n key. */
  label: string
  type: AdvancedFilterType
  /** i18n key. */
  placeholder?: string
  /** Display order; descriptors arrive already sorted by it. */
  order: number
  defaultValue?: AdvancedFilterValue | null
  required: boolean
  visible: boolean
  width: AdvancedFilterWidth
  /** Relevant for select/autocomplete/relation: whether multiple values are picked. */
  multiple: boolean
  /** for-select resource, for autocomplete/relation/async_search. */
  source?: { resource: string }
  /** Static options, for select/radio inline lists. */
  options?: AdvancedFilterOption[]
  /** `enums.<enumKey>.<value>` i18n lookup, for enum/radio backed by a domain enum. */
  enumKey?: string
  /** Enum values the server withholds from this actor (e.g. Task `assignment` `visible`, spec 0153 D-1). */
  excludedValues?: string[]
  dependency?: AdvancedFilterDependency
}
