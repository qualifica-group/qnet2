import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useDocumentLayoutFormMeta } from '@/features/document-layouts/use-document-layout-form-meta'
import { DocumentLayoutFormBody } from '@/features/document-layouts/document-layout-form-body'
import type {
  DocumentLayoutDetail,
  DocumentLayoutFormMode,
} from '@/features/document-layouts/types'

interface DocumentLayoutFormProps {
  mode: DocumentLayoutFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (documentLayout: DocumentLayoutDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * Reusable RHF + Zod form used for both creating and editing a document
 * layout's METADATA (name/code/description/module/is_active/is_default —
 * spec 0069 wave 1; the block/zone `config` is edited by the visual editor,
 * wave 2, not this form). Metadata-driven (spec 0004): resolves the
 * resource's `ResourcePermissions` before rendering — edit mode from the
 * loaded instance detail, create mode from `GET /meta/document-layouts` —
 * then hands off to `DocumentLayoutFormBody`, which reads every field from
 * that context via `MetaField`/`useResourcePermissions()`.
 */
export function DocumentLayoutForm(props: DocumentLayoutFormProps) {
  const { t } = useTranslation()
  const meta = useDocumentLayoutFormMeta(props.mode)

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
      <DocumentLayoutFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
