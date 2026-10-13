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
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (parameter: EnasarcoParameterDetail) => void
}

/** Owns every non-render concern of `EnasarcoParameterFormBody`: RHF/Zod wiring, defaults, 422 mapping, submit. */
export function useEnasarcoParameterForm({ mode, onSuccess }: UseEnasarcoParameterFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const schema = useMemo(() => buildEnasarcoParameterSchema(t), [t])
  const defaultValues = useMemo<EnasarcoParameterFormValues>(
    () => (mode.type === 'edit' ? toFormValues(mode.parameter) : EMPTY_FORM_VALUES),
    [mode],
  )

  const form = useForm<EnasarcoParameterFormValues, unknown, EnasarcoParameterSchemaOutput>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: EnasarcoParameterSchemaOutput) => {
    setServerError(null)
    const payload = buildEnasarcoParameterPayload(values)
    try {
      if (mode.type === 'edit') {
        const saved = await updateEnasarcoParameter(mode.parameter.id, payload)
        queryClient.setQueryData(enasarcoParameterKeys.detail(mode.parameter.id), saved)
        toast.success(t('enasarcoParameters.form.updated'))
        onSuccess(saved)
        return
      }
      const created = await createEnasarcoParameter(payload)
      toast.success(t('enasarcoParameters.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, SERVER_ERROR_FIELDS)) {
        setServerError(t('enasarcoParameters.form.genericError'))
      }
    }
  }

  return { form, serverError, onSubmit }
}
