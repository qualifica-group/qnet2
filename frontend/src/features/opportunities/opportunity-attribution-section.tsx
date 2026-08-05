import { useTranslation } from 'react-i18next'
import { Route } from 'lucide-react'
import { useWatch, type Control, type UseFormSetValue } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { FIELD_GRID_CLASS } from '@/components/record-form/layout'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { SOURCES_FOR_SELECT_RESOURCE } from '@/features/sources/for-select-api'
import { OpportunityReporterField } from '@/features/opportunities/opportunity-reporter-field'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'
import type { OpportunitySelectedItems } from '@/features/opportunities/use-opportunity-selected-items'
import type { RewardAssignmentRef } from '@/features/rewards/types'

interface OpportunityAttributionSectionProps {
  control: Control<OpportunityFormValues>
  setValue: UseFormSetValue<OpportunityFormValues>
  selectedItems: OpportunitySelectedItems
  /** BR-2: keys derived from a linked Lead, forced read-only (spec 0040 MT-6; empty outside that flow). */
  lockedFields: ReadonlySet<string>
  /** The loaded opportunity's persisted reward assignments (edit mode), `[]` on create. */
  initialRewards: RewardAssignmentRef[]
  className?: string
}

/**
 * Where the opportunity comes from and who is attributed with it: the Fonte
 * and the Segnalatore with its reward assignments.
 *
 * Same position as the Gestione Richieste attribution section (user directive
 * 2026-08-05, "prendere spunto dalla gestione richieste"): provenance and
 * ownership sit right after the levers acted on at every touch and before the
 * client's own data. Replaces the former "Classificazione" card.
 *
 * Sede operativa and Regione are NOT rendered here (user directive
 * 2026-08-05: "oscurare sede operativa e regione, servono solo in gestione
 * richieste"). Hidden, not dropped: `operational_site_id`/`state_id` stay
 * form values, so a value inherited from the Lead or already persisted
 * survives the save untouched — the picker is simply no longer part of this
 * form's surface. Gestione Richieste keeps both.
 */
export function OpportunityAttributionSection({
  control,
  setValue,
  selectedItems,
  lockedFields,
  initialRewards,
  className,
}: OpportunityAttributionSectionProps) {
  const { t } = useTranslation()
  const reporterId = useWatch({ control, name: 'reporter_id' })
  const rewards = useWatch({ control, name: 'rewards' })

  const selectLabels = {
    placeholder: t('opportunities.form.selectPlaceholder'),
    emptyLabel: t('opportunities.form.selectEmpty'),
    errorLabel: t('opportunities.form.selectError'),
    clearLabel: t('common.clear'),
    retryLabel: t('common.retry'),
  }

  return (
    <FormSection
      icon={Route}
      title={t('opportunities.form.sections.attribution.title')}
      description={t('opportunities.form.sections.attribution.description')}
      className={className}
    >
      <div className={FIELD_GRID_CLASS}>
        <RelationSelectField
          control={control}
          name="source_id"
          metaKey="source_id"
          label={t('opportunities.form.source')}
          resource={SOURCES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('opportunities.form.sourceSearch')}
          selected={selectedItems.source}
          forceDisabled={lockedFields.has('source_id')}
          {...selectLabels}
        />

        <OpportunityReporterField
          control={control}
          selected={selectedItems.reporter}
          reporterId={reporterId}
          rewards={rewards}
          onRewardsChange={(next) => setValue('rewards', next, { shouldDirty: true })}
          initialRewards={initialRewards}
        />

      </div>
    </FormSection>
  )
}
