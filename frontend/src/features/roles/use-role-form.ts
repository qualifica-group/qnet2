import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { createRole, updateRole } from '@/features/roles/api'
import {
  buildCreateRoleSchema,
  buildUpdateRoleSchema,
  type CreateRoleFormValues,
  type UpdateRoleFormValues,
} from '@/features/roles/role-schema'
import { buildCreatePayload, buildUpdatePayload } from '@/features/roles/role-form-payload'
import type { RoleDetail } from '@/features/roles/types'
import type { RoleFormMode } from '@/features/roles/role-form'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name', 'permissions', 'users', 'field_permissions'] as const

export type RoleFormValues = CreateRoleFormValues & UpdateRoleFormValues

interface UseRoleFormArgs {
  mode: RoleFormMode
  onSuccess: (role: RoleDetail) => void
}

/**
 * Owns every non-render concern of `RoleForm`: RHF/Zod wiring, the
 * field-permission matrix gating (spec 0006) and server 422 mapping. The
 * permission explorer's own tree/search/selection state lives in
 * `permission-explorer/` (spec 0076) — this hook only exposes the flat
 * `permissions`/`field_permissions` RHF fields it reads/writes. The
 * component stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useRoleForm({ mode, onSuccess }: UseRoleFormArgs) {
  const { t } = useTranslation()
  const { canResource } = useResourcePermissions()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  // Custom fields (spec 0021): the single reusable integration — builds the
  // dynamic schema, defaults and 422 paths; `<CustomFieldsSection>` renders.
  const customFields = useCustomFieldsForm(
    'roles',
    mode.type === 'edit'
      ? { type: 'edit', customFields: mode.role.custom_fields }
      : { type: 'create' },
  )

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateRoleSchema(t, customFields.schema)
        : buildCreateRoleSchema(t, customFields.schema),
    [isEdit, t, customFields.schema],
  )

  // Field-permission matrix (spec 0006): there is no dedicated backend
  // field/action key for this section (the 0004 `permissions` envelope is
  // unchanged by this feature), so it reuses the resource-level write
  // ability already in `ResourcePermissions.resource` — the same ceiling
  // rule that locks `name`/`permissions`/`users` when the actor cannot write
  // the role at all. Hidden entirely when the actor cannot manage it (AC15).
  const canManageFieldPermissions = isEdit ? canResource('update') : canResource('create')

  const defaultValues = useMemo<RoleFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.role.name,
        permissions: mode.role.permissions,
        // Hydrate current members from the role detail (`RoleResource.users`);
        // fall back to an empty selection if the field is absent.
        users: mode.role.users ?? [],
        field_permissions: mode.role.field_permissions,
        custom_fields: customFields.defaultValues,
      }
    }
    return {
      name: '',
      permissions: [],
      users: [],
      field_permissions: [],
      custom_fields: customFields.defaultValues,
    }
  }, [mode, customFields.defaultValues])

  const form = useForm<RoleFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: RoleFormValues) => {
    setServerError(null)
    const errorFields: Path<RoleFormValues>[] = [
      ...SERVER_ERROR_FIELDS,
      ...(customFields.errorPaths as Path<RoleFormValues>[]),
    ]
    try {
      const saved =
        mode.type === 'edit'
          ? await updateRole(mode.role.id, buildUpdatePayload(values, mode.role))
          : await createRole(buildCreatePayload(values))

      toast.success(
        t(mode.type === 'edit' ? 'roles.form.updated' : 'roles.form.created'),
      )
      onSuccess(saved)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('roles.form.genericError'))
      }
    }
  }

  return {
    t,
    form,
    isEdit,
    serverError,
    onSubmit,
    canManageFieldPermissions,
  }
}
