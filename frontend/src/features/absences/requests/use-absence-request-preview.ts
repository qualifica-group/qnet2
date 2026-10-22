import { useEffect, useMemo } from 'react'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { useWatch, type UseFormReturn } from 'react-hook-form'
import { previewAbsenceRequest } from '@/features/absences/api'
import { absenceKeys } from '@/features/absences/query-keys'
import { toPreviewPayload } from '@/features/absences/requests/absence-request-payload'
import type { AbsenceRequestFormValues } from '@/features/absences/requests/absence-request-schema'
import { fieldError } from '@/features/absences/requests/absence-server-errors'
import type { AbsenceRequestInsight, PreviewAbsenceRequestPayload } from '@/features/absences/types'
import { useDebouncedValue } from '@/hooks/use-debounced-value'

const PREVIEW_DEBOUNCE_MS = 400

/** Server field of a 422 of the preview -> form field that shows it (`is_full_day` is the mode). */
const PREVIEW_ERROR_FIELDS = {
  absence_type_id: 'absence_type_id',
  user_id: 'user_id',
  is_full_day: 'mode',
  day_part: 'day_part',
  starts_on: 'starts_on',
  ends_on: 'ends_on',
  requested_minutes: 'requested_minutes',
} as const satisfies Record<string, keyof AbsenceRequestFormValues>

function previewFieldErrors(error: unknown): Partial<Record<keyof AbsenceRequestFormValues, string>> {
  const errors: Partial<Record<keyof AbsenceRequestFormValues, string>> = {}
  for (const [serverField, formField] of Object.entries(PREVIEW_ERROR_FIELDS)) {
    const message = fieldError(error, serverField)
    if (message !== null) {
      errors[formField] = message
    }
  }
  return errors
}

export interface AbsenceRequestPreview {
  insight: AbsenceRequestInsight | undefined
  isPending: boolean
  /** A failure no field explains (a 422 on a field is shown next to it instead). */
  isError: boolean
}

/**
 * Live preview of the request being filled: runs once the minimum fields are valid, debounced, keeping the
 * previous answer on screen while the next one loads. A 422 lands on the matching field without blocking the send.
 */
export function useAbsenceRequestPreview(
  form: UseFormReturn<AbsenceRequestFormValues>,
  requestId: number | null,
): AbsenceRequestPreview {
  const values = useWatch({ control: form.control }) as AbsenceRequestFormValues
  const payloadKey = useDebouncedValue(JSON.stringify(toPreviewPayload(values, requestId)), PREVIEW_DEBOUNCE_MS)
  const payload = useMemo(() => JSON.parse(payloadKey) as PreviewAbsenceRequestPayload | null, [payloadKey])

  const query = useQuery<AbsenceRequestInsight, AxiosError>({
    queryKey: absenceKeys.preview(payload as PreviewAbsenceRequestPayload),
    queryFn: () => previewAbsenceRequest(payload as PreviewAbsenceRequestPayload),
    enabled: payload !== null,
    placeholderData: keepPreviousData,
    retry: false,
  })

  const fieldErrors = useMemo(() => previewFieldErrors(query.error), [query.error])
  const { setError, clearErrors } = form
  useEffect(() => {
    const fields = Object.entries(fieldErrors) as [keyof AbsenceRequestFormValues, string][]
    for (const [field, message] of fields) {
      setError(field, { type: 'preview', message })
    }
    return () => clearErrors(fields.map(([field]) => field))
  }, [fieldErrors, setError, clearErrors])

  return {
    insight: payload === null ? undefined : query.data,
    isPending: payload !== null && query.isFetching && query.data === undefined,
    isError: query.isError && Object.keys(fieldErrors).length === 0,
  }
}
