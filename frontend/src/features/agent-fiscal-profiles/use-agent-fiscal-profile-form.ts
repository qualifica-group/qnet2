import { useMemo, useState } from 'react'
import { useForm, type Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  agentFiscalProfileKeys,
  createAgentFiscalProfile,
  updateAgentFiscalProfile,
} from '@/features/agent-fiscal-profiles/api'
import {
  EMPTY_FORM_VALUES,
  buildAgentFiscalProfilePayload,
  toFormValues,
} from '@/features/agent-fiscal-profiles/agent-fiscal-profile-form-payload'
import {
  buildAgentFiscalProfileSchema,
  type AgentFiscalProfileFormValues,
  type AgentFiscalProfileSchemaOutput,
} from '@/features/agent-fiscal-profiles/agent-fiscal-profile-schema'
import type { AgentFiscalProfileDetail, AgentFiscalProfileFormMode } from '@/features/agent-fiscal-profiles/types'

/** Server-side field names mapped onto the form for 422 handling (incl. `valid_from` overlapping validity). */
const SERVER_ERROR_FIELDS: Path<AgentFiscalProfileFormValues>[] = [
  'referent_id',
  'withholding_enabled',
  'withholding_rate',
  'withholding_base_percent',
  'enasarco_enabled',
  'enasarco_mandate_type',
  'valid_from',
  'valid_until',
  'notes',
]

interface UseAgentFiscalProfileFormArgs {
  mode: AgentFiscalProfileFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (profile: AgentFiscalProfileDetail) => void
}

/** Owns every non-render concern of `AgentFiscalProfileForm`: RHF/Zod wiring, defaults, 422 mapping, submit. */
export function useAgentFiscalProfileForm({ mode, onSuccess }: UseAgentFiscalProfileFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const schema = useMemo(() => buildAgentFiscalProfileSchema(t), [t])

  const defaultValues = useMemo<AgentFiscalProfileFormValues>(
    () => (mode.type === 'edit' ? toFormValues(mode.profile) : EMPTY_FORM_VALUES),
    [mode],
  )

  const form = useForm<AgentFiscalProfileFormValues, unknown, AgentFiscalProfileSchemaOutput>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: AgentFiscalProfileSchemaOutput) => {
    setServerError(null)
    const payload = buildAgentFiscalProfilePayload(values)
    try {
      if (mode.type === 'edit') {
        const saved = await updateAgentFiscalProfile(mode.profile.id, payload)
        queryClient.setQueryData(agentFiscalProfileKeys.detail(mode.profile.id), saved)
        toast.success(t('agentFiscalProfiles.form.updated'))
        onSuccess(saved)
        return
      }
      const created = await createAgentFiscalProfile(payload)
      toast.success(t('agentFiscalProfiles.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, SERVER_ERROR_FIELDS)) {
        setServerError(t('agentFiscalProfiles.form.genericError'))
      }
    }
  }

  return { form, serverError, onSubmit }
}
