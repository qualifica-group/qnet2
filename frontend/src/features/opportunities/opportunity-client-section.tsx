import { useTranslation } from 'react-i18next'
import { Contact } from 'lucide-react'
import { useWatch, type Control, type UseFormSetValue } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { FIELD_GRID_CLASS, FIELD_STACK_CLASS } from '@/components/record-form/layout'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { OpportunityContactRecap } from '@/features/opportunities/opportunity-contact-recap'
import { OpportunityRegistryField } from '@/features/opportunities/opportunity-registry-field'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'
import type { OpportunitySelectedItems } from '@/features/opportunities/use-opportunity-selected-items'

interface OpportunityClientSectionProps {
  control: Control<OpportunityFormValues>
  setValue: UseFormSetValue<OpportunityFormValues>
  selectedItems: OpportunitySelectedItems
  /** BR-2: keys derived from a linked Lead, forced read-only (spec 0040 MT-6; empty outside that flow). */
  lockedFields: ReadonlySet<string>
  className?: string
}

/**
 * Who the opportunity is for: the required anagrafica and the two contacts
 * read off it. Last card of the form, exactly where the Gestione Richieste
 * screens keep their own client block (user directive 2026-08-05) — what the
 * record is ABOUT comes first, who it belongs to closes the form.
 *
 * A-4: each select carries the recap of the chosen person's primary contacts.
 * Only the Referente stays anagrafica-scoped (BR-4) and therefore disabled
 * until a registry is picked; Commerciale is the whole platform list (A-3),
 * merely INHERITED from the anagrafica's own role (directive 2026-07-29).
 */
export function OpportunityClientSection({
  control,
  setValue,
  selectedItems,
  lockedFields,
  className,
}: OpportunityClientSectionProps) {
  const { t } = useTranslation()
  const registryId = useWatch({ control, name: 'registry_id' })
  const referentId = useWatch({ control, name: 'referent_id' })
  const commercialId = useWatch({ control, name: 'commercial_id' })

  const selectLabels = {
    placeholder: t('opportunities.form.selectPlaceholder'),
    emptyLabel: t('opportunities.form.selectEmpty'),
    errorLabel: t('opportunities.form.selectError'),
    clearLabel: t('common.clear'),
    retryLabel: t('common.retry'),
  }

  return (
    <FormSection
      icon={Contact}
      title={t('opportunities.form.sections.identity.title')}
      description={t('opportunities.form.sections.identity.description')}
      className={className}
    >
      <OpportunityRegistryField
        control={control}
        setValue={setValue}
        selected={selectedItems.registry}
        forceDisabled={lockedFields.has('registry_id')}
      />

      <div className={FIELD_GRID_CLASS}>
        <div className={FIELD_STACK_CLASS}>
          <RelationSelectField
            control={control}
            name="referent_id"
            metaKey="referent_id"
            label={t('opportunities.form.referent')}
            resource={REFERENTS_FOR_SELECT_RESOURCE}
            searchPlaceholder={t('opportunities.form.referentSearch')}
            selected={selectedItems.referent}
            params={registryId !== null ? { registry_id: registryId } : undefined}
            forceDisabled={registryId === null || lockedFields.has('referent_id')}
            {...selectLabels}
          />
          <OpportunityContactRecap referentId={referentId} />
        </div>

        <div className={FIELD_STACK_CLASS}>
          <RelationSelectField
            control={control}
            name="commercial_id"
            metaKey="commercial_id"
            label={t('opportunities.form.commercial')}
            resource={REFERENTS_FOR_SELECT_RESOURCE}
            searchPlaceholder={t('opportunities.form.commercialSearch')}
            selected={selectedItems.commercial}
            {...selectLabels}
          />
          <OpportunityContactRecap referentId={commercialId} />
        </div>
      </div>
    </FormSection>
  )
}
