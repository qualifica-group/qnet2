import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { DocumentLayoutFormBody } from '@/features/document-layouts/document-layout-form-body'
import type { DocumentLayoutDetail } from '@/features/document-layouts/types'

interface DocumentLayoutFormProps {
  /** Called after a successful create so the caller can navigate to the detail. */
  onSuccess: (documentLayout: DocumentLayoutDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * The document layout create form (there is no edit form: the detail edits in
 * place). Metadata-driven (spec 0004): resolves the create-context
 * `ResourcePermissions` (`GET /meta/document-layouts`) before rendering
 * `DocumentLayoutFormBody`.
 */
export function DocumentLayoutForm(props: DocumentLayoutFormProps) {
  const { t } = useTranslation()
  const metaQuery = useResourceMeta('document-layouts')

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
      <DocumentLayoutFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
