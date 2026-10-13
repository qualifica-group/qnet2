import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useEnasarcoParameterFormMeta } from '@/features/enasarco-parameters/use-enasarco-parameter-form-meta'
import { EnasarcoParameterFormBody } from '@/features/enasarco-parameters/enasarco-parameter-form-body'
import type { EnasarcoParameterDetail, EnasarcoParameterFormMode } from '@/features/enasarco-parameters/types'

interface EnasarcoParameterFormProps {
  mode: EnasarcoParameterFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (parameter: EnasarcoParameterDetail) => void
  onCancel: () => void
}

/**
 * Create/edit form of an ENASARCO parameter set. Resolves the resource
 * `ResourcePermissions` first (edit: from the loaded detail; create:
 * `GET /meta/enasarco-parameters`), then hands off to the body.
 */
export function EnasarcoParameterForm(props: EnasarcoParameterFormProps) {
  const { t } = useTranslation()
  const meta = useEnasarcoParameterFormMeta(props.mode)

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
      <EnasarcoParameterFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
