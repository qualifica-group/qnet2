import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { SKILL_LEVELS, isSkillLevel } from '@/features/skills/skill-level'
import { cn } from '@/lib/utils'

/** A four-step meter plus the level number and name: the level never rests on colour alone. */
export function SkillLevelBadge({ level }: { level: unknown }) {
  const { t } = useTranslation()
  const value = typeof level === 'string' ? Number(level) : level

  if (!isSkillLevel(value)) {
    return <span aria-hidden="true">—</span>
  }

  return (
    <Badge variant="secondary" className="gap-1.5 font-normal">
      <span className="flex items-end gap-px" aria-hidden="true">
        {SKILL_LEVELS.map((step) => (
          <span
            key={step}
            className={cn('w-0.5 rounded-sm', step <= value ? 'bg-foreground' : 'bg-border')}
            style={{ height: `${step * 2 + 2}px` }}
          />
        ))}
      </span>
      {value} · {t(`skills.levels.${value}`)}
    </Badge>
  )
}
