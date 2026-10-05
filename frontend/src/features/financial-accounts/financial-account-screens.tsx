/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchFinancialAccount } from '@/features/financial-accounts/api'
import { FinancialAccountForm } from '@/features/financial-accounts/financial-account-form'
import { FinancialAccountDetailView } from '@/features/financial-accounts/financial-account-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { FinancialAccountDetail } from '@/features/financial-accounts/types'

/** Query key for a single financial account's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['financial-accounts', 'detail', id] as const
}

/**
 * Content-only `financial-accounts` screens for the module registry (spec
 * 0042): fetch + the existing presentational view/form, no page chrome.
 * Reused as-is by the modal Sheet (`useModuleOpener`) and by the generic
 * dedicated pages (`ModuleDetailPage`/`ModuleFormPage`).
 */
export function FinancialAccountDetailScreen({ id, onEdit }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: financialAccount,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(id), () => fetchFinancialAccount(id))

  if (isError) {
    return (
      <DetailError
        message={t('financialAccounts.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !financialAccount) {
    return <DetailLoading />
  }

  return <FinancialAccountDetailView financialAccount={financialAccount} onEdit={onEdit} />
}

export function FinancialAccountFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: FinancialAccountDetail) => {
    queryClient.invalidateQueries({ queryKey: detailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return (
      <FinancialAccountForm
        mode={{ type: 'create' }}
        onSuccess={handleSuccess}
        onCancel={onCancel}
      />
    )
  }

  return (
    <FinancialAccountEditScreen
      financialAccountId={mode.id}
      onSuccess={handleSuccess}
      onCancel={onCancel}
    />
  )
}

interface FinancialAccountEditScreenProps {
  financialAccountId: number
  onSuccess: (financialAccount: FinancialAccountDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized financial account detail before mounting
 * the edit form, so the partial PATCH starts from authoritative values
 * rather than a stale snapshot.
 */
function FinancialAccountEditScreen({
  financialAccountId,
  onSuccess,
  onCancel,
}: FinancialAccountEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: financialAccount,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(financialAccountId), () => fetchFinancialAccount(financialAccountId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('financialAccounts.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !financialAccount) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <FinancialAccountForm
      mode={{ type: 'edit', financialAccount }}
      onSuccess={onSuccess}
      onCancel={onCancel}
    />
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'financial-accounts',
  basePath: '/financial-accounts',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.financialAccounts',
  DetailScreen: FinancialAccountDetailScreen,
  FormScreen: FinancialAccountFormScreen,
  // The record card renders its own Edit action, so the generic page header
  // must not stack a second button — the same registration Opportunita',
  // Utenti and Lead carry.
  detailOwnsEditAction: true,
}
