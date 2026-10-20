import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { Pencil, Trash2 } from 'lucide-react'
import { useConfirm } from '@/components/confirm-dialog-context'
import { DetailLoading, DetailMonogram } from '@/components/detail/detail-panel'
import { RecordBody } from '@/components/detail/record-body'
import { RecordCanvas, RecordCardHeader } from '@/components/detail/record-panel'
import { Button } from '@/components/ui/button'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetToolbar } from '@/components/ui/sheet'
import { useAbilities } from '@/features/auth/use-abilities'
import { StaffTrainingDetailBody } from '@/features/staff-trainings/staff-training-detail-body'
import { StaffTrainingOutcomeBadge } from '@/features/staff-trainings/staff-training-outcome-badge'
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
    return <DetailLoading />
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
    <RecordCanvas>
      <RecordBody
        header={
          <RecordCardHeader
            media={<DetailMonogram name={training.user.name} />}
            title={training.course.name}
            subtitle={training.user.name}
            badges={<StaffTrainingOutcomeBadge outcome={training.outcome} />}
            actions={
              canManage ? (
                <div className="flex flex-wrap items-center justify-end gap-1.5">
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
              ) : undefined
            }
          />
        }
      >
        <StaffTrainingDetailBody training={training} actions={actions} canManage={canManage} />
      </RecordBody>
    </RecordCanvas>
  )
}

/** Detail Sheet of a training on the record kit (spec 0287), opened by a grid row or a row of the "Situazione" tab. */
export function StaffTrainingDetailSheet({ trainingId, onClose, onEdit, refreshGrid }: StaffTrainingDetailSheetProps) {
  const { t } = useTranslation()

  return (
    <Sheet open={trainingId !== null} onOpenChange={(open) => (open ? undefined : onClose())}>
      <SheetContent className="gap-0" showCloseButton={false} storageKey="sheet-width:staff-trainings-detail">
        <SheetToolbar closeLabel={t('common.close')} />
        <SheetHeader className="sr-only">
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
