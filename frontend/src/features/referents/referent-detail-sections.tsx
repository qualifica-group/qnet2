import { useTranslation } from 'react-i18next'
import { IdCard, Info } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import {
  RecordField,
  RecordFieldList,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { PersonalDataIdentityRows } from '@/features/personal-data/personal-data-identity-rows'
import type { ReferentDetailWithPermissions } from '@/features/referents/types'

interface ReferentDetailSectionsProps {
  referent: ReferentDetailWithPermissions
}

/**
 * The record's `RecordSectionsGrid` body. The sections MIRROR the form's own
 * (`referents.form.sections.*`), title for title, so an operator who reads the
 * card and then opens the form finds the same blocks in the same order.
 *
 * The referent TYPE and the contact SCOPE are deliberately not rows here: the
 * identity band already carries them, as subtitle and pill. Repeating them
 * three inches lower would read as two different fields holding the same
 * value — the same rule that keeps the anagrafica's supplier flags a pill and
 * not a row.
 *
 * `user`/`user_id` are OMITTED — not `null` — when the actor may not see them
 * (spec 0090 D-2), so the row is absent rather than showing an empty value for
 * a field that exists but is hidden.
 */
export function ReferentDetailSections({ referent }: ReferentDetailSectionsProps) {
  const { t } = useTranslation()

  return (
    <RecordSectionsGrid>
      {referent.personal_data ? (
        <RecordSection title={t('referents.form.sections.identity.title')} icon={<IdCard />}>
          <PersonalDataIdentityRows card={referent.personal_data} />
        </RecordSection>
      ) : null}

      <RecordSection title={t('referents.form.sections.details.title')} icon={<Info />}>
        <RecordFieldList>
          {'user' in referent ? (
            <RecordField label={t('referents.form.linkedUser')}>
              {referent.user?.name ?? <DetailEmpty />}
            </RecordField>
          ) : null}
          <RecordField label={t('referents.form.notes')}>
            {referent.notes || <DetailEmpty />}
          </RecordField>
        </RecordFieldList>
      </RecordSection>
    </RecordSectionsGrid>
  )
}
