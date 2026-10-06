import { useCallback, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { toast } from 'sonner'
import type { ApiErrorResponse } from '@/api/types'
import {
  createOutboundEmailDraft,
  createOutboundEmailReminder,
  outboundEmailQueryKey,
  outboundEmailsListQueryKey,
} from '@/features/outbound-emails/api'
import type { EmailOwnerRef, OutboundEmail } from '@/features/outbound-emails/types'

export type InvoiceEmailKind = 'email' | 'remind'

export interface InvoiceComposerTarget {
  invoiceId: number
  emailId: number
  /** True for the plain email draft (empty-close cleans it up); false for the reminder, already prefilled server-side. */
  justCreated: boolean
}

interface StartVariables {
  invoiceId: number
  kind: InvoiceEmailKind
}

const ownerOf = (invoiceId: number): EmailOwnerRef => ({ type: 'invoices', id: invoiceId })

/**
 * Row actions `email` / `remind` (spec 0195 D-12/D-13): creates the draft (PDF
 * attached) or the reminder draft for a row and exposes the composer target the
 * caller mounts `OutboundEmailComposerDialog` on. 409 (not overdue) and any
 * other failure surface as a toast with the server message.
 */
export function useInvoiceEmailFlow() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [target, setTarget] = useState<InvoiceComposerTarget | null>(null)

  const start = useMutation<OutboundEmail, unknown, StartVariables>({
    mutationFn: ({ invoiceId, kind }) =>
      kind === 'email'
        ? createOutboundEmailDraft(ownerOf(invoiceId), { attach_pdf: true })
        : createOutboundEmailReminder(ownerOf(invoiceId)),
    onSuccess: (email, { invoiceId, kind }) => {
      const owner = ownerOf(invoiceId)
      queryClient.setQueryData(outboundEmailQueryKey(owner, email.id), email)
      void queryClient.invalidateQueries({ queryKey: outboundEmailsListQueryKey(owner) })
      setTarget({ invoiceId, emailId: email.id, justCreated: kind === 'email' })
    },
    onError: (error) => {
      const message = axios.isAxiosError<ApiErrorResponse>(error) ? error.response?.data?.message : undefined
      toast.error(message ?? t('invoices.document.emailError'))
    },
  })

  const { mutate } = start
  const startEmail = useCallback((invoiceId: number) => mutate({ invoiceId, kind: 'email' }), [mutate])
  const startReminder = useCallback((invoiceId: number) => mutate({ invoiceId, kind: 'remind' }), [mutate])
  const closeComposer = useCallback(() => setTarget(null), [])

  return { startEmail, startReminder, isStarting: start.isPending, target, closeComposer }
}
