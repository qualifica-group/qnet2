import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import axios from 'axios'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { companyClosureKeys, createCompanyClosure, updateCompanyClosure } from '@/features/company-closures/api'
import { buildPayload, CREATE_DEFAULTS, toFormValues } from '@/features/company-closures/company-closure-form-payload'
import { buildCompanyClosureSchema, type CompanyClosureFormValues } from '@/features/company-closures/company-closure-schema'
import type { CompanyClosure } from '@/features/company-closures/types'

const CONFLICT_STATUS = 409

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS: Path<CompanyClosureFormValues>[] = [
  'name',
  'starts_on',
  'ends_on',
  'kind',
  'absence_type_id',
  'applies_to_all_sites',
  'operational_site_ids',
  'repeats_yearly',
]

interface UseCompanyClosureFormArgs {
  /** The closure being edited; absent = create. */
  closure?: CompanyClosure
  /** Called after a successful save, with the saved closure (its `generation` summary included). */
  onSaved: (closure: CompanyClosure) => void
}

/**
 * Owns every non-render concern of the closure form: RHF/Zod wiring, defaults, 422/409 mapping, submit.
 */
export function useCompanyClosureForm({ closure, onSaved }: UseCompanyClosureFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const schema = useMemo(() => buildCompanyClosureSchema(t), [t])
  const defaultValues = useMemo(() => (closure ? toFormValues(closure) : CREATE_DEFAULTS), [closure])
  const form = useForm<CompanyClosureFormValues>({ resolver: zodResolver(schema), defaultValues })

  const onSubmit = async (values: CompanyClosureFormValues) => {
    setServerError(null)
    try {
      const payload = buildPayload(values)
      const saved = closure ? await updateCompanyClosure(closure.id, payload) : await createCompanyClosure(payload)
      void queryClient.invalidateQueries({ queryKey: companyClosureKeys.all })
      toast.success(t(closure ? 'companyClosures.form.updated' : 'companyClosures.form.created'))
      onSaved(saved)
    } catch (error) {
      if (axios.isAxiosError(error) && error.response?.status === CONFLICT_STATUS) {
        setServerError(t('companyClosures.form.startedLocked'))
      } else if (!applyServerValidationErrors(error, form.setError, SERVER_ERROR_FIELDS)) {
        setServerError(t('companyClosures.form.genericError'))
      }
    }
  }

  return { form, serverError, onSubmit }
}
