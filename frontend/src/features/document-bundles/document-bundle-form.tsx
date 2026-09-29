import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useDocumentBundleFormMeta } from '@/features/document-bundles/use-document-bundle-form-meta'
import { DocumentBundleFormBody } from '@/features/document-bundles/document-bundle-form-body'
import type { DocumentBundle, DocumentBundleFormMode } from '@/features/document-bundles/types'

interface DocumentBundleFormProps {
  mode: DocumentBundleFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (documentBundle: DocumentBundle) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * Reusable RHF + Zod form used for both creating and editing a document
 * bundle. Metadata-driven (spec 0004): resolves the resource's
 * `ResourcePermissions` before rendering — edit mode from the loaded
 * instance detail, create mode from `GET /meta/document-bundles` — then
 * hands off to `DocumentBundleFormBody`, which reads every field from that
 * context via `MetaField`/`useResourcePermissions()`.
 */
export function DocumentBundleForm(props: DocumentBundleFormProps) {
  const { t } = useTranslation()
  const meta = useDocumentBundleFormMeta(props.mode)

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
        <Button variant="outline" size="sm" className="bg-card" onClick={meta.retry}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  return (
    <ResourcePermissionsProvider permissions={meta.permissions}>
      <DocumentBundleFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
