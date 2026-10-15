import { useMemo, useState } from 'react'
import { useForm, type Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  createEnasarcoParameter,
  enasarcoParameterKeys,
  updateEnasarcoParameter,
} from '@/features/enasarco-parameters/api'
import {
  EMPTY_FORM_VALUES,
  buildEnasarcoParameterPayload,
  buildEnasarcoParameterUpdatePayload,
  toFormValues,
} from '@/features/enasarco-parameters/enasarco-parameter-form-payload'
import {
  buildEnasarcoParameterSchema,
  type EnasarcoParameterFormValues,
  type EnasarcoParameterSchemaOutput,
} from '@/features/enasarco-parameters/enasarco-parameter-schema'
import type { EnasarcoParameterDetail, EnasarcoParameterFormMode } from '@/features/enasarco-parameters/types'

/** Server-side field names mapped onto the form for 422 handling (a duplicated year lands on `year`). */
const SERVER_ERROR_FIELDS: Path<EnasarcoParameterFormValues>[] = [
  'year',
  'total_rate',
  'agent_share_percent',
  'single_firm_ceiling',
  'multi_firm_ceiling',
  'notes',
]

interface UseEnasarcoParameterFormArgs {
  mode: EnasarcoParameterFormMode
  /** Called after a successful create/update so the caller can close the row or navigate. */
  onSuccess: (parameter: EnasarcoParameterDetail) => void
}

/**
 * Owns every non-render concern of the ENASARCO parameters form: RHF/Zod
 * wiring, defaults, 422 mapping and the submit. Create backs the create
 * draft; edit backs the detail's in-place rows (diff PATCH, spec 0264 D-5).
 */
export function useEnasarcoParameterForm({ mode, onSuccess }: UseEnasarcoParameterFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const schema = useMemo(() => buildEnasarcoParameterSchema(t), [t])
  const defaultValues = useMemo<EnasarcoParameterFormValues>(
    () => (mode.type === 'edit' ? toFormValues(mode.parameter) : EMPTY_FORM_VALUES),
    [mode],
  )

  // Edit backs the detail's in-place rows: a saved record re-syncs the form,
  // `keepDirtyValues` spares a row still open over a background refetch.
  const form = useForm<EnasarcoParameterFormValues, unknown, EnasarcoParameterSchemaOutput>({
    resolver: zodResolver(schema),
    defaultValues,
    values: mode.type === 'edit' ? defaultValues : undefined,
    resetOptions: { keepDirtyValues: true },
  })

  const onSubmit = async (values: EnasarcoParameterSchemaOutput) => {
    setServerError(null)
    try {
      if (mode.type === 'edit') {
        const saved = await updateEnasarcoParameter(
          mode.parameter.id,
          buildEnasarcoParameterUpdatePayload(values, mode.parameter),
        )
        queryClient.setQueryData(enasarcoParameterKeys.detail(mode.parameter.id), saved)
        toast.success(t('enasarcoParameters.form.updated'))
        onSuccess(saved)
        return
      }
      const created = await createEnasarcoParameter(buildEnasarcoParameterPayload(values))
      toast.success(t('enasarcoParameters.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, SERVER_ERROR_FIELDS)) {
        setServerError(t('enasarcoParameters.form.genericError'))
      }
    }
  }

  return { form, serverError, clearServerError: () => setServerError(null), onSubmit }
}
