import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { createRole } from '@/features/roles/api'
import {
  buildCreateRoleSchema,
  buildUpdateRoleSchema,
  type CreateRoleFormValues,
  type UpdateRoleFormValues,
} from '@/features/roles/role-schema'
import { buildCreatePayload } from '@/features/roles/role-form-payload'
import type { RoleDetail, RoleDetailWithPermissions } from '@/features/roles/types'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name', 'permissions', 'users', 'field_permissions'] as const

export type RoleFormValues = CreateRoleFormValues & UpdateRoleFormValues

/** `edit` is the in-place detail (spec 0230); the create form is the only screen left on `create`. */
export type RoleFormMode = { type: 'create' } | { type: 'edit'; role: RoleDetailWithPermissions }

const CREATE_MODE: RoleFormMode = { type: 'create' }

/**
 * The RHF/Zod wiring shared by the create form and the detail's in-place
 * rows: schema (custom fields included, spec 0021), defaults seeded from the
 * role being edited, the 422 field list and the field-permission matrix
 * gating (spec 0006). `mode` must be referentially stable per role: the form
 * re-syncs its values on a new one.
 */
export function useRoleFormState(mode: RoleFormMode) {
  const { t } = useTranslation()
  const { canResource } = useResourcePermissions()
  const isEdit = mode.type === 'edit'

  // Custom fields (spec 0021): the single reusable integration — builds the
  // dynamic schema, defaults and 422 paths; `<CustomFieldRecordSections>` renders.
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
    // Edit: `values` re-syncs the rows on the saved role, `keepDirtyValues` spares an open editor.
    ...(isEdit ? { values: defaultValues, resetOptions: { keepDirtyValues: true } } : { defaultValues }),
  })

  const errorFields: Path<RoleFormValues>[] = [
    ...SERVER_ERROR_FIELDS,
    ...(customFields.errorPaths as Path<RoleFormValues>[]),
  ]

  return { form, errorFields, canManageFieldPermissions }
}

interface UseRoleFormArgs {
  onSuccess: (role: RoleDetail) => void
}

/**
 * Owns every non-render concern of the role create form: the shared form
 * state plus the create submit and server 422 mapping. The permission
 * explorer's own tree/search/selection state lives in `permission-explorer/`
 * (spec 0076) — this hook only exposes the flat `permissions`/`field_permissions`
 * RHF fields it reads/writes. The component stays UI-only; this hook is the
 * orchestration point (`onSubmit`).
 */
export function useRoleForm({ onSuccess }: UseRoleFormArgs) {
  const { t } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)
  const { form, errorFields, canManageFieldPermissions } = useRoleFormState(CREATE_MODE)

  const onSubmit = async (values: RoleFormValues) => {
    setServerError(null)
    try {
      const saved = await createRole(buildCreatePayload(values))

      toast.success(t('roles.form.created'))
      onSuccess(saved)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('roles.form.genericError'))
      }
    }
  }

  return { form, serverError, onSubmit, canManageFieldPermissions }
}
