import { useTranslation } from 'react-i18next'
import { GraduationCap } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { Form } from '@/components/ui/form'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetToolbar } from '@/components/ui/sheet'
import { StaffTrainingFields } from '@/features/staff-trainings/staff-training-fields'
import { useStaffTrainingForm } from '@/features/staff-trainings/use-staff-training-form'
import type { StaffTraining } from '@/features/staff-trainings/types'

const FORM_ID = 'staff-training-form'

interface StaffTrainingFormProps {
  training?: StaffTraining
  refreshGrid?: () => void
  onSaved: (saved: StaffTraining) => void
  onClose: () => void
}

function StaffTrainingForm({ training, refreshGrid, onSaved, onClose }: StaffTrainingFormProps) {
  const { t } = useTranslation()
  const { form, isSubmitting, submit } = useStaffTrainingForm({ training, refreshGrid, onSaved })

  const title = t(training ? 'staffTrainings.form.editTitle' : 'staffTrainings.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={FORM_ID}
        onSubmit={(event) => void submit(event)}
        title={title}
        subtitle={t('staffTrainings.form.description')}
        media={<DetailMonogram name={title} icon={<GraduationCap />} />}
        isSubmitting={isSubmitting}
        serverError={null}
        onCancel={onClose}
        labels={{
          save: t('staffTrainings.form.save'),
          saving: t('staffTrainings.form.saving'),
          cancel: t('staffTrainings.form.cancel'),
        }}
      >
        <StaffTrainingFields
          selectedUser={training ? { id: training.user.id, label: training.user.name } : undefined}
          selectedCourse={training ? { id: training.course.id, label: training.course.name } : undefined}
        />
      </RecordCreateLayout>
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
      <SheetContent className="gap-0" showCloseButton={false} storageKey="sheet-width:staff-trainings-form">
        <SheetToolbar closeLabel={t('common.close')} />
        <SheetHeader className="sr-only">
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
