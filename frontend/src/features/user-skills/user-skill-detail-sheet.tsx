import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { Pencil, Trash2 } from 'lucide-react'
import { useConfirm } from '@/components/confirm-dialog-context'
import { Button } from '@/components/ui/button'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { Skeleton } from '@/components/ui/skeleton'
import { UserSkillDetailBody } from '@/features/user-skills/user-skill-detail-body'
import { useUserSkillDetail } from '@/features/user-skills/use-user-skill-detail'
import { useUserSkillRemove } from '@/features/user-skills/use-user-skill-remove'
import type { UserSkill } from '@/features/user-skills/types'

const NOT_FOUND_STATUS = 404

interface UserSkillDetailSheetProps {
  /** Person skill to show; `null` keeps the sheet closed. */
  userSkillId: number | null
  onClose: () => void
  onEdit: (userSkill: UserSkill) => void
  refreshGrid?: () => void
}

interface DetailContentProps extends Omit<UserSkillDetailSheetProps, 'userSkillId'> {
  userSkillId: number
}

function DetailContent({ userSkillId, onClose, onEdit, refreshGrid }: DetailContentProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const { data: userSkill, isPending, error } = useUserSkillDetail(userSkillId)
  const remove = useUserSkillRemove(userSkillId, refreshGrid, onClose)

  if (isPending) {
    return <Skeleton className="m-4 h-40" />
  }
  if (!userSkill) {
    const missing = axios.isAxiosError(error) && error.response?.status === NOT_FOUND_STATUS
    return (
      <p role="alert" className="p-4 text-sm text-destructive">
        {t(missing ? 'userSkills.detail.notFound' : 'userSkills.detail.loadError')}
      </p>
    )
  }

  const confirmDelete = async () => {
    const confirmed = await confirm({
      title: t('userSkills.actions.deleteTitle'),
      description: t('userSkills.actions.deleteDescription'),
      confirmLabel: t('userSkills.actions.delete'),
      cancelLabel: t('userSkills.actions.cancelBack'),
      tone: 'destructive',
    })
    if (confirmed) {
      remove.mutate()
    }
  }

  return (
    <div className="flex-1 space-y-4 overflow-y-auto px-4 pb-4">
      {userSkill.can_edit ? (
        <div className="flex flex-wrap gap-2">
          <Button type="button" size="sm" variant="outline" onClick={() => onEdit(userSkill)}>
            <Pencil aria-hidden="true" />
            {t('userSkills.actions.edit')}
          </Button>
          <Button type="button" size="sm" variant="outline" disabled={remove.isPending} onClick={() => void confirmDelete()}>
            <Trash2 aria-hidden="true" />
            {t('userSkills.actions.delete')}
          </Button>
        </div>
      ) : null}
      <UserSkillDetailBody userSkill={userSkill} />
    </div>
  )
}

/** Detail Sheet of a person skill (grid row click or a row of the "Copertura" tab). */
export function UserSkillDetailSheet({ userSkillId, onClose, onEdit, refreshGrid }: UserSkillDetailSheetProps) {
  const { t } = useTranslation()

  return (
    <Sheet open={userSkillId !== null} onOpenChange={(open) => (open ? undefined : onClose())}>
      <SheetContent className="gap-0 p-0" storageKey="sheet-width:user-skills-detail">
        <SheetHeader>
          <SheetTitle>{t('userSkills.detail.title')}</SheetTitle>
          <SheetDescription>{t('userSkills.detail.description')}</SheetDescription>
        </SheetHeader>
        {userSkillId !== null ? (
          <DetailContent userSkillId={userSkillId} onClose={onClose} onEdit={onEdit} refreshGrid={refreshGrid} />
        ) : null}
      </SheetContent>
    </Sheet>
  )
}
