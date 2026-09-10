import { useTranslation } from 'react-i18next'
import { MapPin, User } from 'lucide-react'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { Label } from '@/components/ui/label'
import { cn } from '@/lib/utils'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'

/** Precompiled Sede seed (spec 0048 AC-031), when the selection shares one. */
export interface AssignOperatorsDialogSite {
  id: number
  label: string
}

/** Crisp, compact styling shared by the Sede/Operatore selects. */
const SELECT_CLASS = 'h-8 bg-card text-xs shadow-sm transition-colors hover:border-ring/50'

/**
 * Query params of the Operatore picker: the Sede scope (picked by the user in
 * the transfer flow, derived from the records elsewhere) plus, when the call
 * site resolved one, the competence filter (spec 0110 AC-041).
 * `undefined` site = scope not resolved yet: the field is disabled anyway, and
 * an unscoped list would be the wrong one to preload (spec 0113 AC-034).
 * `null` site = no Sede scope (mixed selection): competence alone filters.
 */
function buildOperatorParams(
  siteId: number | null | undefined,
  competenceCategoryIds: number[] | undefined,
): Record<string, number | number[]> | undefined {
  if (siteId === undefined) {
    return undefined
  }
  const params: Record<string, number | number[]> = {}
  if (siteId !== null) {
    params.operational_site_id = siteId
  }
  if (competenceCategoryIds !== undefined) {
    params.competence_category_ids = competenceCategoryIds
  }
  return Object.keys(params).length === 0 ? undefined : params
}

/**
 * Which sentence sits under the Operatore picker. With a user-picked Sede
 * (transfer flow) the wording is the original one; with a derived Sede the
 * user cannot act on the scope, so the sentence explains what the list is
 * scoped to — or why it is not usable yet (spec 0113).
 */
function operatorHintKey(
  showSiteField: boolean,
  siteId: number | null | undefined,
  isResolvingCompetence: boolean,
  hasCompetenceFilter: boolean,
): string {
  if (isResolvingCompetence) {
    return 'leads.assign.operator.resolvingHint'
  }
  if (showSiteField) {
    if (siteId === null || siteId === undefined) {
      return 'leads.assign.operator.disabledHint'
    }
    return hasCompetenceFilter
      ? 'leads.assign.operator.competenceHint'
      : 'leads.assign.operator.hint'
  }
  if (siteId === undefined) {
    return 'leads.assign.operator.derivedSiteUnknownHint'
  }
  if (siteId === null) {
    return 'leads.assign.operator.derivedSiteMixedHint'
  }
  return hasCompetenceFilter
    ? 'leads.assign.operator.derivedSiteCompetenceHint'
    : 'leads.assign.operator.derivedSiteHint'
}

export interface AssignOperatorsPickersProps {
  /** Renders the Sede select: transfer flow only (spec 0113 D-2). */
  showSiteField: boolean
  showOperatorField: boolean
  /** User-picked Sede; meaningful only with `showSiteField`. */
  siteId: number | null
  onSiteChange: (siteId: number | null) => void
  defaultSite?: AssignOperatorsDialogSite | null
  /** Sede derived from the records, used when the field is hidden. */
  operatorSiteId?: number | null
  operatorId: number | null
  onOperatorChange: (operatorId: number | null) => void
  competenceCategoryIds?: number[]
  isResolvingCompetence: boolean
  isSubmitting: boolean
}

/**
 * Step 2 of the assign popup: the Sede select (opt-in) and the Operatore
 * select scoped by Sede + competence. Split out of `assign-operators-dialog`
 * to keep both files within the size thresholds; it owns no state.
 */
export function AssignOperatorsPickers({
  showSiteField,
  showOperatorField,
  siteId,
  onSiteChange,
  defaultSite,
  operatorSiteId,
  operatorId,
  onOperatorChange,
  competenceCategoryIds,
  isResolvingCompetence,
  isSubmitting,
}: AssignOperatorsPickersProps) {
  const { t } = useTranslation()
  // The picker scope: the user's own pick when the field is shown, the Sede
  // the call site derived from the records otherwise (spec 0113).
  const scopeSiteId = showSiteField ? siteId : operatorSiteId

  return (
    <div
      className={cn(
        'grid gap-3 rounded-xl border bg-gradient-to-b from-card to-muted/20 p-3 motion-safe:animate-in motion-safe:fade-in-0 motion-safe:slide-in-from-top-1',
        showSiteField && showOperatorField && 'sm:grid-cols-2',
      )}
    >
      {showSiteField && (
        <div className="space-y-1.5">
          <Label
            htmlFor="assign-operators-site"
            className="flex items-center gap-1.5 text-xs font-medium"
          >
            <MapPin className="size-3.5 text-primary" aria-hidden="true" />
            {t('leads.assign.site.label')}
          </Label>
          <AsyncPaginatedSelect
            id="assign-operators-site"
            resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE}
            value={siteId}
            onChange={onSiteChange}
            selectedItem={defaultSite ? { id: defaultSite.id, label: defaultSite.label } : null}
            disabled={isSubmitting}
            className={SELECT_CLASS}
            labels={{
              placeholder: t('leads.assign.site.placeholder'),
              searchPlaceholder: t('leads.assign.site.searchPlaceholder'),
              empty: t('leads.assign.site.empty'),
              error: t('leads.assign.site.selectError'),
              clearLabel: t('leads.assign.site.selectClear'),
              triggerLabel: t('leads.assign.site.label'),
              retry: t('leads.assign.site.retry'),
            }}
          />
        </div>
      )}

      {showOperatorField && (
        <div className="space-y-1.5">
          <Label
            htmlFor="assign-operators-operator"
            className="flex items-center gap-1.5 text-xs font-medium"
          >
            <User className="size-3.5 text-sky-600 dark:text-sky-400" aria-hidden="true" />
            {t('leads.assign.operator.label')}
          </Label>
          <AsyncPaginatedSelect
            id="assign-operators-operator"
            resource={USERS_FOR_SELECT_RESOURCE}
            value={operatorId}
            onChange={onOperatorChange}
            showAvatar
            disabled={
              isSubmitting ||
              isResolvingCompetence ||
              scopeSiteId === undefined ||
              (showSiteField && scopeSiteId === null)
            }
            params={buildOperatorParams(scopeSiteId, competenceCategoryIds)}
            className={SELECT_CLASS}
            labels={{
              placeholder: t('leads.assign.operator.placeholder'),
              searchPlaceholder: t('leads.assign.operator.searchPlaceholder'),
              empty:
                competenceCategoryIds === undefined
                  ? t('leads.assign.operator.empty')
                  : t('leads.assign.operator.emptyCompetent'),
              error: t('leads.assign.operator.selectError'),
              clearLabel: t('leads.assign.operator.selectClear'),
              triggerLabel: t('leads.assign.operator.label'),
              retry: t('leads.assign.operator.retry'),
            }}
          />
          <p className="text-[11px] text-muted-foreground">
            {t(
              operatorHintKey(
                showSiteField,
                scopeSiteId,
                isResolvingCompetence,
                competenceCategoryIds !== undefined,
              ),
            )}
          </p>
        </div>
      )}
    </div>
  )
}
