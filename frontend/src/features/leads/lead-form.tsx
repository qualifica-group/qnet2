import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { LeadFormBody } from '@/features/leads/lead-form-body'
import type { LeadDetail } from '@/features/leads/types'

interface LeadFormProps {
  /** Called after a successful create so the caller can navigate to the detail. */
  onSuccess: (lead: LeadDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * The lead create form (spec 0217: there is no edit form, the detail edits in
 * place). Metadata-driven (spec 0004): resolves the create-context
 * `ResourcePermissions` (`GET /meta/leads`) before rendering, then hands off
 * to `LeadFormBody`, which reads every field from that context.
 */
export function LeadForm(props: LeadFormProps) {
  const { t } = useTranslation()
  const metaQuery = useResourceMeta('leads')

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
      <LeadFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
