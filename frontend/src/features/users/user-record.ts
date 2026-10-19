import type { UseFormReturn } from 'react-hook-form'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { ProductLineLike } from '@/features/product-lines/types'
import { summarizeAssignment, type AssignmentSummary } from '@/features/users/user-assignment'
import type {
  CurrentContract,
  EmploymentDetail,
  EmploymentRelationRef,
  NamedRef,
  RelationshipType,
  UserDetail,
  UserLocale,
} from '@/features/users/types'
import type { UserFormValues } from '@/features/users/use-user-form'

/**
 * The fields each editor shows besides its own (spec 0222): a rule failing on
 * one of them already has a message on screen.
 */
export const EDITOR_EXTRA_FIELDS: Readonly<Record<string, readonly string[]>> = {
  password: ['password_confirmation'],
}

/** The jolly switch clears the competence rows (spec 0129 D-2): same rule. */
export const COVERS_ALL_CASCADE_FIELDS = [
  'employment.covers_all_product_categories',
  'employment.product_lines',
] as const

/**
 * What the closed Password row says (spec 0222 D-5): on the detail whether the
 * first access is still pending, on create whether the user gets an invite or
 * a temporary password.
 */
export type PasswordState = 'set' | 'pending' | 'invite' | 'temporary'

/** What the record's rows show: the persisted user on the detail, the draft (with resolved labels) on create. */
export interface UserRecordValues {
  email: string
  locale: UserLocale
  is_active: boolean
  password_state: PasswordState
  roles: EmploymentRelationRef[]
  is_assignable: boolean
  covers_all_product_categories: boolean
  /** The competence pairs as the read-only list names them (a null category = all of the function). */
  product_lines: ProductLineLike[]
  primary_operational_site: EmploymentRelationRef | null
  remote_operational_sites: EmploymentRelationRef[]
  job_roles: EmploymentRelationRef[]
  business_functions: EmploymentRelationRef[]
  managed_business_functions: EmploymentRelationRef[]
  reports_to: EmploymentRelationRef[]
  relationship_type: RelationshipType | null
  company: EmploymentRelationRef | null
  /** The condition in force (spec 0272 D-2), read-only: its CCNL level and start. */
  current_contract: CurrentContract | null
  /** Payment account (spec 0272 D-6); null also when the actor may not see it. */
  iban: string | null
  iban_holder: string | null
  hired_at: string | null
  terminated_at: string | null
  standard_daily_minutes: number | null
  break_daily_minutes: number | null
  /** Derived from the salary in force today (spec 0277); null also when the actor may not see it. */
  hourly_cost: number | null
  /** The assignment verdict these values add up to (competence + Sedi + flag). */
  assignment: AssignmentSummary
  custom_fields: Record<string, CustomFieldValue>
}

/** Everything a record section edits with: the shown values, the form and the single open row. */
export interface UserRecordSectionProps {
  values: UserRecordValues
  form: UseFormReturn<UserFormValues>
  inline: InlineEdit
}

/** `{id, label}` refs as the relation pickers' `{id, name}` hydration. */
export function toFieldRefs(refs: EmploymentRelationRef[]): RelationFieldRef[] {
  return refs.map((ref) => ({ id: ref.id, name: ref.label }))
}

/** `{id, name}` contract refs as the record's `{id, label}` rows read them. */
function toRelationRefs(refs: NamedRef[] | undefined): EmploymentRelationRef[] {
  return refs ? refs.map((ref) => ({ id: ref.id, label: ref.name })) : NO_REFS
}

/** Stable empty defaults: a user with no employment profile reads like an empty one. */
const NO_REFS: EmploymentRelationRef[] = []
const NO_LINES: ProductLineLike[] = []
const NO_IDS: number[] = []

/**
 * Projects the persisted profile onto `summarizeAssignment`'s input. A null
 * `product_category` (spec 0129 D-3) is a deliberate "all categories of the
 * function" row, not an incomplete one.
 */
function persistedAssignment(employment: EmploymentDetail | null): AssignmentSummary {
  return summarizeAssignment({
    competenceRows: (employment?.product_lines ?? []).map((line) => ({
      business_function_id: line.business_function.id,
      product_category_id: line.product_category?.id ?? null,
      all_categories: line.product_category === null,
    })),
    isAssignable: employment?.is_assignable ?? true,
    coversAllProductCategories: employment?.covers_all_product_categories ?? false,
    primarySiteId: employment?.primary_operational_site_id ?? null,
    remoteSiteIds: employment?.remote_operational_site_ids ?? NO_IDS,
  })
}

/** The persisted user as the record's rows read it. */
export function persistedUserValues(user: UserDetail): UserRecordValues {
  const employment = user.employment ?? null
  return {
    email: user.email,
    locale: user.locale,
    is_active: user.is_active,
    password_state: user.must_set_password ? 'pending' : 'set',
    roles: user.roles.map((role) => ({ id: role.id, label: role.name })),
    is_assignable: employment?.is_assignable ?? true,
    covers_all_product_categories: employment?.covers_all_product_categories ?? false,
    product_lines: employment?.product_lines ?? NO_LINES,
    primary_operational_site: employment?.primary_operational_site ?? null,
    remote_operational_sites: employment?.remote_operational_sites ?? NO_REFS,
    job_roles: toRelationRefs(employment?.job_roles),
    business_functions: toRelationRefs(employment?.business_functions),
    managed_business_functions: toRelationRefs(user.managed_business_functions),
    reports_to: employment?.reports_to ?? NO_REFS,
    relationship_type: employment?.relationship_type ?? null,
    company: employment?.company ?? null,
    current_contract: employment?.current_contract ?? null,
    iban: employment?.iban ?? null,
    iban_holder: employment?.iban_holder ?? null,
    hired_at: employment?.hired_at ?? null,
    terminated_at: employment?.terminated_at ?? null,
    standard_daily_minutes: employment?.standard_daily_minutes ?? null,
    break_daily_minutes: employment?.break_daily_minutes ?? null,
    hourly_cost: employment?.hourly_cost ?? null,
    assignment: persistedAssignment(employment),
    custom_fields: user.custom_fields ?? {},
  }
}
