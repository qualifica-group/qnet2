import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useQuoteWorkflowFormMeta } from '@/features/quote-workflows/use-quote-workflow-form-meta'
import { QuoteWorkflowFormBody } from '@/features/quote-workflows/quote-workflow-form-body'
import type {
  QuoteWorkflowDetail,
  QuoteWorkflowFormMode,
} from '@/features/quote-workflows/types'

interface QuoteWorkflowFormProps {
  mode: QuoteWorkflowFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (quoteWorkflow: QuoteWorkflowDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * Reusable RHF + Zod form used for both creating and editing an opportunity
 * workflow (spec 0047 Lane C). Metadata-driven (spec 0004): resolves the
 * resource's `ResourcePermissions` before rendering — edit mode from the
 * loaded instance detail, create mode from `GET /meta/quote-workflows`
 * — then hands off to `QuoteWorkflowFormBody`.
 */
export function QuoteWorkflowForm(props: QuoteWorkflowFormProps) {
  const { t } = useTranslation()
  const meta = useQuoteWorkflowFormMeta(props.mode)

  if (meta.status === 'loading') {
    return (
      <div className="flex flex-col gap-4 p-4" aria-hidden="true">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  if (meta.status === 'error') {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('authorization.loadError')}
        </p>
        <Button variant="outline" size="sm" onClick={meta.retry}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  return (
    <ResourcePermissionsProvider permissions={meta.permissions}>
      <QuoteWorkflowFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
