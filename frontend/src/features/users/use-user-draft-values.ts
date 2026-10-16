import { useWatch, type Control } from 'react-hook-form'
import { BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE } from '@/features/business-functions/for-select-api'
import { COMPANIES_FOR_SELECT_RESOURCE } from '@/features/companies/for-select-api'
import type { ForSelectItem } from '@/features/for-select/types'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'
import { categoryPathLabel } from '@/features/product-lines/category-path-label'
import type { ProductLineLike } from '@/features/product-lines/types'
import { ROLES_FOR_SELECT_RESOURCE } from '@/features/roles/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { JOB_ROLES_FOR_SELECT_RESOURCE } from '@/features/job-roles/for-select-api'
import { summarizeAssignment } from '@/features/users/user-assignment'
import { EMPTY_EMPLOYMENT } from '@/features/users/user-form-defaults'
import type { UserRecordValues } from '@/features/users/user-record'
import type { EmploymentRelationRef } from '@/features/users/types'
import type { EmploymentFormValues } from '@/features/users/user-schema'
import type { UserFormValues } from '@/features/users/use-user-form'

/** Shown while a label resolves: never the bare id. */
const PENDING_LABEL = '…'

/** Stable empty defaults: a fresh value per render would break the label hooks' memo. */
const NO_IDS: number[] = []
const NO_TREE: ProductCategoryTreeNode[] = []

function idsOf(id: number | null | undefined): number[] {
  return id != null ? [id] : NO_IDS
}

function refOf(labels: Map<number, ForSelectItem>, id: number | null | undefined): EmploymentRelationRef | null {
  return id != null ? { id, label: labels.get(id)?.label ?? PENDING_LABEL } : null
}

function refsOf(labels: Map<number, ForSelectItem>, ids: number[]): EmploymentRelationRef[] {
  return ids.map((id) => ({ id, label: labels.get(id)?.label ?? PENDING_LABEL }))
}

/**
 * The create draft as the record's closed rows read it (spec 0222): the form
 * holds ids only, so every relation is named through `useForSelectLabels` —
 * the very cache the pickers fill for their own trigger — and each competence
 * row through the business functions' labels and the cached category tree.
 */
export function useUserDraftValues(control: Control<UserFormValues>): UserRecordValues {
  const draft = useWatch({ control })
  // `useWatch` answers deep-partial: read the profile back as the form's own shape.
  const employment: EmploymentFormValues = {
    ...EMPTY_EMPLOYMENT,
    ...(draft.employment as Partial<EmploymentFormValues> | undefined),
  }
  const roleIds = (draft.roles ?? NO_IDS).filter((id): id is number => id != null)
  const competenceRows = employment.product_lines
  const functionIds = [
    ...new Set(competenceRows.map((row) => row.business_function_id).filter((id): id is number => id !== null)),
  ]

  const roles = useForSelectLabels({ resource: ROLES_FOR_SELECT_RESOURCE, ids: roleIds })
  const sites = useForSelectLabels({
    resource: OPERATIONAL_SITES_FOR_SELECT_RESOURCE,
    ids: [...idsOf(employment.primary_operational_site_id), ...employment.remote_operational_site_ids],
  })
  const jobRoles = useForSelectLabels({ resource: JOB_ROLES_FOR_SELECT_RESOURCE, ids: employment.job_role_ids })
  const managers = useForSelectLabels({ resource: USERS_FOR_SELECT_RESOURCE, ids: employment.reports_to_ids })
  const companies = useForSelectLabels({ resource: COMPANIES_FOR_SELECT_RESOURCE, ids: idsOf(employment.company_id) })
  const functions = useForSelectLabels({ resource: BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE, ids: functionIds })
  const tree = useProductCategoryTree().data ?? NO_TREE

  const productLines: ProductLineLike[] = competenceRows
    .filter((row) => row.business_function_id !== null)
    .map((row, index) => ({
      // A draft row has no id yet: its position is its identity.
      id: index,
      business_function: {
        id: row.business_function_id as number,
        name: functions.get(row.business_function_id as number)?.label ?? PENDING_LABEL,
      },
      product_category:
        row.all_categories === true || row.product_category_id === null
          ? null
          : { id: row.product_category_id, name: categoryPathLabel(tree, row.product_category_id) },
    }))

  return {
    email: draft.email ?? '',
    locale: draft.locale ?? 'it',
    is_active: draft.is_active ?? true,
    password_state: draft.password ? 'temporary' : 'invite',
    roles: refsOf(roles, roleIds),
    is_assignable: employment.is_assignable,
    covers_all_product_categories: employment.covers_all_product_categories,
    product_lines: productLines,
    primary_operational_site: refOf(sites, employment.primary_operational_site_id),
    remote_operational_sites: refsOf(sites, employment.remote_operational_site_ids),
    is_manager: employment.is_manager,
    job_roles: refsOf(jobRoles, employment.job_role_ids),
    reports_to: refsOf(managers, employment.reports_to_ids),
    relationship_type: employment.relationship_type,
    company: refOf(companies, employment.company_id),
    qualification_type: employment.qualification_type,
    hired_at: employment.hired_at || null,
    terminated_at: employment.terminated_at || null,
    standard_daily_minutes: employment.standard_daily_minutes,
    break_daily_minutes: employment.break_daily_minutes,
    assignment: summarizeAssignment({
      isAssignable: employment.is_assignable,
      competenceRows,
      coversAllProductCategories: employment.covers_all_product_categories,
      primarySiteId: employment.primary_operational_site_id,
      remoteSiteIds: employment.remote_operational_site_ids,
    }),
    custom_fields: (draft.custom_fields ?? {}) as UserRecordValues['custom_fields'],
  }
}
