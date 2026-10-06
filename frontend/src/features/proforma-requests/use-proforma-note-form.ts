import { useMemo } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { buildProformaNoteSchema, type ProformaNoteFormValues } from '@/features/proforma-requests/proforma-note-schema'

/** RHF + Zod wiring of the one-field note form, shared by the "€" modal and the edit form. */
export function useProformaNoteForm(defaultNote: string) {
  const { t } = useTranslation()
  const schema = useMemo(() => buildProformaNoteSchema(t), [t])

  return useForm<ProformaNoteFormValues>({
    resolver: zodResolver(schema),
    defaultValues: { note: defaultNote },
  })
}
