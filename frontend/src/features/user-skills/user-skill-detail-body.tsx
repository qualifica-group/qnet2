import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { SkillLevelBadge } from '@/features/skills/skill-level-badge'
import type { UserSkill } from '@/features/user-skills/types'
import { formatDate, formatDateTime } from '@/lib/formatting/date-display'

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="min-w-0">
      <dt className="text-xs text-muted-foreground">{label}</dt>
      <dd className="break-words text-sm">{children}</dd>
    </div>
  )
}

/** Read-only data of a person skill: who, which skill, level, assessment day, who assessed it and the notes. */
export function UserSkillDetailBody({ userSkill }: { userSkill: UserSkill }) {
  const { t } = useTranslation()
  const prefix = 'userSkills.detail'

  return (
    <dl className="grid gap-3 sm:grid-cols-2">
      <Field label={t(`${prefix}.person`)}>{userSkill.user.name}</Field>
      <Field label={t(`${prefix}.skill`)}>{userSkill.skill.name}</Field>
      <Field label={t(`${prefix}.category`)}>{userSkill.skill.category ?? '—'}</Field>
      <Field label={t(`${prefix}.level`)}>
        <SkillLevelBadge level={userSkill.level} />
      </Field>
      <Field label={t(`${prefix}.assessedOn`)}>{formatDate(userSkill.assessed_on)}</Field>
      <Field label={t(`${prefix}.assessedBy`)}>
        {userSkill.assessed_by.name} ({formatDateTime(userSkill.updated_at)})
      </Field>
      {userSkill.notes ? (
        <div className="sm:col-span-2">
          <Field label={t(`${prefix}.notes`)}>
            <span className="whitespace-pre-wrap">{userSkill.notes}</span>
          </Field>
        </div>
      ) : null}
    </dl>
  )
}
