import { useTranslation } from 'react-i18next'
import { SkillLevelBadge } from '@/features/skills/skill-level-badge'
import { CoverageStatusBadge } from '@/features/user-skills/coverage-status'
import type { CoverageRow } from '@/features/user-skills/types'

interface UserSkillCoverageTableProps {
  rows: CoverageRow[]
  /** Opens the person skill behind a row; only rows with a `user_skill_id` are clickable. */
  onOpen: (userSkillId: number) => void
}

const CELL_CLASS = 'px-3 py-2 text-left align-middle'

/**
 * Person x required skill table. Wide content scrolls inside its own container, never the page
 * (375 px); the skill cell becomes a button when a person skill backs the row.
 */
export function UserSkillCoverageTable({ rows, onOpen }: UserSkillCoverageTableProps) {
  const { t } = useTranslation()
  const prefix = 'userSkills.coverage.columns'

  return (
    <div className="overflow-x-auto rounded-lg border border-border bg-card">
      <table className="w-full min-w-[36rem] text-xs">
        <caption className="sr-only">{t('userSkills.coverage.caption')}</caption>
        <thead className="bg-surface">
          <tr>
            <th scope="col" className={CELL_CLASS}>{t(`${prefix}.person`)}</th>
            <th scope="col" className={CELL_CLASS}>{t(`${prefix}.skill`)}</th>
            <th scope="col" className={CELL_CLASS}>{t(`${prefix}.requiredLevel`)}</th>
            <th scope="col" className={CELL_CLASS}>{t(`${prefix}.level`)}</th>
            <th scope="col" className={CELL_CLASS}>{t(`${prefix}.status`)}</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={`${row.user.id}-${row.skill.id}`} className="border-t border-border">
              <td className={CELL_CLASS}>{row.user.name}</td>
              <td className={CELL_CLASS}>
                {row.user_skill_id === null ? (
                  <span>{row.skill.name}</span>
                ) : (
                  <button
                    type="button"
                    className="rounded-sm text-left font-medium underline-offset-2 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    aria-label={t('userSkills.coverage.openSkill', { person: row.user.name, skill: row.skill.name })}
                    onClick={() => onOpen(row.user_skill_id as number)}
                  >
                    {row.skill.name}
                  </button>
                )}
              </td>
              <td className={CELL_CLASS}>
                <SkillLevelBadge level={row.required_level} />
              </td>
              <td className={CELL_CLASS}>{row.level === null ? '—' : <SkillLevelBadge level={row.level} />}</td>
              <td className={CELL_CLASS}>
                <CoverageStatusBadge status={row.status} />
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
