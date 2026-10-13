import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  apiClientToFormValues,
  buildCreatePayload,
  buildUpdatePayload,
  EMPTY_API_CLIENT_FORM,
} from '@/features/api-integrations/api-client-form-values'
import {
  buildApiClientSchema,
  type ApiClientFormValues,
} from '@/features/api-integrations/api-client-schema'
import type { ApiKeyReveal } from '@/features/api-integrations/components/api-key-dialog'
import { useCreateApiClient, useUpdateApiClient } from '@/features/api-integrations/use-api-clients'
import type { ApiClient } from '@/features/api-integrations/types'

const SERVER_ERROR_FIELDS: Path<ApiClientFormValues>[] = [
  'name',
  'description',
  'rate_limit_per_minute',
  'expires_at',
  'is_active',
]

interface UseApiClientFormArgs {
  /** The client being edited, or `null` to create one. */
  client: ApiClient | null
  onKeyIssued: (reveal: ApiKeyReveal) => void
  onSaved: () => void
}

/** RHF/Zod wiring and the create/update submit of the API client dialog. */
export function useApiClientForm({ client, onKeyIssued, onSaved }: UseApiClientFormArgs) {
  const { t } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)
  const createMutation = useCreateApiClient()
  const updateMutation = useUpdateApiClient(client?.id ?? 0)

  const initial = useMemo(
    () => (client ? apiClientToFormValues(client) : EMPTY_API_CLIENT_FORM),
    [client],
  )
  const schema = useMemo(() => buildApiClientSchema(t, initial.expires_at), [t, initial])
  const form = useForm<ApiClientFormValues>({ resolver: zodResolver(schema), defaultValues: initial })

  const submit = async (values: ApiClientFormValues) => {
    if (client) {
      await updateMutation.mutateAsync(buildUpdatePayload(values, initial))
      toast.success(t('apiIntegrations.form.updated'))
      return
    }
    // The key goes straight to the caller's local state and out of the mutation cache.
    const result = await createMutation.mutateAsync(buildCreatePayload(values))
    createMutation.reset()
    toast.success(t('apiIntegrations.form.created'))
    onKeyIssued({ clientName: result.client.name, plainTextKey: result.plain_text_key, rotated: false })
  }

  const onSubmit = async (values: ApiClientFormValues) => {
    setServerError(null)
    try {
      await submit(values)
      onSaved()
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, SERVER_ERROR_FIELDS)) {
        setServerError(t('apiIntegrations.form.saveError'))
      }
    }
  }

  return { form, serverError, onSubmit }
}
