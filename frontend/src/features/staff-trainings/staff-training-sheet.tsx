import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { StaffTrainingFields } from '@/features/staff-trainings/staff-training-fields'
import { useStaffTrainingForm } from '@/features/staff-trainings/use-staff-training-form'
import type { StaffTraining } from '@/features/staff-trainings/types'

interface StaffTrainingFormProps {
  training?: StaffTraining
  refreshGrid?: () => void
  onSaved: (saved: StaffTraining) => void
  onClose: () => void
}

function StaffTrainingForm({ training, refreshGrid, onSaved, onClose }: StaffTrainingFormProps) {
  const { t } = useTranslation()
  const { form, isSubmitting, submit } = useStaffTrainingForm({ training, refreshGrid, onSaved })

  return (
    <Form {...form}>
      <form onSubmit={(event) => void submit(event)} noValidate className="flex min-h-0 flex-1 flex-col">
        <div className="flex-1 space-y-3 overflow-y-auto px-4 pb-4">
          <StaffTrainingFields
            selectedUser={training ? { id: training.user.id, label: training.user.name } : undefined}
            selectedCourse={training ? { id: training.course.id, label: training.course.name } : undefined}
          />
        </div>
        <SheetFooter className="border-t bg-surface sm:flex-row sm:justify-end">
          <Button type="button" variant="outline" onClick={onClose}>
            {t('staffTrainings.form.cancel')}
          </Button>
          <Button type="submit" disabled={isSubmitting}>
            {t(isSubmitting ? 'staffTrainings.form.saving' : 'staffTrainings.form.save')}
          </Button>
        </SheetFooter>
      </form>
    </Form>
  )
}

interface StaffTrainingSheetProps extends Omit<StaffTrainingFormProps, 'onClose'> {
  open: boolean
  onOpenChange: (open: boolean) => void
}

/** Right-hand Sheet registering a training or editing a saved one (spec 0284). */
export function StaffTrainingSheet({ open, onOpenChange, training, refreshGrid, onSaved }: StaffTrainingSheetProps) {
  const { t } = useTranslation()

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="gap-0 p-0" storageKey="sheet-width:staff-trainings-form">
        <SheetHeader>
          <SheetTitle>{t(training ? 'staffTrainings.form.editTitle' : 'staffTrainings.form.createTitle')}</SheetTitle>
          <SheetDescription>{t('staffTrainings.form.description')}</SheetDescription>
        </SheetHeader>
        {/* Keyed by training so each opening starts from fresh form state */}
        <StaffTrainingForm
          key={training?.id ?? 'new'}
          training={training}
          refreshGrid={refreshGrid}
          onSaved={onSaved}
          onClose={() => onOpenChange(false)}
        />
      </SheetContent>
    </Sheet>
  )
}
