import { IdCard, Landmark, Percent, ReceiptEuro } from 'lucide-react'
import { useFormContext, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { RecordTextEditor } from '@/components/record-form/record-field-editors'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { MetaField } from '@/features/authorization/MetaField'
import { MetaFieldRowContext } from '@/features/authorization/meta-field-row-context'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { VAT_RATES_FOR_SELECT_RESOURCE } from '@/features/vat-rates/for-select-api'
import { formatDate } from '@/lib/formatting/date-display'
import { CostTypeRow, PaymentMethodRow } from '@/features/agent-fiscal-profiles/agent-fiscal-profile-document-rows'
import {
  EnasarcoRow,
  SecondaryFieldError,
  WithholdingRow,
} from '@/features/agent-fiscal-profiles/agent-fiscal-profile-tax-fields'
import type {
  AgentFiscalProfileFormValues,
  AgentFiscalProfileSchemaOutput,
} from '@/features/agent-fiscal-profiles/agent-fiscal-profile-schema'
import type { AgentFiscalProfileRefs } from '@/features/agent-fiscal-profiles/types'

interface RowProps {
  inline: InlineEdit
  canEdit: boolean
}

interface RelationRowProps extends RowProps {
  refs: AgentFiscalProfileRefs
}

function useProfileForm() {
  return useFormContext<AgentFiscalProfileFormValues, unknown, AgentFiscalProfileSchemaOutput>()
}

function ReferentRow({ inline, canEdit, refs }: RelationRowProps) {
  const { t } = useTranslation()
  const { control } = useProfileForm()

  return (
    <RecordInlineField
      field="referent_id"
      label={t('agentFiscalProfiles.form.referent')}
      inline={inline}
      canEdit={canEdit}
      editor={
        <RelationSelectField
          control={control}
          name="referent_id"
          metaKey="referent_id"
          label={t('agentFiscalProfiles.form.referent')}
          resource={REFERENTS_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('agentFiscalProfiles.form.referentSearch')}
          selected={refs.referent}
          placeholder={t('agentFiscalProfiles.form.referentPlaceholder')}
          emptyLabel={t('agentFiscalProfiles.form.referentEmpty')}
          errorLabel={t('agentFiscalProfiles.form.referentError')}
          clearLabel={t('common.clear')}
          retryLabel={t('common.retry')}
          required
        />
      }
    >
      {refs.referent ? refs.referent.name : <DetailEmpty />}
    </RecordInlineField>
  )
}

/** Start and end are ONE row: the server checks them together (order and no overlap with the agent's other profiles). */
function ValidityRow({ inline, canEdit }: RowProps) {
  const { t } = useTranslation()
  const { control } = useProfileForm()
  const [validFrom, validUntil] = useWatch({ control, name: ['valid_from', 'valid_until'] })
  const from = formatDate(validFrom)

  return (
    <RecordInlineField
      field="valid_from"
      label={t('agentFiscalProfiles.detail.validityRow')}
      inline={inline}
      canEdit={canEdit}
      editor={
        // The row's label names the group: each sub-field keeps its own visible label.
        <MetaFieldRowContext.Provider value={null}>
          <div className="grid gap-3 sm:grid-cols-2">
            <MetaField control={control} name="valid_from" metaKey="valid_from" label={t('agentFiscalProfiles.form.validFrom')} required>
              {({ field, disabled, readOnly }) => (
                <FormControl>
                  <Input type="date" disabled={disabled} readOnly={readOnly} {...field} />
                </FormControl>
              )}
            </MetaField>
            <MetaField control={control} name="valid_until" metaKey="valid_until" label={t('agentFiscalProfiles.form.validUntil')}>
              {({ field, disabled, readOnly }) => (
                <FormControl>
                  <Input type="date" disabled={disabled} readOnly={readOnly} {...field} />
                </FormControl>
              )}
            </MetaField>
          </div>
        </MetaFieldRowContext.Provider>
      }
    >
      {validFrom === '' ? (
        <DetailEmpty />
      ) : validUntil !== '' ? (
        t('agentFiscalProfiles.detail.validityRange', { from, until: formatDate(validUntil) })
      ) : (
        t('agentFiscalProfiles.detail.validityOpenEnded', { from })
      )}
      <SecondaryFieldError names={['valid_until']} />
    </RecordInlineField>
  )
}

function VatRateRow({ inline, canEdit, refs }: RelationRowProps) {
  const { t } = useTranslation()
  const { control } = useProfileForm()

  return (
    <RecordInlineField
      field="vat_rate_id"
      label={t('agentFiscalProfiles.form.vatRate')}
      inline={inline}
      canEdit={canEdit}
      editor={
        <RelationSelectField
          control={control}
          name="vat_rate_id"
          metaKey="vat_rate_id"
          label={t('agentFiscalProfiles.form.vatRate')}
          hint={t('agentFiscalProfiles.form.sections.vat.description')}
          resource={VAT_RATES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('agentFiscalProfiles.form.vatRateSearch')}
          selected={refs.vatRate}
          placeholder={t('agentFiscalProfiles.form.vatRatePlaceholder')}
          emptyLabel={t('agentFiscalProfiles.form.vatRateEmpty')}
          errorLabel={t('agentFiscalProfiles.form.vatRateError')}
          clearLabel={t('common.clear')}
          retryLabel={t('common.retry')}
        />
      }
    >
      {refs.vatRate ? refs.vatRate.name : t('agentFiscalProfiles.detail.vatNone')}
    </RecordInlineField>
  )
}

function NotesRow({ inline, canEdit }: RowProps) {
  const { t } = useTranslation()
  const { control } = useProfileForm()
  const notes = useWatch({ control, name: 'notes' })

  return (
    <RecordInlineField
      field="notes"
      label={t('agentFiscalProfiles.form.notes')}
      inline={inline}
      canEdit={canEdit}
      editor={<RecordTextEditor control={control} name="notes" label={t('agentFiscalProfiles.form.notes')} multiline />}
    >
      {notes.trim() !== '' ? <span className="break-words whitespace-pre-wrap">{notes}</span> : <DetailEmpty />}
    </RecordInlineField>
  )
}

/**
 * The profile's sections, shared by the detail (saved one row at a time) and
 * the create draft (kept until Salva). The fields the server validates
 * together share one row (spec 0264 D-4).
 */
export function AgentFiscalProfileSections({ inline, canEdit, refs }: RelationRowProps) {
  const { t } = useTranslation()
  const rowProps = { inline, canEdit }

  return (
    <RecordSectionsGrid>
      <RecordSection title={t('agentFiscalProfiles.form.sections.validity.title')} icon={<IdCard />}>
        <RecordFieldList>
          <ReferentRow {...rowProps} refs={refs} />
          <ValidityRow {...rowProps} />
        </RecordFieldList>
      </RecordSection>
      <RecordSection title={t('agentFiscalProfiles.form.sections.withholding.title')} icon={<Percent />}>
        <RecordFieldList>
          <WithholdingRow {...rowProps} />
        </RecordFieldList>
      </RecordSection>
      <RecordSection title={t('agentFiscalProfiles.form.sections.enasarco.title')} icon={<Landmark />}>
        <RecordFieldList>
          <EnasarcoRow {...rowProps} />
        </RecordFieldList>
      </RecordSection>
      <RecordSection title={t('agentFiscalProfiles.form.sections.vat.title')} icon={<ReceiptEuro />}>
        <RecordFieldList>
          <VatRateRow {...rowProps} refs={refs} />
          <PaymentMethodRow {...rowProps} refs={refs} />
          <CostTypeRow {...rowProps} refs={refs} />
          <NotesRow {...rowProps} />
        </RecordFieldList>
      </RecordSection>
    </RecordSectionsGrid>
  )
}
