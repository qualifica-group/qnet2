import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ChevronDown, TriangleAlert } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { UserAvatar } from '@/components/user-avatar'
import type { AssignmentScopeBalancedGroup } from '@/features/assignment/types'
import type { UseBalancedOperatorSelectionResult } from '@/features/assignment/use-balanced-operator-selection'

export interface BalancedOperatorsPickerProps {
  /** `undefined` while the scope is still resolving or on failure. */
  groups: AssignmentScopeBalancedGroup[] | undefined
  unassignableCount: number | undefined
  isResolving: boolean
  /**
   * True when the selection-scope request failed. Distinct from "resolved,
   * zero groups": `groups` is `undefined` in both the loading AND the error
   * case, so without this flag the picker would fall back to `groups ?? []`
   * and show the misleading "no operator available" empty state instead of
   * naming the actual failure. Checked before `isResolving` (a stale
   * `isResolving: true` never survives an error) has no priority meaning here
   * — the two are mutually exclusive in practice.
   */
  isError: boolean
  selection: UseBalancedOperatorSelectionResult
  disabled: boolean
}

/**
 * "Smistamento equo" operator list (spec 0168 AC-011/AC-012/AC-013/AC-015):
 * one Collapsible group per Sede with a tri-state header checkbox and a
 * selected/total badge, one checkbox row per operator with its current load,
 * and the two warnings (a group left at zero selected, records with no Sede
 * at all). Pure presentation: every bit of selection logic lives in
 * `useBalancedOperatorSelection`.
 */
export function BalancedOperatorsPicker({
  groups,
  unassignableCount,
  isResolving,
  isError,
  selection,
  disabled,
}: BalancedOperatorsPickerProps) {
  const { t } = useTranslation()

  if (isResolving) {
    return (
      <p className="rounded-lg border bg-card px-3 py-2 text-xs text-muted-foreground" role="status">
        {t('leads.assign.balanced.loading')}
      </p>
    )
  }

  if (isError) {
    return (
      <p
        className="flex items-start gap-1.5 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-xs text-destructive"
        role="alert"
      >
        <TriangleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
        {t('leads.assign.balanced.error')}
      </p>
    )
  }

  // Reached only once RESOLVED (never during loading/error, both handled
  // above): an empty array here is a real answer, not an unknown state.
  const resolvedGroups = groups ?? []

  return (
    <div className="space-y-1.5">
      {resolvedGroups.length > 0 ? (
        <div className="flex max-h-56 flex-col gap-1.5 overflow-auto rounded-lg border bg-card p-1.5">
          {resolvedGroups.map((group) => (
            <BalancedGroupRow
              key={group.operational_site_id}
              group={group}
              selection={selection}
              disabled={disabled}
            />
          ))}
        </div>
      ) : (
        <p className="rounded-lg border bg-card px-3 py-2 text-xs text-muted-foreground">
          {t('leads.assign.balanced.empty')}
        </p>
      )}
      {unassignableCount && unassignableCount > 0 ? (
        <p className="flex items-start gap-1.5 text-[11px] leading-snug text-muted-foreground">
          <TriangleAlert
            className="mt-0.5 size-3 shrink-0 text-amber-600 dark:text-amber-400"
            aria-hidden="true"
          />
          {t('leads.assign.balanced.globalWarning', { count: unassignableCount })}
        </p>
      ) : null}
    </div>
  )
}

interface BalancedGroupRowProps {
  group: AssignmentScopeBalancedGroup
  selection: UseBalancedOperatorSelectionResult
  disabled: boolean
}

/** One Sede group: header (tri-state checkbox, label, counters) plus its operator rows. */
function BalancedGroupRow({ group, selection, disabled }: BalancedGroupRowProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(true)
  const selectedCount = group.operators.filter((operator) =>
    selection.isOperatorSelected(group.operational_site_id, operator.id),
  ).length

  return (
    <Collapsible open={open} onOpenChange={setOpen}>
      <div className="rounded-md border bg-background">
        <div className="flex items-center gap-2 px-2 py-1.5">
          <Checkbox
            checked={selection.groupState(group)}
            disabled={disabled}
            aria-label={`${group.operational_site_label} — ${t('leads.assign.balanced.selectGroup')}`}
            onCheckedChange={(checked) => selection.toggleGroup(group, checked === true)}
          />
          <CollapsibleTrigger className="flex flex-1 items-center gap-1.5 text-left text-xs font-medium outline-none focus-visible:ring-[2px] focus-visible:ring-ring/50 [&[data-state=open]>svg]:rotate-180">
            <ChevronDown
              className="size-3.5 shrink-0 text-muted-foreground transition-transform"
              aria-hidden="true"
            />
            <span className="truncate">{group.operational_site_label}</span>
            <Badge variant="secondary" className="text-[10px]">
              {t('leads.assign.balanced.selectionCount', {
                selected: selectedCount,
                total: group.operators.length,
              })}
            </Badge>
            <span className="ml-auto shrink-0 text-[11px] font-normal text-muted-foreground">
              {t('leads.assign.balanced.recordCount', { count: group.record_count })}
            </span>
          </CollapsibleTrigger>
        </div>
        {selectedCount === 0 && (
          <p className="flex items-start gap-1.5 border-t px-2 py-1.5 text-[11px] leading-snug text-muted-foreground">
            <TriangleAlert
              className="mt-0.5 size-3 shrink-0 text-amber-600 dark:text-amber-400"
              aria-hidden="true"
            />
            {t('leads.assign.balanced.groupWarning', { count: group.record_count })}
          </p>
        )}
        <CollapsibleContent>
          <div className="flex flex-col gap-0.5 border-t px-1.5 py-1.5">
            {group.operators.map((operator) => (
              <label
                key={operator.id}
                className="flex items-center gap-2 rounded px-1 py-1 text-xs hover:bg-muted/40"
              >
                <Checkbox
                  checked={selection.isOperatorSelected(group.operational_site_id, operator.id)}
                  disabled={disabled}
                  aria-label={t('leads.assign.balanced.operatorAriaLabel', {
                    operator: operator.label,
                    site: group.operational_site_label,
                  })}
                  onCheckedChange={(checked) =>
                    selection.toggleOperator(group.operational_site_id, operator.id, checked === true)
                  }
                />
                <UserAvatar name={operator.label} src={operator.avatar_url} size="xs" />
                <span className="min-w-0 flex-1 truncate">{operator.label}</span>
                <span className="shrink-0 text-[11px] text-muted-foreground">
                  {t('leads.assign.balanced.load', { count: operator.load })}
                </span>
              </label>
            ))}
          </div>
        </CollapsibleContent>
      </div>
    </Collapsible>
  )
}
