import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { Pencil, Trash2 } from 'lucide-react'
import { useConfirm } from '@/components/confirm-dialog-context'
import { Button } from '@/components/ui/button'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { Skeleton } from '@/components/ui/skeleton'
import { useAbilities } from '@/features/auth/use-abilities'
import { StaffTrainingDetailBody } from '@/features/staff-trainings/staff-training-detail-body'
import { useStaffTrainingActions } from '@/features/staff-trainings/use-staff-training-actions'
import { useStaffTrainingDetail } from '@/features/staff-trainings/use-staff-training-detail'
import type { StaffTraining } from '@/features/staff-trainings/types'

const NOT_FOUND_STATUS = 404
const MANAGE_PERMISSION = 'staff-trainings.manage'

interface StaffTrainingDetailSheetProps {
  /** Training to show; `null` keeps the sheet closed. */
  trainingId: number | null
  onClose: () => void
  onEdit: (training: StaffTraining) => void
  refreshGrid?: () => void
}

interface DetailContentProps extends Omit<StaffTrainingDetailSheetProps, 'trainingId'> {
  trainingId: number
}

function DetailContent({ trainingId, onClose, onEdit, refreshGrid }: DetailContentProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const { can } = useAbilities()
  const canManage = can(MANAGE_PERMISSION)
  const { data: training, isPending, error } = useStaffTrainingDetail(trainingId)
  const actions = useStaffTrainingActions(trainingId, refreshGrid, onClose)

  if (isPending) {
    return <Skeleton className="m-4 h-40" />
  }
  if (!training) {
    const missing = axios.isAxiosError(error) && error.response?.status === NOT_FOUND_STATUS
    return (
      <p role="alert" className="p-4 text-sm text-destructive">
        {t(missing ? 'staffTrainings.detail.notFound' : 'staffTrainings.detail.loadError')}
      </p>
    )
  }

  const confirmDelete = async () => {
    const confirmed = await confirm({
      title: t('staffTrainings.actions.deleteTitle'),
      description: t('staffTrainings.actions.deleteDescription'),
      confirmLabel: t('staffTrainings.actions.delete'),
      cancelLabel: t('staffTrainings.actions.cancelBack'),
      tone: 'destructive',
    })
    if (confirmed) {
      actions.remove.mutate()
    }
  }

  return (
    <div className="flex-1 space-y-4 overflow-y-auto px-4 pb-4">
      {canManage ? (
        <div className="flex flex-wrap gap-2">
          <Button type="button" size="sm" variant="outline" onClick={() => onEdit(training)}>
            <Pencil aria-hidden="true" />
            {t('staffTrainings.actions.edit')}
          </Button>
          <Button
            type="button"
            size="sm"
            variant="outline"
            disabled={actions.remove.isPending}
            onClick={() => void confirmDelete()}
          >
            <Trash2 aria-hidden="true" />
            {t('staffTrainings.actions.delete')}
          </Button>
        </div>
      ) : null}
      <StaffTrainingDetailBody training={training} actions={actions} canManage={canManage} />
    </div>
  )
}

/** Detail Sheet of a training (grid row click or a row of the "Situazione" tab). */
export function StaffTrainingDetailSheet({ trainingId, onClose, onEdit, refreshGrid }: StaffTrainingDetailSheetProps) {
  const { t } = useTranslation()

  return (
    <Sheet open={trainingId !== null} onOpenChange={(open) => (open ? undefined : onClose())}>
      <SheetContent className="gap-0 p-0" storageKey="sheet-width:staff-trainings-detail">
        <SheetHeader>
          <SheetTitle>{t('staffTrainings.detail.title')}</SheetTitle>
          <SheetDescription>{t('staffTrainings.detail.description')}</SheetDescription>
        </SheetHeader>
        {trainingId !== null ? (
          <DetailContent trainingId={trainingId} onClose={onClose} onEdit={onEdit} refreshGrid={refreshGrid} />
        ) : null}
      </SheetContent>
    </Sheet>
  )
}
