import { useWatch, type Control } from 'react-hook-form'
import type { ForSelectItem } from '@/features/for-select/types'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE } from '@/features/business-functions/for-select-api'
import type { BusinessFunctionRecordValues } from '@/features/business-functions/business-function-record'
import type { BusinessFunctionFormValues } from '@/features/business-functions/use-business-function-form'
import { COMPANIES_FOR_SELECT_RESOURCE } from '@/features/companies/for-select-api'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'

/** Shown while a label resolves: never the bare id. */
const PENDING_LABEL = '…'

/** Stable empty default: a fresh array per render would break the label hooks' memo. */
const NO_IDS: number[] = []

function idsOf(id: number | null | undefined): number[] {
  return id != null ? [id] : NO_IDS
}

function labelOf(labels: Map<number, ForSelectItem>, id: number): string {
  return labels.get(id)?.label ?? PENDING_LABEL
}

/**
 * The create draft as the record's closed rows read it: the form holds ids
 * only, so every relation is named through `useForSelectLabels`, the very
 * cache the pickers fill for their own trigger.
 */
export function useBusinessFunctionDraftValues(control: Control<BusinessFunctionFormValues>): BusinessFunctionRecordValues {
  const draft = useWatch({ control })
  const userIds = (draft.users ?? NO_IDS).filter((id): id is number => id !== undefined)
  const siteIds = (draft.operational_sites ?? NO_IDS).filter((id): id is number => id !== undefined)
  const companyIds = (draft.company_ids ?? NO_IDS).filter((id): id is number => id !== undefined)
  const companies = useForSelectLabels({ resource: COMPANIES_FOR_SELECT_RESOURCE, ids: companyIds })
  const parents = useForSelectLabels({ resource: BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE, ids: idsOf(draft.parent_id) })
  const managers = useForSelectLabels({ resource: USERS_FOR_SELECT_RESOURCE, ids: idsOf(draft.manager_id) })
  const users = useForSelectLabels({ resource: USERS_FOR_SELECT_RESOURCE, ids: userIds })
  const sites = useForSelectLabels({ resource: OPERATIONAL_SITES_FOR_SELECT_RESOURCE, ids: siteIds })

  return {
    parent: draft.parent_id != null ? { id: draft.parent_id, name: labelOf(parents, draft.parent_id) } : null,
    manager:
      draft.manager_id != null
        ? { id: draft.manager_id, name: labelOf(managers, draft.manager_id), avatar_url: null }
        : null,
    users: userIds.map((id) => ({ id, name: labelOf(users, id), avatar_url: null })),
    operational_sites: siteIds.map((id) => ({ id, label: labelOf(sites, id) })),
    companies: companyIds.map((id) => ({ id, label: labelOf(companies, id) })),
    custom_fields: (draft.custom_fields ?? {}) as BusinessFunctionRecordValues['custom_fields'],
  }
}
