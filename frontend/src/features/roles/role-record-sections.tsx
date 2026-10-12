import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import type { UseFormReturn } from 'react-hook-form'
import { KeySquare, ShieldCheck } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { RoleNameField, RoleUsersField } from '@/features/roles/role-fields'
import { RoleMembers } from '@/features/roles/role-overview'
import type { RoleFormValues } from '@/features/roles/use-role-form'

interface IdentitySectionProps {
  /** What the closed rows show: the saved role on the detail, the draft on create. */
  values: Pick<RoleFormValues, 'name' | 'users'>
  form: UseFormReturn<RoleFormValues>
  inline: InlineEdit
  /** `false` without the role's `update` permission: no row offers a pencil. */
  canEdit: boolean
}

/** "Dettagli ruolo": name and members, one row each (spec 0230 D-2). */
export function RoleIdentityRecordSection({ values, form, inline, canEdit }: IdentitySectionProps) {
  const { t } = useTranslation()
  const { control } = form

  return (
    <RecordSection title={t('roles.form.sections.details.title')} icon={<ShieldCheck />}>
      <RecordFieldList>
        <RecordInlineField
          field="name"
          label={t('roles.form.name')}
          inline={inline}
          canEdit={canEdit}
          editor={<RoleNameField control={control} />}
        >
          {values.name.trim() !== '' ? <span className="break-words">{values.name}</span> : <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="users"
          label={t('roles.form.users')}
          inline={inline}
          canEdit={canEdit}
          editor={<RoleUsersField control={control} />}
        >
          <RoleMembers ids={values.users} />
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}

interface PermissionsSectionProps {
  /** How many permissions are granted: the section's badge. */
  count: number
  children: ReactNode
}

/** The "Permessi" section's frame, shared by the detail (one block row) and the create form (the open editor). */
export function RolePermissionsRecordSection({ count, children }: PermissionsSectionProps) {
  const { t } = useTranslation()

  return (
    <RecordSection
      title={t('roles.form.sections.permissions.title')}
      icon={<KeySquare />}
      action={count > 0 ? <Badge variant="secondary">{count}</Badge> : null}
      full
    >
      {children}
    </RecordSection>
  )
}
