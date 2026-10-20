import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { Pencil, Trash2 } from 'lucide-react'
import { useConfirm } from '@/components/confirm-dialog-context'
import { DetailLoading, DetailMonogram } from '@/components/detail/detail-panel'
import { RecordBody } from '@/components/detail/record-body'
import { RecordCanvas, RecordCardHeader } from '@/components/detail/record-panel'
import { Button } from '@/components/ui/button'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetToolbar } from '@/components/ui/sheet'
import { SkillLevelBadge } from '@/features/skills/skill-level-badge'
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
    return <DetailLoading />
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
    <RecordCanvas>
      <RecordBody
        header={
          <RecordCardHeader
            media={<DetailMonogram name={userSkill.user.name} />}
            title={userSkill.skill.name}
            subtitle={userSkill.user.name}
            badges={<SkillLevelBadge level={userSkill.level} />}
            actions={
              userSkill.can_edit ? (
                <div className="flex flex-wrap items-center justify-end gap-1.5">
                  <Button type="button" size="sm" variant="outline" onClick={() => onEdit(userSkill)}>
                    <Pencil aria-hidden="true" />
                    {t('userSkills.actions.edit')}
                  </Button>
                  <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={remove.isPending}
                    onClick={() => void confirmDelete()}
                  >
                    <Trash2 aria-hidden="true" />
                    {t('userSkills.actions.delete')}
                  </Button>
                </div>
              ) : undefined
            }
          />
        }
      >
        <UserSkillDetailBody userSkill={userSkill} />
      </RecordBody>
    </RecordCanvas>
  )
}

/** Detail Sheet of a person skill on the record kit (spec 0287), opened by a grid row or a row of the "Copertura" tab. */
export function UserSkillDetailSheet({ userSkillId, onClose, onEdit, refreshGrid }: UserSkillDetailSheetProps) {
  const { t } = useTranslation()

  return (
    <Sheet open={userSkillId !== null} onOpenChange={(open) => (open ? undefined : onClose())}>
      <SheetContent className="gap-0" showCloseButton={false} storageKey="sheet-width:user-skills-detail">
        <SheetToolbar closeLabel={t('common.close')} />
        <SheetHeader className="sr-only">
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
