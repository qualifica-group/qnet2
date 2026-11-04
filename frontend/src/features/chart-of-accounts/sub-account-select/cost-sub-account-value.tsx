import { useMemo } from 'react'
import { COST_TYPES_FOR_SELECT_RESOURCE } from '@/features/cost-types/for-select-api'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import type { CostSubAccountItem } from '@/features/chart-of-accounts/sub-account-select/cost-sub-account'
import { subAccountLabel } from '@/features/chart-of-accounts/sub-account-select/sub-account-groups'

/**
 * Read-only value of a cost sub-account field: code + name, resolved by id through the
 * picker's own label cache; the record's name alone shows until the code is known.
 */
export function CostSubAccountValue({ id, name }: { id: number; name: string }) {
  const ids = useMemo(() => [id], [id])
  const item = useForSelectLabels({ resource: COST_TYPES_FOR_SELECT_RESOURCE, ids }).get(id)
  const code = (item as Partial<CostSubAccountItem> | undefined)?.code
  return <span className="truncate">{subAccountLabel({ code, name: item?.label ?? name })}</span>
}
