import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { SkillsTable } from '@/features/skills/skills-table'

/**
 * Skills catalogue page (spec 0286). Light composition only: gates access with
 * `skills.view` and mounts the thin adapter over the generic table.
 */
export default function SkillsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="skills.view"
      fallback={<p className="text-sm text-muted-foreground">{t('skills.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <SkillsTable />
      </div>
    </Can>
  )
}
