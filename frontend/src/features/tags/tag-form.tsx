import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { TagFormBody } from '@/features/tags/tag-form-body'
import type { TagDetail } from '@/features/tags/types'

interface TagFormProps {
  /** Called after a successful create so the caller can navigate to the detail. */
  onSuccess: (tag: TagDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * The tag create form (there is no edit form: the detail edits in
 * place). Metadata-driven (spec 0004): resolves the create-context
 * `ResourcePermissions` (`GET /meta/tags`) before rendering
 * `TagFormBody`.
 */
export function TagForm(props: TagFormProps) {
  const { t } = useTranslation()
  const metaQuery = useResourceMeta('tags')

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
      <TagFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
