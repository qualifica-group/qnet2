import { useMemo } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'
import { useUserFormDefaults } from '@/features/users/user-form-defaults'
import {
  buildCreateUserSchema,
  buildUpdateUserSchema,
  type CreateUserFormValues,
  type UpdateUserFormValues,
} from '@/features/users/user-schema'
import type { UserFormMode } from '@/features/users/types'

export type UserFormValues = CreateUserFormValues & UpdateUserFormValues

interface UseUserFormArgs {
  mode: UserFormMode
}

/**
 * Owns the RHF/Zod wiring of the user create form and of the in-place detail
 * (`useUserInlineEdit`, edit mode), custom fields included (spec 0021). The
 * anagraphic card lives outside RHF (a buffered draft); submission lives in
 * `useUserFormSubmit`.
 *
 * Edit mode IS the user detail (spec 0222): the persisted record can change
 * under the form, so `values` re-syncs it while `keepDirtyValues` preserves
 * the row still being edited.
 */
export function useUserForm({ mode }: UseUserFormArgs) {
  const { t } = useTranslation()
  const isEdit = mode.type === 'edit'

  const customFields = useCustomFieldsForm(
    'users',
    mode.type === 'edit' ? { type: 'edit', customFields: mode.user.custom_fields } : { type: 'create' },
  )

  const schema = useMemo(
    () => (isEdit ? buildUpdateUserSchema(t, customFields.schema) : buildCreateUserSchema(t, customFields.schema)),
    [isEdit, t, customFields.schema],
  )

  const defaultValues = useUserFormDefaults(mode, customFields.defaultValues)

  const form = useForm<UserFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: isEdit ? { keepDirtyValues: true } : undefined,
  })

  return { form, customFieldErrorPaths: customFields.errorPaths }
}
