import { useTranslation } from 'react-i18next'
import { Info, Users } from 'lucide-react'
import { useWatch, type Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { ManagerSlotsField } from '@/components/form/manager-slots-field'
import { MetaField } from '@/features/authorization/MetaField'
import { useQuoteManagerLabels } from '@/features/quotes/use-quote-manager-labels'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'
import type { QuoteDetail } from '@/features/quotes/types'
import type { ForSelectItem } from '@/features/for-select/types'

interface QuoteTeamSectionProps {
  control: Control<QuoteFormValues>
  /** The loaded quote in edit mode, for the slots' hydrated labels and the sync banner (D-7). */
  original: QuoteDetail | null
  className?: string
}

/** Stable empty selection while there is nothing to hydrate (create mode, or the pivot not loaded yet). */
const EMPTY_SELECTED_ITEMS: ForSelectItem[] = []

/** Converts the wire `manager_labels` (string position keys) to `ManagerSlotsField`'s own `Record<number, string>`. */
function toSlotLabels(source: Record<string, string>): Record<number, string> | undefined {
  const entries = Object.entries(source).map(([position, label]) => [Number(position), label] as const)
  return entries.length > 0 ? Object.fromEntries(entries) : undefined
}

/**
 * The offer's own team: the shared, ordered "G.A. n" manager slots (spec
 * 0087 D-1/D-11, `ManagerSlotsField`, reused verbatim — the Supervisore
 * stays a plain field in `QuoteFormBody`'s identity section, an unrelated
 * commission-only role, D-13). Mirrors `OpportunityTeamSection`.
 *
 * "G.A. n" relabeling (D-8): resolved LIVE from the offer's own current
 * REVENUE rows (`useQuoteManagerLabels`), falling back to the linked
 * Opportunity's categories while there are none yet.
 *
 * The sync banner (D-7) only ever reflects a LOADED quote's own
 * `managers_synchronized` — there is nothing to sync yet on a still-unsaved
 * create.
 */
export function QuoteTeamSection({ control, original, className }: QuoteTeamSectionProps) {
  const { t } = useTranslation()
  const opportunityId = useWatch({ control, name: 'opportunity_id' })
  const offerLines = useWatch({ control, name: 'offer_lines' })
  const managerLabels = useQuoteManagerLabels(offerLines ?? [], opportunityId)
  const slotLabels = toSlotLabels(managerLabels)

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
