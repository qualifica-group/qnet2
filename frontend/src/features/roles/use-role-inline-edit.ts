import { useMemo, useState } from 'react'
import { get, type FieldErrors } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { firstErrorMessage } from '@/components/record-form/first-error-message'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { roleDetailQueryKey, updateRole } from '@/features/roles/api'
import { buildUpdatePayload } from '@/features/roles/role-form-payload'
import { useRoleFormState, type RoleFormMode, type RoleFormValues } from '@/features/roles/use-role-form'
import type { RoleDetailWithPermissions } from '@/features/roles/types'

/** The permissions block row's key: the explorer edits `permissions` and, inside it, the `field_permissions` matrix. */
export const ROLE_PERMISSIONS_FIELD = 'permissions'

/**
 * Drives the role detail's in-place editing (spec 0230): ONE row open at a
 * time, and Save submits the form, whose diff-based PATCH
 * (`buildUpdatePayload`) sends only what that row changed (a closed row never
 * differs from the saved role). The saved role, with its authorization
 * metadata, replaces the cached detail. Opening another row first discards
 * the unconfirmed one: an edit is never carried silently into another save.
 */
export function useRoleInlineEdit(role: RoleDetailWithPermissions, onChanged?: () => void) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [editingField, setEditingField] = useState<string | null>(null)
  const [serverError, setServerError] = useState<string | null>(null)
  const [isSaving, setIsSaving] = useState(false)
  // Stable per persisted role: the form re-syncs its values on a new one.
  const mode = useMemo<RoleFormMode>(() => ({ type: 'edit', role }), [role])
  const { form, errorFields, canManageFieldPermissions } = useRoleFormState(mode)

  // `keepDirtyValues: false`: the form's `resetOptions` keep dirty values for
  // the background re-sync, but here the unconfirmed edit must really go.
  const discard = () => {
    form.reset(undefined, { keepDirtyValues: false })
    setServerError(null)
  }

  const start = (field: string) => {
    discard()
    setEditingField(field)
  }

  const cancel = () => {
    discard()
    setEditingField(null)
  }

  const persist = async (values: RoleFormValues) => {
    const payload = buildUpdatePayload(values, role)
    // Nothing changed: close the row without a pointless request.
    if (Object.keys(payload).length === 0) {
      setEditingField(null)
      return
    }
    setIsSaving(true)
    setServerError(null)
    try {
      const saved = await updateRole(role.id, payload)
      queryClient.setQueryData(roleDetailQueryKey(role.id), saved)
      toast.success(t('roles.form.updated'))
      setEditingField(null)
      onChanged?.()
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('roles.form.genericError'))
      }
    } finally {
      setIsSaving(false)
    }
  }

  // A rule failing on a field the open editor does not show has no message on screen: say it.
  const handleInvalid = (errors: FieldErrors<RoleFormValues>) => {
    if (editingField === null || get(errors, editingField) === undefined) {
      toast.error(firstErrorMessage(errors) ?? t('roles.form.genericError'))
    }
  }

  const inline: InlineEdit = {
    editingField,
    start,
    cancel,
    save: () => void form.handleSubmit(persist, handleInvalid)(),
    dismiss: cancel,
    isSaving,
    error: serverError,
    confirmLabel: t('common.inlineEdit.save'),
    cancelLabel: t('common.inlineEdit.cancel'),
  }

  return { form, inline, canManageFieldPermissions }
}
