import { useQuery } from '@tanstack/react-query'
import { fetchSubAccountOptions } from '@/features/treasury-movements/api'
import { treasuryMovementKeys } from '@/features/treasury-movements/query-keys'
import {
  SUB_ACCOUNT_KINDS_BY_TYPE,
  type SubAccountKind,
  type SubAccountOption,
  type TreasuryMovementType,
  type TreasurySubAccountRef,
} from '@/features/treasury-movements/types'

const SUB_ACCOUNT_STALE_MS = 60 * 1000
const NO_OPTIONS: SubAccountOption[] = []

function useKindOptions(kind: SubAccountKind, enabled: boolean): SubAccountOption[] {
  const { data } = useQuery({
    queryKey: treasuryMovementKeys.subAccounts(kind),
    queryFn: () => fetchSubAccountOptions(kind),
    enabled,
    staleTime: SUB_ACCOUNT_STALE_MS,
  })
  return data ?? NO_OPTIONS
}

export interface SubAccountGroup {
  kind: SubAccountKind
  options: SubAccountOption[]
}

/**
 * Selectable sub-accounts of a movement type, grouped by kind. The sub-account
 * already saved on the movement is kept even when deactivated (spec 0355).
 */
export function useSubAccountGroups(
  type: TreasuryMovementType,
  saved: TreasurySubAccountRef | null,
): SubAccountGroup[] {
  const allowed = SUB_ACCOUNT_KINDS_BY_TYPE[type]
  const cost = useKindOptions('cost', allowed.includes('cost'))
  const revenue = useKindOptions('revenue', allowed.includes('revenue'))
  const financial = useKindOptions('financial', allowed.includes('financial'))
  const byKind: Record<SubAccountKind, SubAccountOption[]> = { cost, revenue, financial }

  return allowed.map((kind) => {
    const options = byKind[kind]
    const missingSaved = saved !== null && saved.kind === kind && !options.some((o) => o.id === saved.id)
    return {
      kind,
      options: missingSaved
        ? [...options, { kind, id: saved.id, label: saved.code ? `${saved.code} ${saved.name}` : saved.name }]
        : options,
    }
  })
}
