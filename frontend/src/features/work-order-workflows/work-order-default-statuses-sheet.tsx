import { useTranslation } from 'react-i18next'
import { useDefaultStatuses } from '@/features/work-order-workflows/use-default-statuses'
import { useWorkOrderStatusGroups } from '@/features/work-order-workflows/use-work-order-status-groups'
import { DefaultStatusesSheet } from '@/features/workflow-statuses/default-statuses-sheet'

interface WorkOrderDefaultStatusesSheetProps {
  open: boolean
  onOpenChange: (open: boolean) => void
}

/** The GLOBAL work order default status set: the shared sheet fed by `GET`/`PUT /work-order-workflows/default-statuses`. */
export function WorkOrderDefaultStatusesSheet({ open, onOpenChange }: WorkOrderDefaultStatusesSheetProps) {
  const { t } = useTranslation()
  const groups = useWorkOrderStatusGroups()
  const state = useDefaultStatuses({
    enabled: open,
    labels: {
      saved: t('workOrderWorkflows.defaultStatuses.saved'),
      forbidden: t('workOrderWorkflows.defaultStatuses.forbidden'),
      genericError: t('workOrderWorkflows.defaultStatuses.genericError'),
      nameRequired: t('workOrderWorkflows.form.statuses.nameRequired'),
    },
  })

  return (
    <DefaultStatusesSheet
      open={open}
      onOpenChange={onOpenChange}
      state={state}
      groups={groups}
      showProgressPercentage
      storageKey="sheet-width:work-order-workflows-default-statuses"
      labels={{
        title: t('workOrderWorkflows.defaultStatuses.title'),
        subtitle: t('workOrderWorkflows.defaultStatuses.subtitle'),
        loadError: t('workOrderWorkflows.defaultStatuses.loadError'),
        cancel: t('workOrderWorkflows.form.cancel'),
        save: t('workOrderWorkflows.form.save'),
        saving: t('workOrderWorkflows.form.saving'),
      }}
    />
  )
}
