import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus } from 'lucide-react'
import { PageHeader } from '@/components/page-header'
import { Button } from '@/components/ui/button'
import { FilterTabs } from '@/components/ui/filter-tabs'
import { Can } from '@/features/auth/can'
import { useAbilities } from '@/features/auth/use-abilities'
import type { TableViewHandle } from '@/features/table/table-view'
import { StaffTrainingCompliance } from '@/features/staff-trainings/staff-training-compliance'
import { StaffTrainingDetailSheet } from '@/features/staff-trainings/staff-training-detail-sheet'
import { StaffTrainingRegister } from '@/features/staff-trainings/staff-training-register'
import { StaffTrainingSheet } from '@/features/staff-trainings/staff-training-sheet'
import { STAFF_TRAINING_VIEWS, type StaffTraining, type StaffTrainingView } from '@/features/staff-trainings/types'

/**
 * Training page (spec 0284): the "Registro" grid and the "Situazione" table, the detail Sheet and
 * the register/edit Sheet. Opened by `staff-trainings.viewAny` or by having subordinates (spec 0309); "Registra formazione" needs `manage`.
 */
export default function StaffTrainingsPage() {
  const { t } = useTranslation()
  const { can, hasSubordinates, isLoading } = useAbilities()
  const tableRef = useRef<TableViewHandle>(null)
  const [view, setView] = useState<StaffTrainingView>('register')
  const [openedId, setOpenedId] = useState<number | null>(null)
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<StaffTraining | undefined>(undefined)

  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])
  const closeDetail = useCallback(() => setOpenedId(null), [])

  const openCreate = () => {
    setEditing(undefined)
    setFormOpen(true)
  }
  const openEdit = (training: StaffTraining) => {
    closeDetail()
    setEditing(training)
    setFormOpen(true)
  }

  if (isLoading) return null
  if (!can('staff-trainings.viewAny') && !hasSubordinates) {
    return <p className="text-sm text-muted-foreground">{t('staffTrainings.forbidden')}</p>
  }

  return (
    <>
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader
          actions={
            <div className="flex flex-wrap items-center justify-end gap-2">
              <FilterTabs<StaffTrainingView>
                aria-label={t('staffTrainings.page.viewsLabel')}
                value={view}
                options={STAFF_TRAINING_VIEWS.map((value) => ({ value, label: t(`staffTrainings.page.views.${value}`) }))}
                onValueChange={setView}
              />
              <Can permission="staff-trainings.manage">
                <Button type="button" onClick={openCreate}>
                  <Plus aria-hidden="true" />
                  {t('staffTrainings.page.register')}
                </Button>
              </Can>
            </div>
          }
        />
        {view === 'register' ? (
          <StaffTrainingRegister tableRef={tableRef} onOpen={setOpenedId} />
        ) : (
          <StaffTrainingCompliance onOpen={setOpenedId} />
        )}
      </div>

      <StaffTrainingDetailSheet
        trainingId={openedId}
        onClose={closeDetail}
        onEdit={openEdit}
        refreshGrid={refreshGrid}
      />
      <StaffTrainingSheet
        open={formOpen}
        onOpenChange={setFormOpen}
        training={editing}
        refreshGrid={refreshGrid}
        onSaved={() => setFormOpen(false)}
      />
    </>
  )
}
