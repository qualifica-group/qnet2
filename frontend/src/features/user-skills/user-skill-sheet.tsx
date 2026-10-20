import { useTranslation } from 'react-i18next'
import { Gauge } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { Form } from '@/components/ui/form'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetToolbar } from '@/components/ui/sheet'
import { UserSkillFields } from '@/features/user-skills/user-skill-fields'
import { useUserSkillForm } from '@/features/user-skills/use-user-skill-form'
import type { UserSkill } from '@/features/user-skills/types'

const FORM_ID = 'user-skill-form'

interface UserSkillFormProps {
  userSkill?: UserSkill
  refreshGrid?: () => void
  onSaved: (saved: UserSkill) => void
  onClose: () => void
}

function UserSkillForm({ userSkill, refreshGrid, onSaved, onClose }: UserSkillFormProps) {
  const { t } = useTranslation()
  const { form, isSubmitting, submit } = useUserSkillForm({ userSkill, refreshGrid, onSaved })

  const title = t(userSkill ? 'userSkills.form.editTitle' : 'userSkills.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={FORM_ID}
        onSubmit={(event) => void submit(event)}
        title={title}
        subtitle={t('userSkills.form.description')}
        media={<DetailMonogram name={title} icon={<Gauge />} />}
        isSubmitting={isSubmitting}
        serverError={null}
        onCancel={onClose}
        labels={{
          save: t('userSkills.form.save'),
          saving: t('userSkills.form.saving'),
          cancel: t('userSkills.form.cancel'),
        }}
      >
        <UserSkillFields
          selectedUser={userSkill ? { id: userSkill.user.id, label: userSkill.user.name } : undefined}
          selectedSkill={userSkill ? { id: userSkill.skill.id, label: userSkill.skill.name } : undefined}
        />
      </RecordCreateLayout>
    </Form>
  )
}

interface UserSkillSheetProps extends Omit<UserSkillFormProps, 'onClose'> {
  open: boolean
  onOpenChange: (open: boolean) => void
}

/** Right-hand Sheet assigning a skill to a person or editing a saved one (spec 0286). */
export function UserSkillSheet({ open, onOpenChange, userSkill, refreshGrid, onSaved }: UserSkillSheetProps) {
  const { t } = useTranslation()

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="gap-0" showCloseButton={false} storageKey="sheet-width:user-skills-form">
        <SheetToolbar closeLabel={t('common.close')} />
        <SheetHeader className="sr-only">
          <SheetTitle>{t(userSkill ? 'userSkills.form.editTitle' : 'userSkills.form.createTitle')}</SheetTitle>
          <SheetDescription>{t('userSkills.form.description')}</SheetDescription>
        </SheetHeader>
        {/* Keyed by userSkill so each opening starts from fresh form state */}
        <UserSkillForm
          key={userSkill?.id ?? 'new'}
          userSkill={userSkill}
          refreshGrid={refreshGrid}
          onSaved={onSaved}
          onClose={() => onOpenChange(false)}
        />
      </SheetContent>
    </Sheet>
  )
}
