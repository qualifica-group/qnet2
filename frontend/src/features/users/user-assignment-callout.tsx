import { useTranslation } from 'react-i18next'
import { CircleCheck, TriangleAlert } from 'lucide-react'
import { cn } from '@/lib/utils'
import type { AssignmentSummary } from '@/features/users/user-assignment'

/**
 * The "will this person ever be matched to an offer?" band, shared by the user
 * form (live, in the sticky side column while the configuration is being
 * typed) and the read-only scheda (only when something is missing — a green
 * band on a record that is already fine is noise).
 *
 * Two tones, two hues: green means the configuration lets records reach the
 * person, amber means it does not. Amber and not `destructive`: nothing is
 * broken and nothing is refused — the save goes through either way, exactly
 * like `IdentityDuplicateWarning`, whose chrome this reuses. `role="status"`
 * for the same reason: assistive tech announces it without interrupting.
 *
 * The counts are rendered as labelled chips rather than a sentence: a sentence
 * would need a plural rule per number in two languages to say no more than
 * "2 · 3".
 */

const OK_CLASS =
  'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'

const WARNING_CLASS =
  'border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-400'

interface UserAssignmentCalloutProps {
  summary: AssignmentSummary
  className?: string
}

export function UserAssignmentCallout({ summary, className }: UserAssignmentCalloutProps) {
  const { t } = useTranslation()
  const Icon = summary.assignable ? CircleCheck : TriangleAlert

  return (
    <div
      role="status"
      className={cn(
        'flex flex-col gap-2 rounded-lg border p-3 text-xs',
        summary.assignable ? OK_CLASS : WARNING_CLASS,
        className,
      )}
    >
      <span className="flex items-center gap-1.5 font-semibold">
        <Icon className="size-3.5 shrink-0" aria-hidden="true" />
        {t(summary.assignable ? 'users.assignment.assignable' : 'users.assignment.notAssignable')}
      </span>

      {summary.assignable ? (
        <div className="flex flex-wrap gap-1.5">
          <AssignmentCountChip
            label={t('users.assignment.chips.competence')}
            value={summary.competenceCount}
          />
          <AssignmentCountChip
            label={t('users.assignment.chips.physicalSite')}
            value={summary.physicalSiteCount}
          />
          <AssignmentCountChip
            label={t('users.assignment.chips.remoteSites')}
            value={summary.remoteSiteCount}
          />
        </div>
      ) : (
        summary.blockers.map((blocker) => (
          // Deliberately not a <ul>: the scheda counts the competence rows by
          // `listitem` role, and a bulleted blocker would join that count.
          <p key={blocker} className="text-pretty">
            {t(`users.assignment.blockers.${blocker}`)}
          </p>
        ))
      )}
    </div>
  )
}

/** One `label value` chip; inherits the band's tone instead of carrying its own. */
function AssignmentCountChip({ label, value }: { label: string; value: number }) {
  return (
    <span className="inline-flex items-center gap-1 rounded-md border border-current/25 px-1.5 py-0.5">
      {label}
      <span className="font-semibold tabular-nums">{value}</span>
    </span>
  )
}
