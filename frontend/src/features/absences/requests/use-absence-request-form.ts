import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  createAbsenceRequest,
  submitAbsenceRequest,
  updateAbsenceRequest,
  uploadAbsenceAttachment,
} from '@/features/absences/api'
import {
  EMPTY_ABSENCE_FORM,
  absenceFormValues,
  toAbsencePayload,
  toCreatePayload,
} from '@/features/absences/requests/absence-request-payload'
import {
  buildAbsenceRequestSchema,
  type AbsenceRequestFormValues,
} from '@/features/absences/requests/absence-request-schema'
import { fieldError, serverMessage } from '@/features/absences/requests/absence-server-errors'
import { useAbsenceRequestPreview } from '@/features/absences/requests/use-absence-request-preview'
import { useAbsenceRefresh } from '@/features/absences/requests/use-absence-refresh'
import type { AbsenceRequest } from '@/features/absences/types'

/** Server-side field names mapped onto the form for 422 handling (`is_full_day` is the mode, `user_id` has its own toast). */
const SERVER_ERROR_FIELDS = [
  'absence_type_id',
  'day_part',
  'starts_on',
  'ends_on',
  'requested_minutes',
  'note',
] as const

interface UseAbsenceRequestFormArgs {
  /** The draft being edited; omitted for a new request. */
  request?: AbsenceRequest
  refreshGrid?: () => void
  onSaved: (saved: AbsenceRequest) => void
}

/**
 * Owns every non-render concern of the request form: RHF/Zod wiring, the
 * create/update + optional submit + optional attachment sequence and the 422
 * mapping. "Salva bozza" and "Invia" share one orchestration (`persist`).
 */
export function useAbsenceRequestForm({ request, refreshGrid, onSaved }: UseAbsenceRequestFormArgs) {
  const { t } = useTranslation()
  const refresh = useAbsenceRefresh(refreshGrid)
  const [file, setFile] = useState<File | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  const schema = useMemo(() => buildAbsenceRequestSchema(t), [t])
  const form = useForm<AbsenceRequestFormValues>({
    resolver: zodResolver(schema),
    defaultValues: request ? absenceFormValues(request) : EMPTY_ABSENCE_FORM,
  })

  function writeRequest(values: AbsenceRequestFormValues, send: boolean): Promise<AbsenceRequest> | null {
    if (request) {
      const payload = toAbsencePayload(values)
      return payload === null ? null : updateAbsenceRequest(request.id, payload)
    }
    const payload = toCreatePayload(values, send)
    return payload === null ? null : createAbsenceRequest(payload)
  }

  async function save(values: AbsenceRequestFormValues, send: boolean): Promise<AbsenceRequest | null> {
    // Step 1: create (the server submits in the same call) or update the draft
    const written = writeRequest(values, send)
    if (written === null) {
      return null
    }
    let saved = await written
    // Step 2: an edited draft is sent with its own action
    if (request && send) {
      saved = await submitAbsenceRequest(request.id)
    }
    // Step 3: the optional attachment goes up once the request exists; a failure keeps the request
    if (file) {
      try {
        saved = await uploadAbsenceAttachment(saved.id, file)
      } catch {
        toast.error(t('absences.requests.form.attachmentError'))
      }
    }
    return saved
  }

  async function persist(values: AbsenceRequestFormValues, send: boolean) {
    setIsSubmitting(true)
    try {
      const saved = await save(values, send)
      if (saved) {
        refresh(saved.id)
        toast.success(t(send ? 'absences.requests.form.sent' : 'absences.requests.form.draftSaved'))
        onSaved(saved)
      }
    } catch (error) {
      const handled = applyServerValidationErrors(error, form.setError, [...SERVER_ERROR_FIELDS])
      const modeError = fieldError(error, 'is_full_day')
      if (modeError !== null) {
        form.setError('mode', { message: modeError })
      }
      // `user_id` (no approver for the person) has no input: it goes to a toast
      const unmapped = fieldError(error, 'user_id')
      if (unmapped !== null) {
        toast.error(unmapped)
      } else if (!handled) {
        toast.error(serverMessage(error) ?? t('absences.requests.form.error'))
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  const saveDraft = form.handleSubmit((values) => persist(values, false))
  const send = form.handleSubmit((values) => persist(values, true))

  const preview = useAbsenceRequestPreview(form, request?.id ?? null)

  return { form, preview, file, setFile, isSubmitting, saveDraft, send }
}
