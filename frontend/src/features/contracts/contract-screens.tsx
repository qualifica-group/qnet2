/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreens map colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchContract, contractDetailQueryKey } from '@/features/contracts/api'
import { ContractDetailView } from '@/features/contracts/contract-detail'
import type { ModuleDetailScreenProps, ModuleScreens } from '@/features/modules/types'

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
    error,
    refetch,
  } = useEntityDetail(contractDetailQueryKey(id), () => fetchContract(id))

  if (isError) {
    return (
      <DetailError
        error={error}
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
 * `ModuleScreens.FormScreen` is a mandatory field of the registry
 * type. `mode.type === 'edit'` cannot occur either: `detailOwnsEditAction`
 * suppresses the generic page's Edit button, and `ContractDetailView` never
 * calls its own `onEdit` (edit is the "Modifica dati" dialog, not a route).
 */
export function ContractFormScreen() {
  return null
}

/** Screens of this module, loaded on first use by the module registry (spec 0042). */
export const moduleScreens: ModuleScreens = {
  DetailScreen: ContractDetailScreen,
  FormScreen: ContractFormScreen,
}
