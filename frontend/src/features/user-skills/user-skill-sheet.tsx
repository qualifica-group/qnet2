import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { UserSkillFields } from '@/features/user-skills/user-skill-fields'
import { useUserSkillForm } from '@/features/user-skills/use-user-skill-form'
import type { UserSkill } from '@/features/user-skills/types'

interface UserSkillFormProps {
  userSkill?: UserSkill
  refreshGrid?: () => void
  onSaved: (saved: UserSkill) => void
  onClose: () => void
}

function UserSkillForm({ userSkill, refreshGrid, onSaved, onClose }: UserSkillFormProps) {
  const { t } = useTranslation()
  const { form, isSubmitting, submit } = useUserSkillForm({ userSkill, refreshGrid, onSaved })

  return (
    <Form {...form}>
      <form onSubmit={(event) => void submit(event)} noValidate className="flex min-h-0 flex-1 flex-col">
        <div className="flex-1 space-y-3 overflow-y-auto px-4 pb-4">
          <UserSkillFields
            selectedUser={userSkill ? { id: userSkill.user.id, label: userSkill.user.name } : undefined}
            selectedSkill={userSkill ? { id: userSkill.skill.id, label: userSkill.skill.name } : undefined}
          />
        </div>
        <SheetFooter className="border-t bg-surface sm:flex-row sm:justify-end">
          <Button type="button" variant="outline" onClick={onClose}>
            {t('userSkills.form.cancel')}
          </Button>
          <Button type="submit" disabled={isSubmitting}>
            {t(isSubmitting ? 'userSkills.form.saving' : 'userSkills.form.save')}
          </Button>
        </SheetFooter>
      </form>
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
      <SheetContent className="gap-0 p-0" storageKey="sheet-width:user-skills-form">
        <SheetHeader>
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
