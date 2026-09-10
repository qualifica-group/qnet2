import { useTranslation } from 'react-i18next'
import { Info, Users } from 'lucide-react'
import { useWatch, type Control } from 'react-hook-form'
import { toManagerSlotLabels } from '@/lib/utils'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { FormSection } from '@/components/form-section'
import { ManagerSlotsField } from '@/components/form/manager-slots-field'
import { useQuickCreateAction } from '@/components/form/use-quick-create-action'
import { FIELD_GRID_CLASS } from '@/components/record-form/layout'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import type { ForSelectItem } from '@/features/for-select/types'
import type { RequestCreateFormValues } from '@/features/request-management/request-create-schema'
import { useActiveCategoryManagerLabels } from '@/features/request-management/use-active-category-manager-labels'
import { useRequestManagerLabels } from '@/features/request-management/use-request-manager-labels'
import { useRequestManagementCategoryPreference } from '@/features/request-management/use-request-management-category-preference'

/** Hoisted: a create form has no persisted pivot to hydrate the slot triggers from. */
const EMPTY_SELECTED_ITEMS: ForSelectItem[] = []

/** Same reason, for the labels: a fresh `{}` per render would rebuild the slots' label map. */
const EMPTY_MANAGER_LABELS = {}

interface RequestCreateTeamSectionProps {
  control: Control<RequestCreateFormValues>
  /**
   * Whether the actor may assign the team (`request-management.assignOperator`)
   * and set the Sede (`operational-sites.viewAny`), resolved once by the form
   * that owns them. Only the slots are supervisory: the Supervisore is a plain
   * attribution field, gated wholesale by `request-management.create` like
   * Fonte and Segnalatore.
   */
  canAssignOperator: boolean
  canPickSite: boolean
  /**
   * The Sede half of the link the FORM owns (`useRequestSiteOperatorLink`,
   * spec 0097 rev-2 D-7): the Sede itself is edited in "Attribuzione", one
   * section away, so this one only consumes the scoping it produces.
   */
  siteId: number | null
  slotParamsFor: (position: number) => Record<string, string | number> | undefined
  onSlotItemChange: (position: number, item: ForSelectItem | null) => void
}

/**
 * The create form's team section, twin of the work panel's
 * (`request-team-section.tsx`) as every other section of these two screens is:
 * the Offerta's Supervisore (spec 0097 rev-2 D-9) plus its ordered "G.A. n"
 * slots, available already at creation.
 *
 * The pickers are the plain `AsyncPaginatedSelect`, not the panel's
 * meta-driven `RelationSelectField`: this create-only form has no
 * `permissions` envelope to gate fields against — creation is gated wholesale
 * by `request-management.create` server-side — so it mirrors
 * `RequestCreateAttributionSection` instead. The team block alone is
 * supervisory and keeps its own ability gate (user directive 2026-08-03), the
 * same the store endpoint enforces.
 *
 * G.A. relabeling (spec 0080, revised by the user directive 2026-09-08):
 * resolved from what the FORM carries — the offer rows' product categories,
 * or the "categoria prodotto" rows when there is no offer row yet
 * (`useRequestManagerLabels`, the work panel's own rule) — so picking a
 * category renames the slots on the spot. The module's active category tab
 * only stands in until the form has a category of its own: with no persisted
 * request there is nothing else to resolve from, and it is the same univocal
 * context the table's own column header relies on.
 *
 * The scoping hint sits with the SLOTS, not with the Sede that produces it: it
 * describes which users the operator slot lists. Never shown without the Sede
 * field itself, which the actor may not be allowed to see.
 */
export function RequestCreateTeamSection({
  control,
  canAssignOperator,
  canPickSite,
  siteId,
  slotParamsFor,
  onSlotItemChange,
}: RequestCreateTeamSectionProps) {
  const { t } = useTranslation()
  const { categoryId: activeCategoryId } = useRequestManagementCategoryPreference()
  const { data: activeCategoryManagerLabels } = useActiveCategoryManagerLabels(activeCategoryId)
  const offerLines = useWatch({ control, name: 'offer_lines' })
  const productLines = useWatch({ control, name: 'product_lines' })
  const liveLabels = useRequestManagerLabels(offerLines, productLines)
  const slotLabels = toManagerSlotLabels(liveLabels ?? activeCategoryManagerLabels ?? EMPTY_MANAGER_LABELS)
  const supervisorQuickCreate = useQuickCreateAction(USERS_FOR_SELECT_RESOURCE)

  const selectLabels = {
    placeholder: t('requestManagement.form.create.team.selectPlaceholder'),
    empty: t('requestManagement.form.create.team.selectEmpty'),
    error: t('requestManagement.form.create.team.selectError'),
    clearLabel: t('common.clear'),
    retry: t('common.retry'),
  }

  return (
    <FormSection
      icon={Users}
      title={t('requestManagement.form.create.team.title')}
      description={t('requestManagement.form.create.team.description')}
      className="min-w-0"
    >
      <div className={FIELD_GRID_CLASS}>
        <FormField
          control={control}
          name="supervisor_id"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('requestManagement.form.create.team.supervisor')}</FormLabel>
              <FormControl>
                <AsyncPaginatedSelect
                  resource={USERS_FOR_SELECT_RESOURCE}
                  value={field.value}
                  onChange={field.onChange}
                  selectedItem={supervisorQuickCreate.selectedItemFor(field.value)}
                  action={supervisorQuickCreate.renderAction((ref) => field.onChange(ref.id))}
                  showAvatar
                  labels={{
                    ...selectLabels,
                    searchPlaceholder: t('requestManagement.form.create.team.supervisorSearch'),
                    triggerLabel: t('requestManagement.form.create.team.supervisor'),
                  }}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
      </div>

      {canAssignOperator && (
        <FormField
          control={control}
          name="manager_slots"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('requestManagement.form.create.team.managers')}</FormLabel>
              <FormControl>
                <ManagerSlotsField
                  value={field.value}
                  onChange={field.onChange}
                  selectedItems={EMPTY_SELECTED_ITEMS}
                  labels={slotLabels}
                  paramsFor={slotParamsFor}
                  onItemChange={onSlotItemChange}
                />
              </FormControl>
              {canPickSite && siteId != null && (
                <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                  <Info className="size-3.5 shrink-0" aria-hidden="true" />
                  {t('requestManagement.form.create.team.operatorFilteredBySite')}
                </p>
              )}
              <FormMessage />
            </FormItem>
          )}
        />
      )}
    </FormSection>
  )
}
