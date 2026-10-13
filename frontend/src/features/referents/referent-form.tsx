import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { ReferentFormBody } from '@/features/referents/referent-form-body'
import type { ReferentDetail } from '@/features/referents/types'

interface ReferentFormProps {
  /** Called after a successful create so the caller can close + refresh. */
  onSuccess: (referent: ReferentDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * The referente create form (spec 0216: there is no edit form, the detail
 * edits in place). Metadata-driven (spec 0004): resolves the create-context
 * `ResourcePermissions` (`GET /meta/referents`) before rendering, then hands
 * off to `ReferentFormBody`, which reads every field from that context.
 */
export function ReferentForm(props: ReferentFormProps) {
  const { t } = useTranslation()
  const metaQuery = useResourceMeta('referents')

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
      <ReferentFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
