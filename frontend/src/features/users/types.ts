/**
 * Users CRUD types. The generic table types (columns/filters/actions/rows) live
 * in `features/table/types.ts`; this file holds only what is genuinely
 * users-specific — the user resource and its create/update payloads.
 * Source of truth: the frozen Users CRUD API contract.
 */

import type { PersonalDataCard } from '@/features/personal-data/types'
import type { ProductLineRelationRef } from '@/features/product-lines/types'
import type { PersonalDataPayload } from '@/features/personal-data/drafts'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldValue } from '@/features/custom-fields/types'

/**
 * Allowed UI/user locales. Kept as a defensive guard only — the selectable
 * locales are served by the backend (GET /api/config → enums.locale) and the
 * form renders them from there, never from this literal.
 */
export type UserLocale = 'en' | 'it'

/** A role membership as returned by the backend ({id, name}). */
export interface UserRole {
  id: number
  name: string
}

/** Employment relationship with the organization (spec 0015). */
export const RELATIONSHIP_TYPES = ['employee', 'self_employed', 'intern', 'other'] as const
export type RelationshipType = (typeof RELATIONSHIP_TYPES)[number]

/** Working time of a contract condition (spec 0291 D-3). */
export const WORKING_TIMES = ['full_time', 'part_time'] as const
export type WorkingTime = (typeof WORKING_TIMES)[number]

/** The condition in force, as summarised on the employment profile (spec 0272). */
export interface CurrentContract {
  starts_on: string
  contract_type: NamedRef | null
  working_time: WorkingTime | null
  /** `Y-m-d`; informative only (spec 0291 D-4). */
  contract_expires_on: string | null
}

/** A `{id, name}` function reference (spec 0275 contract), unlike the `{id, label}` employment refs. */
export interface NamedRef {
  id: number
  name: string
}

/** A related entity reference as returned inside `employment` ({id, label}). */
export interface EmploymentRelationRef {
  id: number
  label: string
  subtitle?: string | null
}

/**
 * A competence row as returned by the server (spec 0111, nullable category
 * since spec 0129 D-3): `product_category: null` means "all categories of the
 * function". Distinct from the shared `ProductLine` (category always
 * present, used by offers/opportunities) since only the user's competence
 * carries this null.
 */
export interface EmploymentProductLine {
  id: number
  business_function: ProductLineRelationRef
  product_category: ProductLineRelationRef | null
}

/**
 * The user's employment profile (spec 0015), present only when the backend
 * loaded it. `standard_daily_minutes`/`break_daily_minutes` are stored as
 * plain minute counts (0..1440), not a TIME column. The contractual keys are
 * optional: the server leaves out those the actor's field matrix hides (spec 0283).
 */
export interface EmploymentDetail {
  id: number
  relationship_type?: RelationshipType | null
  hired_at?: string | null
  terminated_at?: string | null
  standard_daily_minutes?: number | null
  break_daily_minutes?: number | null
  /** Euro/hour from the salary in force today (spec 0277 D-5): present only with `users.manageCompensation`. */
  hourly_cost?: number | null
  /** Zero or more managers (spec 0166), ordered by name; [] when none. */
  reports_to_ids: number[]
  /** Mansioni ids (spec 0271); [] when none. */
  job_role_ids: number[]
  /** Funzioni aziendali di appartenenza (spec 0275): same data as the function's "Utenti associati"; [] when none. */
  business_function_ids: number[]
  company_id?: number | null
  /** The condition in force today (spec 0272 D-2) and its start; null when the history is empty. */
  current_contract?: CurrentContract | null
  /** Payment account (spec 0272 D-6): present only with `users.manageCompensation`. */
  iban?: string | null
  iban_holder?: string | null
  /** The user's single physical site (spec 0103 D-3), at most one, optional. */
  primary_operational_site_id: number | null
  /** The user's remote sites (spec 0103 D-1): operative exactly like the physical one. */
  remote_operational_site_ids: number[]
  /**
   * Spec 0194: explicit "Assegnabile" flag. False excludes the person from every
   * record assignment pool even with Sede and competence. Scalar flag.
   */
  is_assignable: boolean
  /**
   * Spec 0129 D-1: jolly flag — competent for ANY category, regardless of
   * business function, when true. Scalar flag.
   */
  covers_all_product_categories: boolean
  /**
   * Assignment competence (spec 0111): the business-function -> product-category
   * pairs this user covers, the single source of both. Optional because the
   * backend emits it only when the `productLines` relation was eager-loaded
   * (`whenLoaded` discipline); the `{id, name}` projections also label the row
   * editor without a hydration fetch.
   */
  product_lines?: EmploymentProductLine[]
  /** Same order as `reports_to_ids`; emitted whenever the relation is loaded (always in the detail). */
  reports_to: EmploymentRelationRef[]
  /** Mansioni (spec 0271), same order as `job_role_ids`. */
  job_roles: NamedRef[]
  /** Funzioni aziendali di appartenenza (spec 0275), same order as `business_function_ids`. */
  business_functions: NamedRef[]
  company?: EmploymentRelationRef | null
  /** Present only when the relation is eager-loaded (whenLoaded), like `company`. */
  primary_operational_site?: EmploymentRelationRef | null
  remote_operational_sites?: EmploymentRelationRef[]
}

/**
 * A competence row as sent to the server (create/update payload, spec 0111;
 * nullable category since spec 0129 D-3: `null` = "all categories of the
 * function").
 */
export interface EmploymentProductLineInput {
  business_function_id: number
  product_category_id: number | null
}

/**
 * Single user detail returned by GET/POST/PATCH /users (envelope `data`).
 * Matches UserResource in the frozen API contract.
 */
export interface UserDetail {
  id: number
  /** Legacy id; sent only to an actor allowed to see it (`legacy-ids.view`). */
  old_id?: number | null
  name: string
  email: string
  locale: UserLocale
  /** Whether the account may sign in. An inactive user is denied login. */
  is_active: boolean
  /** Role memberships as {id, name}: the form picks by id, the detail shows name. */
  roles: UserRole[]
  /** Absolute URL to the authenticated avatar download endpoint, or null. */
  avatar_url: string | null
  /** True while the user has not completed the first access (spec 0177). */
  must_set_password: boolean
  /**
   * The user's personal-data card (with its contacts and addresses), present only
   * when the backend loaded it (whenLoaded). Absent/null when the user has none.
   */
  personal_data?: PersonalDataCard | null
  /** The user's employment profile (spec 0015), null when the user has none. */
  employment?: EmploymentDetail | null
  /** Functions this user manages (spec 0275 D-6), read only, ordered by name; [] when none. */
  managed_business_functions: NamedRef[]
  created_at: string | null
  /** Custom field values keyed by their raw (un-namespaced) key (spec 0021). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * A `UserDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /users/{user}` (`show`). Used to
 * seed the edit form's `ResourcePermissionsProvider` without a second request.
 */
export interface UserDetailWithPermissions extends UserDetail {
  permissions: ResourcePermissions
}

/**
 * The nested `employment` object accepted by the user write endpoints
 * (spec 0015), written atomically with the user in the same request. Always
 * built and sent from the form (upsert semantics), mirroring `personal_data`.
 */
export interface EmploymentPayload {
  /** Mansioni ids (spec 0271): always sent, [] clears them. */
  job_role_ids: number[]
  /** Funzioni aziendali di appartenenza (spec 0275): always sent, [] clears them (the server refuses it for an active user). */
  business_function_ids: number[]
  /** Funzioni aziendali gestite (spec 0276): the listed ones take this user as head; always sent, [] leaves none. */
  managed_business_function_ids: number[]
  /** Zero or more manager ids (spec 0166). */
  reports_to_ids: number[]
  /** At most one physical site (spec 0103 D-3); mirrors `EmploymentDetail`. */
  primary_operational_site_id: number | null
  /** Zero or more remote sites (spec 0103 D-1). */
  remote_operational_site_ids: number[]
  /** Spec 0194: always sent by the form; the server leaves the column untouched only when the key is absent. */
  is_assignable: boolean
  /** Spec 0129 D-1/D-10: scalar jolly flag, (absent = false). */
  covers_all_product_categories: boolean
  /**
   * Assignment competence (spec 0111): the function/category pairs the user
   * covers. Spec 0129 D-2: always `[]` when `covers_all_product_categories`
   * is true (the server also enforces this — 422 otherwise).
   */
  product_lines: EmploymentProductLineInput[]
  /** Spec 0283 D-5: the contract data travel only when changed; absent = untouched. */
  hired_at?: string | null
  terminated_at?: string | null
  standard_daily_minutes?: number | null
  break_daily_minutes?: number | null
  /** Payment account (spec 0272 D-6): sent only when changed, and only with `users.manageCompensation`. */
  iban?: string | null
  iban_holder?: string | null
}

/** Payload for POST /users (create). Password omitted = invite mode, present = temporary password (spec 0177). */
export interface CreateUserPayload {
  email: string
  locale: UserLocale
  /** Whether the account may sign in (defaults to true server-side when omitted). */
  is_active: boolean
  /** Role IDS to assign (for-select standard, ADR 0011). */
  roles?: number[]
  password?: string
  password_confirmation?: string
  /**
   * The nested personal-data card written atomically with the user (ADR 0012).
   * Required: `users.name` is derived server-side from this card (the user form
   * has no `name` field), so identity must always be present on create.
   */
  personal_data: PersonalDataPayload
  /** The nested employment profile written atomically with the user (spec 0015). */
  employment: EmploymentPayload
  /** All valued custom fields, keyed by raw key (spec 0021, create = full set). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * Payload for PATCH /users/{id} (partial update). Every field is optional so
 * the request only carries what actually changed.
 */
export interface UpdateUserPayload {
  email?: string
  locale?: UserLocale
  /** Whether the account may sign in. Sent only when toggled. */
  is_active?: boolean
  /** Role IDS to assign (for-select standard, ADR 0011). */
  roles?: number[]
  password?: string
  password_confirmation?: string
  /**
   * Optional nested profile written atomically with the user (ADR 0012). Omit to
   * leave the card untouched; present = card upsert plus authoritative child sync.
   */
  personal_data?: PersonalDataPayload
  /** The nested employment profile written atomically with the user (spec 0015). */
  employment?: EmploymentPayload
  /** Only the custom fields that changed, keyed by raw key (spec 0021, sparse diff). */
  custom_fields?: Record<string, CustomFieldValue>
}

/** Discriminated form mode: a bare create, or the in-place detail of a persisted user (spec 0222). */
export type UserFormMode = { type: 'create' } | { type: 'edit'; user: UserDetailWithPermissions }
