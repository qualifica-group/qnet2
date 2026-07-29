import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { CommissionConfigurationFormBody } from './commission-configuration-form-body'
import type {
  CommissionConfigurationDetail,
  CommissionConfigurationFormMode,
} from './types'

interface Props {
  mode: CommissionConfigurationFormMode
  onSuccess: (configuration: CommissionConfigurationDetail) => void
  onCancel: () => void
}

export function CommissionConfigurationForm(props: Props) {
  const { t } = useTranslation()
  const meta = useResourceMeta('commission-configurations', props.mode.type === 'create')
  const permissions =
    props.mode.type === 'edit' ? props.mode.configuration.permissions : meta.data?.permissions

  if (!permissions && !meta.isError) {
    return (
      <div className="grid gap-4 p-4" aria-hidden="true">
        {Array.from({ length: 8 }, (_, index) => (
          <Skeleton key={index} className="h-9 w-full" />
        ))}
      </div>
    )
  }
  if (!permissions) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p role="alert" className="text-sm text-destructive">
          {t('authorization.loadError')}
        </p>
        <Button variant="outline" size="sm" onClick={() => meta.refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }
  return (
    <ResourcePermissionsProvider permissions={permissions}>
      <CommissionConfigurationFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
