/**
 * One row of the team tree (spec 0122 D-10), q-net parity
 * (`team-pulse/team-member-row.tsx`): tree guide lines, avatar, name,
 * business functions (crown when `is_manager`), roles, job description
 * ("Mansione"), operating site, coverage bar/pill, expand chevron for
 * subordinates. No Regione (out of scope, D-10).
 */

import { Briefcase, Building2, ChevronDown, ChevronRight, Crown, Shield } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { UserAvatar } from '@/components/user-avatar'
import { cn } from '@/lib/utils'
import { resolveTeamCoverageColorToken } from '@/features/time-entries/time-entry-constants'
import type { TeamTreeNode } from '@/features/time-entries/team/team-tree'

interface TeamMemberRowProps {
  node: TeamTreeNode
  depth: number
  isClickable: boolean
  isCurrentUser: boolean
  onSelect: () => void
  isLastChild: boolean
  parentTrails: boolean[]
  hasChildren: boolean
  isExpanded: boolean
  onToggle: () => void
}

const INDENT_PER_LEVEL = 24
const BASE_PADDING = 16
const TRAIL_OFFSET = 10
const TOGGLE_WIDTH = 24

const COVERAGE_BAR_COLOR_CLASS: Record<ReturnType<typeof resolveTeamCoverageColorToken>, string> = {
  emerald: 'bg-emerald-500',
  amber: 'bg-amber-500',
  red: 'bg-red-500',
  slate: 'bg-slate-300',
}

function TreeGuides({
  depth,
  isLastChild,
  parentTrails,
}: {
  depth: number
  isLastChild: boolean
  parentTrails: boolean[]
}) {
  if (depth === 0) {
    return null
  }
  return (
    <>
      {parentTrails.map((showTrail, index) => (
        <span
          key={index}
          aria-hidden="true"
          className={cn('pointer-events-none absolute top-0 bottom-0 w-px', showTrail && 'bg-border/60')}
          style={{ left: `${BASE_PADDING + index * INDENT_PER_LEVEL + TRAIL_OFFSET}px` }}
        />
      ))}
      <span
        aria-hidden="true"
        className={cn(
          'pointer-events-none absolute left-0 w-px bg-border/60',
          isLastChild ? 'top-0 h-1/2' : 'top-0 bottom-0',
        )}
        style={{ left: `${BASE_PADDING + (depth - 1) * INDENT_PER_LEVEL + TRAIL_OFFSET}px` }}
      />
      <span
        aria-hidden="true"
        className="pointer-events-none absolute top-1/2 h-px bg-border/60"
        style={{
          left: `${BASE_PADDING + (depth - 1) * INDENT_PER_LEVEL + TRAIL_OFFSET}px`,
          width: `${INDENT_PER_LEVEL - TRAIL_OFFSET}px`,
        }}
      />
    </>
  )
}

function CoverageBar({ percentage, label }: { percentage: number; label: string }) {
  const { t } = useTranslation()
  const barWidth = Math.min(100, Math.max(0, percentage))
  return (
    <div className="hidden w-32 shrink-0 sm:block">
      <div className="flex items-center justify-between text-[11px] font-medium text-muted-foreground">
        <span>{t('timeEntries.pulse.coverage')}</span>
        <span className="text-foreground">{label}</span>
      </div>
      <div className="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-muted">
        <div
          className={cn('h-full rounded-full transition-all', COVERAGE_BAR_COLOR_CLASS[resolveTeamCoverageColorToken(percentage)])}
          style={{ width: `${barWidth}%` }}
        />
      </div>
    </div>
  )
}

function CoverageChip({ percentage, label }: { percentage: number; label: string }) {
  const { t } = useTranslation()
  return (
    <div className="mt-1.5 flex flex-wrap items-center gap-2 sm:hidden">
      <span className="inline-flex items-center gap-1 rounded-full border border-border/60 bg-background px-2 py-0.5 text-[11px] font-medium text-foreground">
        <span
          aria-hidden="true"
          className={cn('size-1.5 rounded-full', COVERAGE_BAR_COLOR_CLASS[resolveTeamCoverageColorToken(percentage)])}
        />
        {t('timeEntries.pulse.coverage')} {label}
      </span>
    </div>
  )
}

export function TeamMemberRow({
  node,
  depth,
  isClickable,
  isCurrentUser,
  onSelect,
  isLastChild,
  parentTrails,
  hasChildren,
  isExpanded,
  onToggle,
}: TeamMemberRowProps) {
  const { t } = useTranslation()
  const { member } = node
  const coveragePercentage = member.coverage.percentage
  const coverageLabel = `${coveragePercentage}%`
  const roleLabel = member.roles.join(', ')
  const disabledTitle = isCurrentUser ? t('timeEntries.team.thisIsYou') : undefined
  const toggleLeft = BASE_PADDING + depth * INDENT_PER_LEVEL

  return (
    <div className="relative">
      <button
        type="button"
        disabled={!isClickable}
        onClick={isClickable ? onSelect : undefined}
        title={disabledTitle}
        className={cn(
          'group relative flex w-full items-center gap-3 py-3 pr-4 text-left transition-colors',
          isClickable ? 'cursor-pointer hover:bg-muted/40' : 'cursor-not-allowed opacity-60',
        )}
        style={{ paddingLeft: `${toggleLeft + TOGGLE_WIDTH}px` }}
      >
        <TreeGuides depth={depth} isLastChild={isLastChild} parentTrails={parentTrails} />

        <UserAvatar name={member.user.name} src={member.user.avatar_url} className="ring-2 ring-background" />

        <div className="min-w-0 flex-1">
          <div className="flex min-w-0 items-center gap-2">
            <p className="shrink-0 truncate text-sm font-semibold text-foreground">{member.user.name}</p>
            {member.business_functions.length > 0 ? (
              <span className="flex min-w-0 flex-1 items-center gap-1 truncate text-xs text-muted-foreground">
                {member.business_functions.map((businessFunction, index) => (
                  <span key={businessFunction.id} className="inline-flex shrink-0 items-center gap-0.5">
                    {index > 0 ? <span className="text-muted-foreground/60">·</span> : null}
                    {businessFunction.is_manager ? (
                      <Crown className="size-3 shrink-0 text-amber-500" aria-label={t('timeEntries.team.responsible')} />
                    ) : null}
                    <span className="truncate">{businessFunction.name}</span>
                  </span>
                ))}
              </span>
            ) : null}
          </div>
          <p className="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
            <span className="flex min-w-0 items-center gap-1">
              <Tooltip>
                <TooltipTrigger asChild>
                  <Shield className="size-3 shrink-0 text-muted-foreground" aria-label={t('timeEntries.team.role')} />
                </TooltipTrigger>
                <TooltipContent side="top">{t('timeEntries.team.role')}</TooltipContent>
              </Tooltip>
              <span className="truncate" title={roleLabel || t('timeEntries.team.noRole')}>
                {roleLabel || t('timeEntries.team.noRole')}
              </span>
            </span>
            {member.job_description ? (
              <span className="flex min-w-0 items-center gap-1">
                <Tooltip>
                  <TooltipTrigger asChild>
                    <Briefcase
                      className="size-3 shrink-0 text-muted-foreground"
                      aria-label={t('timeEntries.team.jobDescription')}
                    />
                  </TooltipTrigger>
                  <TooltipContent side="top">{t('timeEntries.team.jobDescription')}</TooltipContent>
                </Tooltip>
                <span className="truncate" title={member.job_description}>
                  {member.job_description}
                </span>
              </span>
            ) : null}
          </p>
          {member.operational_site?.label ? (
            <p className="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
              <span className="flex min-w-0 items-center gap-1">
                <Tooltip>
                  <TooltipTrigger asChild>
                    <Building2
                      className="size-3 shrink-0 text-muted-foreground"
                      aria-label={t('timeEntries.team.operatingSite')}
                    />
                  </TooltipTrigger>
                  <TooltipContent side="top">{t('timeEntries.team.operatingSite')}</TooltipContent>
                </Tooltip>
                <span className="truncate" title={member.operational_site.label}>
                  {member.operational_site.label}
                </span>
              </span>
            </p>
          ) : null}
          <CoverageChip percentage={coveragePercentage} label={coverageLabel} />
        </div>

        <CoverageBar percentage={coveragePercentage} label={coverageLabel} />

        {isClickable ? (
          <ChevronRight className="size-4 shrink-0 text-muted-foreground transition-transform" aria-hidden="true" />
        ) : null}
      </button>

      {hasChildren ? (
        <button
          type="button"
          onClick={onToggle}
          aria-expanded={isExpanded}
          aria-label={isExpanded ? t('timeEntries.dayCard.collapse') : t('timeEntries.dayCard.expand')}
          title={isExpanded ? t('timeEntries.dayCard.collapse') : t('timeEntries.dayCard.expand')}
          className="absolute top-1/2 z-10 flex size-5 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
          style={{ left: `${toggleLeft}px` }}
        >
          {isExpanded ? <ChevronDown className="size-4" /> : <ChevronRight className="size-4" />}
        </button>
      ) : null}
    </div>
  )
}
