import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useRegistryFormMeta } from '@/features/registries/use-registry-form-meta'
import { RegistryFormBody } from '@/features/registries/registry-form-body'
import type { RegistryDetail, RegistryFormMode } from '@/features/registries/types'

interface RegistryFormProps {
  mode: RegistryFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (registry: RegistryDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * Reusable RHF + Zod form used for both creating and editing a registry.
 * Metadata-driven (spec 0004): resolves the resource's `ResourcePermissions`
 * before rendering — edit mode from the loaded instance detail, create mode
 * from `GET /meta/registries` — then hands off to `RegistryFormBody`, which
 * reads every field from that context via `MetaField`/`useResourcePermissions()`.
 */
export function RegistryForm(props: RegistryFormProps) {
  const { t } = useTranslation()
  const meta = useRegistryFormMeta(props.mode)

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
      <RegistryFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
