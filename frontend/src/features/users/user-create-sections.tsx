import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import type { UseFormReturn } from 'react-hook-form'
import { RecordSectionsGrid } from '@/components/detail/record-panel'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'
import {
  PersonalDataIdentityRecordSection,
  type PersonalDataCardBuffer,
} from '@/features/personal-data/personal-data-identity-record-section'
import { anagraphicSectionProps } from '@/features/personal-data/use-reveal-blocked-section'
import { useAssignmentFieldsVisibility, useUserSectionVisibility } from '@/features/users/user-assignment'
import { UserContractHistoryCreateNote } from '@/features/users/user-contract-history-section'
import { usePaymentVisible } from '@/features/users/use-payment-visible'
import { UserPaymentRecordSection } from '@/features/users/user-payment-section'
import { UserAssignmentRecordSection } from '@/features/users/user-assignment-record-section'
import type { UserRecordValues } from '@/features/users/user-record'
import {
  UserAccessRecordSection,
  UserContractDataRecordSection,
  UserProfileRecordSection,
} from '@/features/users/user-record-sections'
import type { UserFormValues } from '@/features/users/use-user-form'

interface UserCreateSectionsProps {
  /** The draft as the closed rows read it (`useUserDraftValues`, shared with the header). */
  values: UserRecordValues
  form: UseFormReturn<UserFormValues>
  draft: InlineEdit
  card: PersonalDataCardBuffer
  /** The deferred profile-photo picker, above the card's row. */
  avatar: ReactNode
}

/**
 * The create form as a replica of the user detail (spec 0222 D-2): the
 * detail's sections, rows, labels and order — the anagraphic card included —
 * every row CLOSED until clicked and opening on the same component the detail
 * edits in place. Nothing is saved per row: the header's Salva validates and
 * creates the whole draft.
 */
export function UserCreateSections({ values, form, draft, card, avatar }: UserCreateSectionsProps) {
  const { t } = useTranslation()
  const visibility = useUserSectionVisibility()
  const assignmentFields = useAssignmentFieldsVisibility()
  const paymentVisible = usePaymentVisible()
  const sectionProps = { values, form, inline: draft }

  return (
    <RecordSectionsGrid>
      {/* The grid item itself, so a refused save can scroll it into view. */}
      <div {...anagraphicSectionProps('card')} className="flex min-w-0 flex-col">
        <PersonalDataIdentityRecordSection
          title={t('users.form.sections.identity.title')}
          identity={card.draft}
          buffer={card}
          inline={draft}
          leading={avatar}
        />
      </div>
      {visibility.access ? <UserAccessRecordSection {...sectionProps} isEdit={false} /> : null}
      {assignmentFields.any ? <UserAssignmentRecordSection {...sectionProps} /> : null}
      {visibility.profile ? <UserProfileRecordSection {...sectionProps} /> : null}
      <UserContractHistoryCreateNote />
      {visibility.contractData ? <UserContractDataRecordSection {...sectionProps} /> : null}
      {paymentVisible ? <UserPaymentRecordSection {...sectionProps} /> : null}
      <CustomFieldRecordSections resource="users" values={values.custom_fields} inline={draft} control={form.control} />
    </RecordSectionsGrid>
  )
}
