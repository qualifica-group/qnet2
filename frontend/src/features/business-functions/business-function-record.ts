import type {
  BusinessFunctionCompany,
  BusinessFunctionDetail,
  BusinessFunctionMember,
  BusinessFunctionOperationalSite,
  BusinessFunctionParent,
} from '@/features/business-functions/types'
import type { CustomFieldValue } from '@/features/custom-fields/types'

/**
 * What the closed rows of a business function show beyond the form's own
 * text and type: the relations as named records. The detail reads them from
 * the saved record, the create draft resolves them from the picked ids
 * (`useBusinessFunctionDraftValues`), so one set of rows serves both.
 */
export interface BusinessFunctionRecordValues {
  parent: BusinessFunctionParent | null
  /** Spec 0280 D-8: known on a saved record only, a draft has none yet. */
  inactive_ancestor: BusinessFunctionParent | null
  manager: BusinessFunctionMember | null
  users: BusinessFunctionMember[]
  operational_sites: BusinessFunctionOperationalSite[]
  companies: BusinessFunctionCompany[]
  custom_fields: Record<string, CustomFieldValue>
}

const NO_CUSTOM_FIELDS: Record<string, CustomFieldValue> = {}

/** The saved business function as the closed rows read it. */
export function persistedBusinessFunctionValues(businessFunction: BusinessFunctionDetail): BusinessFunctionRecordValues {
  return {
    parent: businessFunction.parent,
    inactive_ancestor: businessFunction.inactive_ancestor,
    manager: businessFunction.manager,
    users: businessFunction.users,
    operational_sites: businessFunction.operational_sites,
    companies: businessFunction.companies,
    custom_fields: businessFunction.custom_fields ?? NO_CUSTOM_FIELDS,
  }
}
