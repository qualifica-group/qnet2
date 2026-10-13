/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchQuoteWorkflow, quoteWorkflowDetailQueryKey } from '@/features/quote-workflows/api'
import { QuoteWorkflowForm } from '@/features/quote-workflows/quote-workflow-form'
import { QuoteWorkflowDetailView } from '@/features/quote-workflows/quote-workflow-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { QuoteWorkflowDetail } from '@/features/quote-workflows/types'

/**
 * Content-only `quote-workflows` screens for the module registry (spec
 * 0042): fetch + the presentational view/form, no page chrome. Reused as-is
 * by the modal Sheet (`useModuleOpener`) and by the generic dedicated pages
 * (`ModuleDetailPage`/`ModuleFormPage`). The detail edits its rows in place,
 * so `onEdit` is never used; each save reports through `onChanged`.
 */
export function QuoteWorkflowDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: quoteWorkflow,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(quoteWorkflowDetailQueryKey(id), () => fetchQuoteWorkflow(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('quoteWorkflows.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !quoteWorkflow) {
    return <DetailLoading />
  }

  return <QuoteWorkflowDetailView quoteWorkflow={quoteWorkflow} onChanged={onChanged} />
}

/**
 * Create only: there is no edit form, and the registry generates no
 * `:id/edit` route. Leaving a workflow being created (Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload) always asks first.
 */
export function QuoteWorkflowFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('quoteWorkflows.form.leaveConfirm.title'),
    description: t('quoteWorkflows.form.leaveConfirm.description'),
    confirmLabel: t('quoteWorkflows.form.leaveConfirm.confirm'),
    cancelLabel: t('quoteWorkflows.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: QuoteWorkflowDetail) => {
    queryClient.invalidateQueries({ queryKey: quoteWorkflowDetailQueryKey(saved.id) })
    // Saved: the navigation to the new record's detail is no "leaving".
    leaveGuard.allowLeave()
    onSuccess(saved.id)
  }

  const handleCancel = () => {
    void leaveGuard.confirmLeave().then((leave) => {
      if (leave) {
        onCancel()
      }
    })
  }

  return (
    <>
      {leaveGuard.navigationGuard}
      <QuoteWorkflowForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'quote-workflows',
  basePath: '/quote-workflows',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.quoteWorkflows',
  DetailScreen: QuoteWorkflowDetailScreen,
  FormScreen: QuoteWorkflowFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
