import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useUnitOfMeasureFormMeta } from '@/features/units-of-measure/use-unit-of-measure-form-meta'
import { UnitOfMeasureFormBody } from '@/features/units-of-measure/unit-of-measure-form-body'
import type {
  UnitOfMeasureDetail,
  UnitOfMeasureFormMode,
} from '@/features/units-of-measure/types'

interface UnitOfMeasureFormProps {
  mode: UnitOfMeasureFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (unitOfMeasure: UnitOfMeasureDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * Reusable RHF + Zod form used for both creating and editing a unit of
 * measure. Metadata-driven (spec 0004): resolves the resource's
 * `ResourcePermissions` before rendering — edit mode from the loaded
 * instance detail, create mode from `GET /meta/units-of-measure` — then
 * hands off to `UnitOfMeasureFormBody`, which reads every field from that
 * context via `MetaField`/`useResourcePermissions()`.
 */
export function UnitOfMeasureForm(props: UnitOfMeasureFormProps) {
  const { t } = useTranslation()
  const meta = useUnitOfMeasureFormMeta(props.mode)

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
      <UnitOfMeasureFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
