/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreens map colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchQuoteWorkflow, quoteWorkflowDetailQueryKey } from '@/features/quote-workflows/api'
import { QuoteWorkflowForm } from '@/features/quote-workflows/quote-workflow-form'
import { QuoteWorkflowDetailView } from '@/features/quote-workflows/quote-workflow-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleScreens,
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
 * Create or duplicate (spec 0354): there is no edit form, and the registry
 * generates no `:id/edit` route. Leaving a workflow being created (Cancel,
 * the Sheet's X/overlay/Esc, a link, a reload) always asks first.
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

  if (mode.type === 'edit') {
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
      {mode.type === 'duplicate' ? (
        <QuoteWorkflowDuplicateLoader workflowId={mode.id} onSuccess={handleSuccess} onCancel={handleCancel} />
      ) : (
        <QuoteWorkflowForm onSuccess={handleSuccess} onCancel={handleCancel} />
      )}
    </>
  )
}

interface QuoteWorkflowDuplicateLoaderProps {
  workflowId: number
  onSuccess: (saved: QuoteWorkflowDetail) => void
  onCancel: () => void
}

/**
 * Fetches the source workflow fresh (row action "duplicate") before mounting
 * the create form pre-filled from it, so the copy starts from its current
 * statuses rather than a grid snapshot. The form still submits via POST.
 */
function QuoteWorkflowDuplicateLoader({ workflowId, onSuccess, onCancel }: QuoteWorkflowDuplicateLoaderProps) {
  const { t } = useTranslation()
  const {
    data: source,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(quoteWorkflowDetailQueryKey(workflowId), () => fetchQuoteWorkflow(workflowId))

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

  if (isLoading || !source) {
    return <RecordFormSkeleton />
  }

  return <QuoteWorkflowForm source={source} onSuccess={onSuccess} onCancel={onCancel} />
}

/** Screens of this module, loaded on first use by the module registry (spec 0042). */
export const moduleScreens: ModuleScreens = {
  DetailScreen: QuoteWorkflowDetailScreen,
  FormScreen: QuoteWorkflowFormScreen,
}
