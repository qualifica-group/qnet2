import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { RelationMultiSelectField } from '@/components/form/relation-multi-select-field'
import { FormControl, FormDescription } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { MetaField } from '@/features/authorization/MetaField'
import { ROLES_FOR_SELECT_RESOURCE } from '@/features/roles/for-select-api'
import type { EmploymentRelationRef } from '@/features/users/types'
import { toFieldRefs } from '@/features/users/user-record'
import type { UserFormValues } from '@/features/users/use-user-form'

/*
 * The account's own fields (credentials and access), one component each,
 * shared by the detail's in-place rows and the create draft's rows (spec 0222).
 */

interface FieldProps {
  control: Control<UserFormValues>
}

export function UserEmailField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField control={control} name="email" metaKey="email" label={t('users.form.email')}>
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input type="email" autoComplete="email" disabled={disabled} readOnly={readOnly} {...field} />
        </FormControl>
      )}
    </MetaField>
  )
}

/**
 * "Password" (spec 0177, spec 0222 D-5): the new password and its
 * confirmation in one editor. On create an empty pair means "invite by
 * email"; on the detail it sets a temporary password.
 */
export function UserPasswordFields({ control, isEdit }: FieldProps & { isEdit: boolean }) {
  const { t } = useTranslation()
  return (
    <div className="flex min-w-0 flex-col gap-3">
      <MetaField
        control={control}
        name="password"
        metaKey="password"
        label={t(isEdit ? 'users.form.newPassword' : 'users.form.password')}
        description={
          <FormDescription>{t(isEdit ? 'users.form.passwordEditHint' : 'users.form.passwordCreateHint')}</FormDescription>
        }
      >
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <Input type="password" autoComplete="new-password" disabled={disabled} readOnly={readOnly} {...field} />
          </FormControl>
        )}
      </MetaField>
      <MetaField control={control} name="password_confirmation" metaKey="password" label={t('users.form.confirmPassword')}>
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <Input type="password" autoComplete="new-password" disabled={disabled} readOnly={readOnly} {...field} />
          </FormControl>
        )}
      </MetaField>
    </div>
  )
}

/** "Ruoli": the permissions are inherited from them. */
export function UserRolesField({ control, selected }: FieldProps & { selected: EmploymentRelationRef[] }) {
  const { t } = useTranslation()
  return (
    <RelationMultiSelectField
      control={control}
      name="roles"
      metaKey="roles"
      label={t('users.form.roles')}
      resource={ROLES_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('users.form.rolesSearch')}
      selected={toFieldRefs(selected)}
      placeholder={t('users.form.rolesPlaceholder')}
      emptyLabel={t('users.form.rolesEmpty')}
      errorLabel={t('users.form.rolesError')}
      removeLabel={t('users.form.rolesRemove')}
      retryLabel={t('common.retry')}
    />
  )
}

/** A boolean switch row (account active, assignable...): the label and an optional explanation. */
export function UserSwitchField({
  control,
  name,
  label,
  description,
}: FieldProps & {
  name: 'is_active' | 'employment.is_assignable'
  label: string
  description: string
}) {
  return (
    <MetaField
      control={control}
      name={name}
      metaKey={name}
      layout="inline"
      label={label}
      description={<FormDescription>{description}</FormDescription>}
    >
      {({ field, disabled }) => (
        <FormControl>
          <Switch checked={field.value} onCheckedChange={field.onChange} disabled={disabled} />
        </FormControl>
      )}
    </MetaField>
  )
}
