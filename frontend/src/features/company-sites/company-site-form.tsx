import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { CompanySiteFormBody } from '@/features/company-sites/company-site-form-body'
import type { CompanySiteDetail } from '@/features/company-sites/types'

interface CompanySiteFormProps {
  /** Called after a successful create so the caller can navigate to the detail. */
  onSuccess: (companySite: CompanySiteDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * The company-site create form (there is no edit form: the detail edits in
 * place). Metadata-driven (spec 0004): resolves the create-context
 * `ResourcePermissions` (`GET /meta/company-sites`) before rendering
 * `CompanySiteFormBody`.
 */
export function CompanySiteForm(props: CompanySiteFormProps) {
  const { t } = useTranslation()
  const metaQuery = useResourceMeta('company-sites')

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
      <CompanySiteFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
