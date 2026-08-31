import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { ArrowUpRight } from 'lucide-react'
import { formatDate } from '@/lib/formatting/date-display'
import { cn } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import { UserAvatar } from '@/components/user-avatar'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { badgeColorClass } from '@/features/table/cell-renderers'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { OpportunityStatusBadge } from '@/features/opportunities/opportunity-status-badge'
import { RewardChip } from '@/features/rewards/reward-chip'
import type { RewardDetailItem, RewardSourceRef } from '@/features/rewards/types'

/**
 * For-select resource segment behind the inline status picker (spec 0060
 * D-7: `/reward-statuses/for-select` returns only active statuses, ordered).
 * The `reward-statuses` module itself belongs to another feature and is
 * intentionally never imported here — only its resource name is needed.
 */
const REWARD_STATUS_RESOURCE = 'reward-statuses'

/** Morph alias of the Offerta origin — the one that captions `context.status` differently. */
const QUOTE_SOURCE_TYPE = 'quote'

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
  /**
   * Caption of `context.status` for an OPPORTUNITA' origin: the status
   * computed from its own quotes (spec 0082).
   */
  commercialStatus: string
  /**
   * Caption of the SAME `context.status` for an OFFERTA origin (user
   * directive 2026-08-31): there the value is the PARENT opportunity's
   * computed status, so calling it "stato commerciale" on an offer card would
   * name the wrong record.
   */
  opportunityStatus: string
  /** Caption of `context.workflow_status` — the Offerta's OWN working state. */
  workflowStatus: string
  operator: string
  /**
   * Caption of each linked record, keyed by its morph alias
   * (`opportunity`/`quote`) — the origin AND every cross-reference use it, so
   * a card that carries both reads unambiguously. An alias absent from the map
   * captions itself rather than borrowing an unrelated label.
   */
  sourceTypes: Record<string, string>
  /** Term label for the reward's own status field, and the select's trigger aria-label. */
  status: string
  statusPlaceholder: string
  statusSearchPlaceholder: string
  statusEmpty: string
  statusError: string
  statusClearLabel: string
  statusRetry: string
}

interface RewardCardProps {
  reward: RewardDetailItem
  labels: RewardCardLabels
  className?: string
  /**
   * Opens one of the reward's linked records — its origin or a
   * cross-reference. When provided, each name renders as an action button (the
   * caller decides modal vs page via the module open mode) instead of a plain
   * router `Link`. Omitted, the card stays a self-contained `Link` — keeping
   * this component free of any module/open-mode dependency.
   */
  onOpenRecord?: (record: RewardSourceRef) => void
  /**
   * Whether the current user may change the reward's own status inline
   * (spec 0060 D-1/D-8: `rewarded-referents.update` permission, checked by
   * the caller). Defaults to `false` (readonly badge) so an omitted prop
   * never accidentally exposes the edit affordance.
   */
  canEditStatus?: boolean
  /** Fired with the newly picked, active status id once the user selects it. */
  onStatusChange?: (rewardStatusId: number) => void
  /** True while this card's own status PATCH is in flight — disables the select. */
  isStatusUpdating?: boolean
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

/**
 * One linked record: an action button when the caller knows how to open it,
 * a plain router `Link` when it only has a path, plain text when it has
 * neither (an alias with no module page).
 */
function RecordLink({
  record,
  onOpen,
}: {
  record: RewardSourceRef
  onOpen?: (record: RewardSourceRef) => void
}) {
  const label = (
    <>
      <span className="truncate">{record.name}</span>
      <ArrowUpRight aria-hidden="true" className="size-3.5 shrink-0" />
    </>
  )

  if (onOpen) {
    return (
      <button type="button" onClick={() => onOpen(record)} className={SOURCE_LINK_CLASS}>
        {label}
      </button>
    )
  }

  if (record.path === null) {
    return <span className="truncate text-sm font-medium">{record.name}</span>
  }

  return (
    <Link to={record.path} className={SOURCE_LINK_CLASS}>
      {label}
    </Link>
  )
}

interface RewardStatusFieldProps {
  reward: RewardDetailItem
  labels: RewardCardLabels
  canEditStatus: boolean
  onStatusChange?: (rewardStatusId: number) => void
  isStatusUpdating: boolean
}

/**
 * The reward's own persisted status (spec 0060 D-1, AC-029/AC-030): editable
 * inline via the generic for-select when the caller grants it, a readonly
 * badge otherwise — never both, and never a select for a value the user is
 * not allowed to change. The select's trigger is tinted with the current
 * status color (`badgeColorClass`) so it keeps reading as a status pill even
 * while it doubles as the edit control.
 */
function RewardStatusField({
  reward,
  labels,
  canEditStatus,
  onStatusChange,
  isStatusUpdating,
}: RewardStatusFieldProps) {
  const status = reward.reward_status

  if (!canEditStatus || !onStatusChange) {
    return status ? <StatusBadge name={status.name} color={status.color} /> : null
  }

  return (
    <AsyncPaginatedSelect
      resource={REWARD_STATUS_RESOURCE}
      value={status?.id ?? null}
      onChange={(next) => {
        if (next !== null) {
          onStatusChange(next)
        }
      }}
      selectedItem={status ? { id: status.id, label: status.name } : null}
      disabled={isStatusUpdating}
      className={cn('h-8 w-auto text-xs', badgeColorClass(status?.color ?? null))}
      labels={{
        placeholder: labels.statusPlaceholder,
        searchPlaceholder: labels.statusSearchPlaceholder,
        empty: labels.statusEmpty,
        error: labels.statusError,
        clearLabel: labels.statusClearLabel,
        triggerLabel: labels.status,
        retry: labels.statusRetry,
      }}
    />
  )
}

/**
 * One reward's card, rendered inside the `rewarded-referents` master/detail
 * expanded row: type, origin, live commercial context (D-1: exactly two
 * status badges), operator and notes. Every field is independently nullable
 * (context itself can be null when the origin was deleted) — each section
 * renders only when it has something to show, never an empty row.
 */
export function RewardCard({
  reward,
  labels,
  className,
  onOpenRecord,
  canEditStatus = false,
  onStatusChange,
  isStatusUpdating = false,
}: RewardCardProps) {
  const context = reward.context
  const categories = context?.product_categories ?? []
  const categoriesLabel = categories.map((category) => category.name).join(', ')
  const hasComputedStatus = (context?.status?.entries.length ?? 0) > 0
  const hasStatus = hasComputedStatus || Boolean(context?.workflow_status)
  const hasMeta = Boolean(context?.registry) || categories.length > 0 || Boolean(context?.operator)
  const hasOwnStatus = canEditStatus || Boolean(reward.reward_status)
  // The same `context.status` reads as a different thing depending on the
  // origin: the Opportunita's own computed status, or — on an Offerta card —
  // that of the opportunity it belongs to.
  const computedStatusLabel =
    reward.source?.type === QUOTE_SOURCE_TYPE ? labels.opportunityStatus : labels.commercialStatus

  return (
    <Card className={cn('gap-3 py-3', className)}>
      <CardContent className="flex flex-col gap-3 px-3">
        <div className="flex flex-wrap items-start justify-between gap-2">
          <RewardChip rewardType={reward.reward_type} />
          <span className="text-xs tabular-nums text-muted-foreground">
            <span className="sr-only">{labels.assignedAt}: </span>
            {formatDate(reward.assigned_at)}
          </span>
        </div>

        {hasOwnStatus ? (
          <Field term={labels.status}>
            <RewardStatusField
              reward={reward}
              labels={labels}
              canEditStatus={canEditStatus}
              onStatusChange={onStatusChange}
              isStatusUpdating={isStatusUpdating}
            />
          </Field>
        ) : null}

        {reward.source ? (
          <div className="flex flex-wrap gap-3">
            {[reward.source, ...(reward.related ?? [])].map((record) => (
              <Field
                key={`${record.type}:${record.id}`}
                term={labels.sourceTypes[record.type] ?? record.type}
              >
                <RecordLink record={record} onOpen={onOpenRecord} />
              </Field>
            ))}
          </div>
        ) : (
          <span className="text-sm text-muted-foreground">{labels.sourceRemoved}</span>
        )}

        {hasStatus ? (
          <div className="flex flex-wrap gap-3">
            {hasComputedStatus ? (
              <Field term={computedStatusLabel}>
                <OpportunityStatusBadge summary={context?.status} />
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
