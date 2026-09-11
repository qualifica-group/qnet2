import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useReferentFormMeta } from '@/features/referents/use-referent-form-meta'
import { ReferentFormBody } from '@/features/referents/referent-form-body'
import type { ReferentDetail, ReferentFormMode } from '@/features/referents/types'

interface ReferentFormProps {
  mode: ReferentFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (referent: ReferentDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * Reusable RHF + Zod form used for both creating and editing a referent.
 * Metadata-driven (spec 0004): resolves the resource's `ResourcePermissions`
 * before rendering — edit mode from the loaded instance detail, create mode
 * from `GET /meta/referents` — then hands off to `ReferentFormBody`, which
 * reads every field from that context via `MetaField`/`useResourcePermissions()`.
 */
export function ReferentForm(props: ReferentFormProps) {
  const { t } = useTranslation()
  const meta = useReferentFormMeta(props.mode)

  if (meta.status === 'loading') {
    return <RecordFormSkeleton />
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
      <ReferentFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
