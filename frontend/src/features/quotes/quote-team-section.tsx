import { useTranslation } from 'react-i18next'
import { Info, Users } from 'lucide-react'
import { useWatch, type Control } from 'react-hook-form'
import { toManagerSlotLabels } from '@/lib/utils'
import { FormSection } from '@/components/form-section'
import { ManagerSlotsField } from '@/components/form/manager-slots-field'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { MetaField } from '@/features/authorization/MetaField'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { useQuoteManagerLabels } from '@/features/quotes/use-quote-manager-labels'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'
import type { QuoteDetail } from '@/features/quotes/types'
import type { ForSelectItem } from '@/features/for-select/types'

interface QuoteTeamSectionProps {
  control: Control<QuoteFormValues>
  /** The loaded quote in edit mode, for the slots' hydrated labels and the sync banner (D-7). */
  original: QuoteDetail | null
  /**
   * The Supervisore's hydrated ref, ALREADY resolved by the caller (spec 0097
   * rev-2 D-8): the ref inherited from the Opportunita' wins over the loaded
   * quote's own, so the trigger relabels the moment the field auto-fills. That
   * rule lives in `QuoteFormBody`'s `roleRef` and is never duplicated here.
   */
  supervisor: RelationFieldRef | null
  /** The form's shared relation-picker strings (same object every other section receives). */
  labels: {
    placeholder: string
    emptyLabel: string
    errorLabel: string
    clearLabel: string
    retryLabel: string
  }
  className?: string
}

/** Stable empty selection while there is nothing to hydrate (create mode, or the pivot not loaded yet). */
const EMPTY_SELECTED_ITEMS: ForSelectItem[] = []

/**
 * The offer's own team: the Supervisore plus the shared, ordered "G.A. n"
 * manager slots (spec 0087 D-1/D-11, `ManagerSlotsField`, reused verbatim).
 * Mirrors `OpportunityTeamSection`.
 *
 * The Supervisore joined this section with spec 0097 rev-2 D-8 (it used to be
 * a plain field of `QuoteFormBody`'s identity block): a pure move — same
 * permission key, same payload, same inherited hydration, which the caller
 * resolves and hands over as `supervisor`.
 *
 * "G.A. n" relabeling (D-8): resolved LIVE from the offer's own current
 * REVENUE rows (`useQuoteManagerLabels`), falling back to the linked
 * Opportunity's categories while there are none yet.
 *
 * The sync banner (D-7) only ever reflects a LOADED quote's own
 * `managers_synchronized` — there is nothing to sync yet on a still-unsaved
 * create.
 */
export function QuoteTeamSection({ control, original, supervisor, labels, className }: QuoteTeamSectionProps) {
  const { t } = useTranslation()
  const opportunityId = useWatch({ control, name: 'opportunity_id' })
  const offerLines = useWatch({ control, name: 'offer_lines' })
  const managerLabels = useQuoteManagerLabels(offerLines ?? [], opportunityId)
  const slotLabels = toManagerSlotLabels(managerLabels)

  const selectedItems = original
    ? original.managers?.map((manager) => ({ id: manager.id, label: manager.name })) ?? EMPTY_SELECTED_ITEMS
    : EMPTY_SELECTED_ITEMS

  return (
    <FormSection
      icon={Users}
      title={t('quotes.form.sections.team.title')}
      description={t('quotes.form.sections.team.description')}
      className={className}
    >
      {original?.managers_synchronized ? (
        <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
          <Info aria-hidden="true" className="size-3.5 shrink-0" />
          {t('quotes.form.managersSyncHint')}
        </p>
      ) : null}

      <RelationSelectField
        control={control}
        name="supervisor_id"
        metaKey="supervisor_id"
        label={t('quotes.form.supervisor')}
        resource={USERS_FOR_SELECT_RESOURCE}
        searchPlaceholder={t('quotes.form.supervisorSearch')}
        selected={supervisor}
        showAvatar
        {...labels}
      />

      <MetaField
        control={control}
        name="manager_slots"
        metaKey="manager_slots"
        label={t('quotes.form.managers')}
      >
        {({ field, disabled }) => (
          <ManagerSlotsField
            value={field.value}
            onChange={field.onChange}
            selectedItems={selectedItems}
            disabled={disabled}
            labels={slotLabels}
          />
        )}
      </MetaField>
    </FormSection>
  )
}
