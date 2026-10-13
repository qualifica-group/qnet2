import { useTranslation } from 'react-i18next'
import { RecordSectionsGrid } from '@/components/detail/record-panel'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'
import { PersonalDataIdentityRecordSection } from '@/features/personal-data/personal-data-identity-record-section'
import { useAssignmentFieldsVisibility, useUserSectionVisibility } from '@/features/users/user-assignment'
import { UserAssignmentRecordSection } from '@/features/users/user-assignment-record-section'
import { UserAvatarField } from '@/features/users/user-avatar-field'
import { persistedUserValues } from '@/features/users/user-record'
import {
  UserAccessRecordSection,
  UserContractDataRecordSection,
  UserContractRecordSection,
  UserProfileRecordSection,
} from '@/features/users/user-record-sections'
import type { UserDetailEditor } from '@/features/users/use-user-inline-edit'
import type { UserDetailWithPermissions } from '@/features/users/types'

interface UserDetailSectionsProps {
  user: UserDetailWithPermissions
  editor: UserDetailEditor
  onChanged?: () => void
}

/**
 * The user record's `RecordSectionsGrid` body, every user-written field
 * editable in place (spec 0222): the anagraphic card (with the profile
 * photo), credentials and access, the ASSIGNMENT configuration full width,
 * the employment profile and the custom fields. The employment sections are
 * there even for a user with no profile yet: the first save creates it. The
 * create form renders the same sections in the same order.
 */
export function UserDetailSections({ user, editor, onChanged }: UserDetailSectionsProps) {
  const { t } = useTranslation()
  const { form, inline, card } = editor
  const visibility = useUserSectionVisibility()
  const assignmentFields = useAssignmentFieldsVisibility()
  const sectionProps = { values: persistedUserValues(user), form, inline }

  return (
    <RecordSectionsGrid>
      <PersonalDataIdentityRecordSection
        title={t('users.form.sections.identity.title')}
        identity={user.personal_data ?? null}
        buffer={card}
        inline={inline}
        leading={<UserAvatarField user={user} onChanged={onChanged} />}
      />
      {visibility.access ? <UserAccessRecordSection {...sectionProps} isEdit /> : null}
      {assignmentFields.any ? <UserAssignmentRecordSection {...sectionProps} /> : null}
      {visibility.profile ? <UserProfileRecordSection {...sectionProps} /> : null}
      {visibility.contract ? <UserContractRecordSection {...sectionProps} /> : null}
      {visibility.contractData ? <UserContractDataRecordSection {...sectionProps} /> : null}
      <CustomFieldRecordSections resource="users" values={sectionProps.values.custom_fields} inline={inline} control={form.control} />
    </RecordSectionsGrid>
  )
}
