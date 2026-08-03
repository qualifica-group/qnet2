import { ShieldCheck } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Form, FormControl } from '@/components/ui/form'
import { RelationMultiSelectField } from '@/components/form/relation-multi-select-field'
import { FormSection } from '@/components/form-section'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { MetaField } from '@/features/authorization/MetaField'
import { PermissionsSection } from '@/features/roles/permission-explorer/permissions-section'
import { useRoleForm } from '@/features/roles/use-role-form'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import type { RoleFormMode } from '@/features/roles/role-form'
import type { RoleDetail } from '@/features/roles/types'

interface RoleFormBodyProps {
  mode: RoleFormMode
  onSuccess: (role: RoleDetail) => void
  onCancel: () => void
}

/**
 * The role create/edit form UI. Every field is wrapped in `MetaField` (spec
 * 0004): hidden fields are absent, non-editable fields render disabled,
 * `required` comes from the resolved `ResourcePermissions` — no hardcoded
 * permission logic lives here. All non-render logic lives in `useRoleForm`.
 * Presentation is grouped into three `FormSection` cards: role details, the
 * two-panel permission explorer (spec 0076 — `PermissionsSection`, which
 * unifies the module actions and its field-permission matrix in one scheda),
 * and `<CustomFieldsSection>` (spec 0021), which mounts the resource's
 * admin-defined custom fields with zero roles-specific rendering/validation
 * logic.
 */
export function RoleFormBody({ mode, onSuccess, onCancel }: RoleFormBodyProps) {
  const { t, form, serverError, onSubmit, canManageFieldPermissions } = useRoleForm({ mode, onSuccess })

  return (
    <Form {...form}>
      <form
        onSubmit={form.handleSubmit(onSubmit)}
        className="flex flex-1 flex-col gap-4 overflow-y-auto p-4"
        noValidate
      >
        <FormSection
          icon={ShieldCheck}
          title={t('roles.form.sections.details.title')}
          description={t('roles.form.sections.details.description')}
        >
          <MetaField control={form.control} name="name" metaKey="name" label={t('roles.form.name')}>
            {({ field, disabled, readOnly }) => (
              <FormControl>
                <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
              </FormControl>
            )}
          </MetaField>

          <RelationMultiSelectField
            control={form.control}
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
        </FormSection>

        <PermissionsSection control={form.control} canManageFieldPermissions={canManageFieldPermissions} />

        <CustomFieldsSection resource="roles" control={form.control} />

        {serverError && (
          <p className="text-sm font-medium text-destructive" role="alert">
            {serverError}
          </p>
        )}

        <div className="mt-auto flex justify-end gap-2 pt-2">
          <Button
            type="button"
            variant="outline"
            onClick={onCancel}
            disabled={form.formState.isSubmitting}
          >
            {t('roles.form.cancel')}
          </Button>
          <Button type="submit" disabled={form.formState.isSubmitting}>
            {form.formState.isSubmitting
              ? t('roles.form.saving')
              : t('roles.form.save')}
          </Button>
        </div>
      </form>
    </Form>
  )
}
