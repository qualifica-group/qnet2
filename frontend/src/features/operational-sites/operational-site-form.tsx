import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useOperationalSiteFormMeta } from '@/features/operational-sites/use-operational-site-form-meta'
import { OperationalSiteFormBody } from '@/features/operational-sites/operational-site-form-body'
import type {
  OperationalSiteDetail,
  OperationalSiteFormMode,
} from '@/features/operational-sites/types'

interface OperationalSiteFormProps {
  mode: OperationalSiteFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (operationalSite: OperationalSiteDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * Reusable RHF + Zod form used for both creating and editing an operational
 * site. Metadata-driven (spec 0004): resolves the resource's
 * `ResourcePermissions` before rendering — edit mode from the loaded instance
 * detail, create mode from `GET /meta/operational-sites` — then hands off to
 * `OperationalSiteFormBody`, which reads every field from that context via
 * `MetaField`/`useResourcePermissions()`.
 */
export function OperationalSiteForm(props: OperationalSiteFormProps) {
  const { t } = useTranslation()
  const meta = useOperationalSiteFormMeta(props.mode)

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
      <OperationalSiteFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
