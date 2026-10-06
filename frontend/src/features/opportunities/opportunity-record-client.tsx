import { useTranslation } from 'react-i18next'
import { Contact } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import { RecordField, RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import { ExistingOpportunityAlert } from '@/features/opportunities/existing-opportunity-alert'
import { OpportunityRegistryField } from '@/features/opportunities/opportunity-registry-field'
import { OpportunityReporterField } from '@/features/opportunities/opportunity-reporter-field'
import { useCascadeEditable, type OpportunityRecordSectionProps } from '@/features/opportunities/opportunity-record'
import {
  OpportunityCommercialField,
  OpportunityReferentField,
} from '@/features/opportunities/opportunity-relation-fields'
import type { BlockingOpportunity } from '@/features/opportunities/use-opportunity-form'

/**
 * What picking an anagrafica writes besides itself: the referent it clears
 * (BR-4) and the four roles it hands down (spec 0040 A-5, directive
 * 2026-07-29), all in the same save.
 */
const REGISTRY_CASCADE_FIELDS = ['referent_id', 'commercial_id', 'reporter_id', 'supervisor_id', 'manager_slots']

/** A referent-like relation row's persisted value: a link to the record, or the kit's empty placeholder. */
function ReferentLink({ value }: { value: RelationFieldRef | null }) {
  return value ? (
    <RecordLink domain="referents" id={value.id}>
      {value.name}
    </RecordLink>
  ) : (
    <DetailEmpty />
  )
}

interface OpportunityClientRecordSectionProps extends OpportunityRecordSectionProps {
  /** The open opportunity the chosen anagrafica already has, when the server refused it (directive 2026-08-31). */
  blockingOpportunity: BlockingOpportunity | null
}

/**
 * "Anagrafica e contatti" of the opportunity record (spec 0198): who the
 * opportunity is for and its contacts, every row editing in place. The
 * anagrafica's editor is the create form's own picker, with its cascade —
 * so it opens only when everything that cascade writes is editable too, and
 * never on an anagrafica derived from a Lead (BR-2). The Segnalatore's editor
 * carries its reward assignments (spec 0059 D-3). The originating Lead is
 * immutable: a plain read-only row.
 */
export function OpportunityClientRecordSection({
  values,
  form,
  inline,
  lockedFields,
  blockingOpportunity,
}: OpportunityClientRecordSectionProps) {
  const { t } = useTranslation()
  const { control, setValue, getValues } = form
  const cascadeEditable = useCascadeEditable(REGISTRY_CASCADE_FIELDS)

  return (
    <RecordSection title={t('opportunities.form.sections.identity.title')} icon={<Contact />}>
      <RecordFieldList>
        <RecordInlineField
          field="registry_id"
          label={t('opportunities.form.registry')}
          inline={inline}
          canEdit={cascadeEditable && !lockedFields.has('registry_id')}
          editor={
            <>
              <OpportunityRegistryField
                control={control}
                setValue={setValue}
                getValues={getValues}
                selected={values.registry}
              />
              {blockingOpportunity !== null ? (
                <ExistingOpportunityAlert
                  opportunityId={blockingOpportunity.id}
                  message={blockingOpportunity.message}
                  productIds={blockingOpportunity.productIds}
                />
              ) : null}
            </>
          }
        >
          {values.registry ? (
            <RecordLink domain="registries" id={values.registry.id}>
              {values.registry.name}
            </RecordLink>
          ) : (
            <DetailEmpty />
          )}
        </RecordInlineField>
        <RecordInlineField
          field="referent_id"
          label={t('opportunities.form.referent')}
          inline={inline}
          canEdit={values.registry !== null && !lockedFields.has('referent_id')}
          editor={<OpportunityReferentField control={control} selected={values.referent} />}
        >
          <ReferentLink value={values.referent} />
        </RecordInlineField>
        <RecordInlineField
          field="commercial_id"
          label={t('opportunities.form.commercial')}
          inline={inline}
          editor={<OpportunityCommercialField control={control} selected={values.commercial} />}
        >
          <ReferentLink value={values.commercial} />
        </RecordInlineField>
        <RecordInlineField
          field="reporter_id"
          label={t('opportunities.form.reporter')}
          inline={inline}
          editor={
            <OpportunityReporterField control={control} selected={values.reporter} initialRewards={values.rewards} />
          }
        >
          <ReferentLink value={values.reporter} />
        </RecordInlineField>
        {values.lead ? (
          <RecordField label={t('opportunities.detail.sourceLead')}>
            <RecordLink domain="leads" id={values.lead.id}>
              {values.lead.label}
            </RecordLink>
          </RecordField>
        ) : null}
      </RecordFieldList>
    </RecordSection>
  )
}
