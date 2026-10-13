import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { CompanyFormBody } from '@/features/companies/company-form-body'
import type { CompanyDetail } from '@/features/companies/types'

interface CompanyFormProps {
  /** Called after a successful create so the caller can navigate to the detail. */
  onSuccess: (company: CompanyDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * The company create form (there is no edit form: the detail edits in
 * place). Metadata-driven (spec 0004): resolves the create-context
 * `ResourcePermissions` (`GET /meta/companies`) before rendering
 * `CompanyFormBody`.
 */
export function CompanyForm(props: CompanyFormProps) {
  const { t } = useTranslation()
  const metaQuery = useResourceMeta('companies')

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
      <CompanyFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
