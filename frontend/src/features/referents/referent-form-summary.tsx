import { useTranslation } from 'react-i18next'
import { Info } from 'lucide-react'
import { useWatch, type Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { EMPTY_VALUE, SUMMARY_LIST_CLASS, SummaryRow } from '@/components/record-form/record-summary'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { enumLabelOf } from '@/features/config/enum-label'
import type { ForSelectItem } from '@/features/for-select/types'
import type { PersonalDataDraft } from '@/features/personal-data/types'
import type { ReferentFormValues } from '@/features/referents/use-referent-form'

interface ReferentFormSummaryProps {
  control: Control<ReferentFormValues>
  /** Hydrated refs: the only source of a NAME for the ids the form holds. */
  selectedReferentTypeItem: ForSelectItem | null
  selectedUserItem: ForSelectItem | null
  /** The buffered anagraphic card: contacts and addresses live there, not in RHF. */
  profileDraft: PersonalDataDraft
}

/**
 * The form's side-column recap, in the same card and the same `label / value`
 * rows as the Opportunità and anagrafica summaries (`SummaryRow`), so the
 * record forms keep one shape.
 *
 * Contacts and addresses are counted from the BUFFERED draft, not from RHF:
 * they are managed outside the form state (`ContactsManager`/
 * `AddressesManager`), and a recap reading anything else would lag behind what
 * the operator just added. A relation renders its name only while the hydrated
 * ref still matches the chosen id; after a change the picker's own trigger
 * already names the new one, and inventing a label here would be a guess.
 */
export function ReferentFormSummary({
  control,
  selectedReferentTypeItem,
  selectedUserItem,
  profileDraft,
}: ReferentFormSummaryProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const referentTypeId = useWatch({ control, name: 'referent_type_id' })
  const userId = useWatch({ control, name: 'user_id' })
  const contactScope = useWatch({ control, name: 'contact_scope' })

  const referentTypeName =
    referentTypeId !== null && selectedReferentTypeItem?.id === referentTypeId
      ? selectedReferentTypeItem.label
      : null
  const userName =
    userId !== null && userId !== undefined && selectedUserItem?.id === userId
      ? selectedUserItem.label
      : null

  return (
    <FormSection
      icon={Info}
      title={t('referents.detail.summary.title')}
      description={t('referents.detail.summary.description')}
      className="min-w-0"
    >
      <dl className={SUMMARY_LIST_CLASS}>
        {fieldPermission('referent_type_id').visible ? (
          <SummaryRow label={t('referents.form.referentType')}>
            {referentTypeName ?? EMPTY_VALUE}
          </SummaryRow>
        ) : null}
        {fieldPermission('contact_scope').visible ? (
          <SummaryRow label={t('referents.form.contactScope')}>
            {contactScope ? enumLabelOf('referent_contact_scope', contactScope) : EMPTY_VALUE}
          </SummaryRow>
        ) : null}
        {fieldPermission('user_id').visible ? (
          <SummaryRow label={t('referents.form.linkedUser')}>{userName ?? EMPTY_VALUE}</SummaryRow>
        ) : null}
        <SummaryRow label={t('referents.detail.stats.contacts')}>
          {profileDraft.contacts.length > 0 ? profileDraft.contacts.length : EMPTY_VALUE}
        </SummaryRow>
        <SummaryRow label={t('referents.detail.stats.addresses')}>
          {profileDraft.addresses.length > 0 ? profileDraft.addresses.length : EMPTY_VALUE}
        </SummaryRow>
      </dl>
    </FormSection>
  )
}
