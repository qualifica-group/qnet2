import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { QuoteSystemStatusFormBody } from '@/features/quote-system-statuses/quote-system-status-form-body'
import type { QuoteSystemStatusDetail } from '@/features/quote-system-statuses/types'

interface QuoteSystemStatusFormProps {
  /** Called after a successful create so the caller can navigate to the detail. */
  onSuccess: (quoteSystemStatus: QuoteSystemStatusDetail) => void
  onCancel: () => void
}

/**
 * The system status create form (the detail edits in place). Metadata-driven
 * (spec 0004): resolves the create-context permissions before rendering the body.
 */
export function QuoteSystemStatusForm(props: QuoteSystemStatusFormProps) {
  const { t } = useTranslation()
  const metaQuery = useResourceMeta('quote-system-statuses')

  if (metaQuery.isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('authorization.loadError')}
        </p>
        <Button variant="outline" size="sm" className="bg-card" onClick={() => void metaQuery.refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (!metaQuery.data) {
    return <RecordFormSkeleton />
  }

  return (
    <ResourcePermissionsProvider permissions={metaQuery.data.permissions}>
      <QuoteSystemStatusFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
