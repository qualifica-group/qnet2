import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { RelationMultiSelectField } from '@/components/form/relation-multi-select-field'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { MetaField } from '@/features/authorization/MetaField'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import type { RoleFormValues } from '@/features/roles/use-role-form'

/**
 * The role's header field controls (spec 0230), shared by the detail's
 * in-place rows and the create form's closed rows: a row's editor is the very
 * control the create form uses (spec 0195 D-3).
 */

interface FieldProps {
  control: Control<RoleFormValues>
}

export function RoleNameField({ control }: FieldProps) {
  const { t } = useTranslation()

  return (
    <MetaField control={control} name="name" metaKey="name" label={t('roles.form.name')}>
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
        </FormControl>
      )}
    </MetaField>
  )
}

export function RoleUsersField({ control }: FieldProps) {
  const { t } = useTranslation()

  return (
    <RelationMultiSelectField
      control={control}
      name="users"
      metaKey="users"
      label={t('roles.form.users')}
      resource={USERS_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('roles.form.usersSearch')}
      showAvatar
      placeholder={t('roles.form.usersPlaceholder')}
      emptyLabel={t('roles.form.usersEmpty')}
      errorLabel={t('roles.form.usersError')}
      removeLabel={t('roles.form.usersRemove')}
      retryLabel={t('common.retry')}
    />
  )
}
