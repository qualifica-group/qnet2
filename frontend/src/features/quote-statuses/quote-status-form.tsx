import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useQuoteStatusFormMeta } from '@/features/quote-statuses/use-quote-status-form-meta'
import { QuoteStatusFormBody } from '@/features/quote-statuses/quote-status-form-body'
import type {
  QuoteStatusDetail,
  QuoteStatusFormMode,
} from '@/features/quote-statuses/types'

interface QuoteStatusFormProps {
  mode: QuoteStatusFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (quoteStatus: QuoteStatusDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * Reusable RHF + Zod form used for both creating and editing a quote status.
 * Metadata-driven (spec 0004): resolves the resource's `ResourcePermissions`
 * before rendering — edit mode from the loaded instance detail, create mode
 * from `GET /meta/quote-statuses` — then hands off to `QuoteStatusFormBody`,
 * which reads every field from that context via
 * `MetaField`/`useResourcePermissions()`.
 */
export function QuoteStatusForm(props: QuoteStatusFormProps) {
  const { t } = useTranslation()
  const meta = useQuoteStatusFormMeta(props.mode)

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
      <QuoteStatusFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
