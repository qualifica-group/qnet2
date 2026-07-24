import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { ArrowUpRight } from 'lucide-react'
import i18n from '@/i18n'
import { cn } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import { UserAvatar } from '@/components/user-avatar'
import { badgeColorClass } from '@/features/table/cell-renderers'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { RewardChip } from '@/features/rewards/reward-chip'
import type { RewardDetailItem } from '@/features/rewards/types'

/**
 * Field labels the caller passes already translated (this component takes no
 * i18n dependency — same "props-only labels" idiom as `FormSection`), so it
 * stays reusable across the `rewarded-referents` master/detail and any other
 * future consumer without owning a translation namespace.
 */
export interface RewardCardLabels {
  assignedAt: string
  sourceRemoved: string
  client: string
  categories: string
  commercialStatus: string
  workflowStatus: string
  operator: string
}

interface RewardCardProps {
  reward: RewardDetailItem
  labels: RewardCardLabels
  className?: string
  /**
   * Opens the reward's origin. When provided, the origin name renders as an
   * action button (the caller decides modal vs page via the module open mode)
   * instead of a plain router `Link`. Omitted, the card stays a self-contained
   * `Link` — keeping this component free of any module/open-mode dependency.
   */
  onOpenSource?: () => void
}

/** Shared visual language for the origin affordance, whether it is a link or a button. */
const SOURCE_LINK_CLASS =
  'inline-flex min-w-0 items-center gap-1 text-left text-sm font-medium text-primary hover:underline'

/** A small uppercase caption above its value; omitted entirely when there is nothing to show. */
function Field({ term, children, className }: { term: string; children: ReactNode; className?: string }) {
  return (
    <div className={cn('flex min-w-0 flex-1 basis-32 flex-col gap-0.5', className)}>
      <span className="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">{term}</span>
      {children}
    </div>
  )
}

/** Same visual language as `StatusBadgeCell` (dot + soft badge), without the AG Grid cell coupling. */
function StatusBadge({ name, color }: { name: string; color: string | null }) {
  const dotClass = swatchClassFor(color)
  return (
    <Badge variant="secondary" className={cn('w-fit gap-1.5', badgeColorClass(color))}>
      {dotClass ? <span className={cn('size-1.5 shrink-0 rounded-full', dotClass)} aria-hidden="true" /> : null}
      <span className="truncate">{name}</span>
    </Badge>
  )
}

/** Localized, date-only (no time) formatting for `assigned_at`. */
function formatAssignedAt(value: string): string {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return value
  }
  return new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium' }).format(date)
}

/**
 * One reward's card, rendered inside the `rewarded-referents` master/detail
 * expanded row: type, origin, live commercial context (D-1: exactly two
 * status badges), operator and notes. Every field is independently nullable
 * (context itself can be null when the origin was deleted) — each section
 * renders only when it has something to show, never an empty row.
 */
export function RewardCard({ reward, labels, className, onOpenSource }: RewardCardProps) {
  const context = reward.context
  const categories = context?.product_categories ?? []
  const categoriesLabel = categories.map((category) => category.name).join(', ')
  const hasStatus = Boolean(context?.opportunity_status ?? context?.workflow_status)
  const hasMeta = Boolean(context?.registry) || categories.length > 0 || Boolean(context?.operator)

  return (
    <Card className={cn('gap-3 py-3', className)}>
      <CardContent className="flex flex-col gap-3 px-3">
        <div className="flex flex-wrap items-start justify-between gap-2">
          <RewardChip rewardType={reward.reward_type} />
          <span className="text-xs tabular-nums text-muted-foreground">
            <span className="sr-only">{labels.assignedAt}: </span>
            {formatAssignedAt(reward.assigned_at)}
          </span>
        </div>

        {reward.source ? (
          onOpenSource ? (
            <button type="button" onClick={onOpenSource} className={SOURCE_LINK_CLASS}>
              <span className="truncate">{reward.source.name}</span>
              <ArrowUpRight aria-hidden="true" className="size-3.5 shrink-0" />
            </button>
          ) : (
            <Link to={reward.source.path} className={SOURCE_LINK_CLASS}>
              <span className="truncate">{reward.source.name}</span>
              <ArrowUpRight aria-hidden="true" className="size-3.5 shrink-0" />
            </Link>
          )
        ) : (
          <span className="text-sm text-muted-foreground">{labels.sourceRemoved}</span>
        )}

        {hasStatus ? (
          <div className="flex flex-wrap gap-3">
            {context?.opportunity_status ? (
              <Field term={labels.commercialStatus}>
                <StatusBadge name={context.opportunity_status.name} color={context.opportunity_status.color} />
              </Field>
            ) : null}
            {context?.workflow_status ? (
              <Field term={labels.workflowStatus}>
                <StatusBadge name={context.workflow_status.name} color={context.workflow_status.color} />
              </Field>
            ) : null}
          </div>
        ) : null}

        {hasMeta ? (
          <div className="flex flex-wrap gap-3 text-xs">
            {context?.registry ? (
              <Field term={labels.client}>
                <span className="truncate" title={context.registry.name}>
                  {context.registry.name}
                </span>
              </Field>
            ) : null}
            {categories.length > 0 ? (
              <Field term={labels.categories}>
                <span className="truncate" title={categoriesLabel}>
                  {categoriesLabel}
                </span>
              </Field>
            ) : null}
            {context?.operator ? (
              <Field term={labels.operator}>
                <span className="flex items-center gap-1.5">
                  <UserAvatar name={context.operator.name} src={context.operator.avatar_url} size="sm" />
                  <span className="truncate">{context.operator.name}</span>
                </span>
              </Field>
            ) : null}
          </div>
        ) : null}

        {reward.notes ? <p className="line-clamp-3 text-xs text-muted-foreground">{reward.notes}</p> : null}
      </CardContent>
    </Card>
  )
}
