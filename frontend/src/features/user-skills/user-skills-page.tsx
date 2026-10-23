import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus } from 'lucide-react'
import { PageHeader } from '@/components/page-header'
import { Button } from '@/components/ui/button'
import { FilterTabs } from '@/components/ui/filter-tabs'
import { useAbilities } from '@/features/auth/use-abilities'
import type { TableViewHandle } from '@/features/table/table-view'
import { UserSkillCoverage } from '@/features/user-skills/user-skill-coverage'
import { UserSkillDetailSheet } from '@/features/user-skills/user-skill-detail-sheet'
import { UserSkillSheet } from '@/features/user-skills/user-skill-sheet'
import { UserSkillsGrid } from '@/features/user-skills/user-skills-grid'
import { USER_SKILL_VIEWS, type UserSkill, type UserSkillView } from '@/features/user-skills/types'

/**
 * Skills page (spec 0286): the "Persone" grid and the "Copertura" table, the detail Sheet and the
 * assign/edit Sheet. Opened by `user-skills.viewAny` or by having subordinates; "Assegna competenza" needs `manage` (spec 0309).
 */
export default function UserSkillsPage() {
  const { t } = useTranslation()
  const { can, hasSubordinates, isLoading } = useAbilities()
  const tableRef = useRef<TableViewHandle>(null)
  const [view, setView] = useState<UserSkillView>('people')
  const [openedId, setOpenedId] = useState<number | null>(null)
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<UserSkill | undefined>(undefined)
  const canWrite = can('user-skills.manage')

  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])
  const closeDetail = useCallback(() => setOpenedId(null), [])

  const openCreate = () => {
    setEditing(undefined)
    setFormOpen(true)
  }
  const openEdit = (userSkill: UserSkill) => {
    closeDetail()
    setEditing(userSkill)
    setFormOpen(true)
  }

  if (isLoading) return null
  if (!can('user-skills.viewAny') && !hasSubordinates) {
    return <p className="text-sm text-muted-foreground">{t('userSkills.forbidden')}</p>
  }

  return (
    <>
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader
          actions={
            <div className="flex flex-wrap items-center justify-end gap-2">
              <FilterTabs<UserSkillView>
                aria-label={t('userSkills.page.viewsLabel')}
                value={view}
                options={USER_SKILL_VIEWS.map((value) => ({ value, label: t(`userSkills.page.views.${value}`) }))}
                onValueChange={setView}
              />
              {canWrite ? (
                <Button type="button" onClick={openCreate}>
                  <Plus aria-hidden="true" />
                  {t('userSkills.page.assign')}
                </Button>
              ) : null}
            </div>
          }
        />
        {view === 'people' ? (
          <UserSkillsGrid tableRef={tableRef} onOpen={setOpenedId} />
        ) : (
          <UserSkillCoverage onOpen={setOpenedId} />
        )}
      </div>

      <UserSkillDetailSheet userSkillId={openedId} onClose={closeDetail} onEdit={openEdit} refreshGrid={refreshGrid} />
      <UserSkillSheet
        open={formOpen}
        onOpenChange={setFormOpen}
        userSkill={editing}
        refreshGrid={refreshGrid}
        onSaved={() => setFormOpen(false)}
      />
    </>
  )
}
