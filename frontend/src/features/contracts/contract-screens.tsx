/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchContract, contractDetailQueryKey } from '@/features/contracts/api'
import { ContractDetailView } from '@/features/contracts/contract-detail'
import { OPEN_MODE_PAGE } from '@/features/modules/types'
import type { ModuleDetailScreenProps, ModuleRegistryEntry } from '@/features/modules/types'

/**
 * Content-only `contracts` screen for the module registry (spec 0042/0072).
 * `onEdit` is intentionally unused: unlike every other module, a contract has
 * no dedicated edit surface — "Modifica dati" is one of the gated action
 * dialogs inside `ContractDetailView` itself (`ContractActionsBar`), not a
 * navigation target.
 */
export function ContractDetailScreen({ id }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: contract,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(contractDetailQueryKey(id), () => fetchContract(id))

  if (isError) {
    return (
      <DetailError
        message={t('contracts.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !contract) {
    return <DetailLoading />
  }

  return <ContractDetailView contract={contract} />
}

/**
 * A contract is never created nor deleted by hand (D-6): no
 * `create`/`update` route is ever navigated to (see the registry entry
 * below), so this branch is unreachable in practice — kept only because
 * `ModuleRegistryEntry.FormScreen` is a mandatory field of the registry
 * type. `mode.type === 'edit'` cannot occur either: `detailOwnsEditAction`
 * suppresses the generic page's Edit button, and `ContractDetailView` never
 * calls its own `onEdit` (edit is the "Modifica dati" dialog, not a route).
 */
export function ContractFormScreen() {
  return null
}

/**
 * Auto-registered in the module registry (spec 0042). `generateRoutes:
 * false`: contracts has none of the generic `new`/`:id/edit`/`:id/duplicate`
 * routes (D-6) — MT-09 wires only `/contracts` (list) and `/contracts/:id`
 * (the generic `ModuleDetailPage`, by hand) in `router.tsx`, mirroring how
 * every module keeps its list route manual.
 */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'contracts',
  basePath: '/contracts',
  defaultMode: OPEN_MODE_PAGE,
  labelKey: 'navigation.contracts',
  generateRoutes: false,
  detailOwnsEditAction: true,
  DetailScreen: ContractDetailScreen,
  FormScreen: ContractFormScreen,
}
