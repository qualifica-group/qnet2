import { useTranslation } from 'react-i18next'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordField, RecordFieldList } from '@/components/detail/record-panel'
import { enumLabelOf } from '@/features/config/enum-label'
import type { PersonalDataCard } from '@/features/personal-data/types'
import { formatDate } from '@/lib/formatting/date-display'

/**
 * The anagraphic card's fiscal identity as read-only record rows, shared by
 * every owner's record screen (anagrafica, referente, and whoever comes next).
 *
 * Which rows exist depends on the card's own kind — a company has an SDI code,
 * a person has birth and residence data — and that split is made HERE, once,
 * the same way `PersonalDataCardForm` makes it on the editing side. Written
 * per module it would be the same twenty rows copied twice, free to answer
 * differently the day the card gains a field.
 */
export function PersonalDataIdentityRows({ card }: { card: PersonalDataCard }) {
  const { t } = useTranslation()

  return (
    <RecordFieldList>
      {card.type === 'company' ? (
        <RecordField label={t('personalData.form.companyName')}>
          {card.company_name || <DetailEmpty />}
        </RecordField>
      ) : (
        <>
          <RecordField label={t('personalData.form.firstName')}>
            {card.first_name || <DetailEmpty />}
          </RecordField>
          <RecordField label={t('personalData.form.lastName')}>
            {card.last_name || <DetailEmpty />}
          </RecordField>
        </>
      )}

      <RecordField label={t('personalData.form.taxCode')}>
        {card.tax_code || <DetailEmpty />}
      </RecordField>
      <RecordField label={t('personalData.form.vatNumber')}>
        {card.vat_number || <DetailEmpty />}
      </RecordField>

      {card.type === 'company' ? (
        <RecordField label={t('personalData.form.sdiCode')}>
          {card.sdi_code || <DetailEmpty />}
        </RecordField>
      ) : (
        <>
          <RecordField label={t('personalData.form.birthDate')}>
            {formatDate(card.birth_date) || <DetailEmpty />}
          </RecordField>
          <RecordField label={t('personalData.form.birthCity')}>
            {card.birth_city?.name || <DetailEmpty />}
          </RecordField>
          <RecordField label={t('personalData.form.residenceCity')}>
            {card.residence_city?.name || <DetailEmpty />}
          </RecordField>
          <RecordField label={t('personalData.form.gender')}>
            {card.gender ? enumLabelOf('gender', card.gender) : <DetailEmpty />}
          </RecordField>
        </>
      )}
    </RecordFieldList>
  )
}
