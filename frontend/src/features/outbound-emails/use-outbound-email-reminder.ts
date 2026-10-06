import { useState } from 'react'
import { useCreateOutboundEmailReminder } from '@/features/outbound-emails/use-outbound-email-draft'
import type { EmailOwnerRef } from '@/features/outbound-emails/types'

/**
 * Invoice reminder flow (spec 0195 D-13): creates the `purpose=reminder`
 * draft, then exposes its id so the caller mounts
 * `OutboundEmailComposerDialog` on it (`justCreated={false}`,
 * `prefillDefaultTo`). A 409 (invoice not overdue) rejects the mutation and
 * leaves `draftId` null: the caller surfaces the error.
 */
export function useOutboundEmailReminder(owner: EmailOwnerRef) {
  const [draftId, setDraftId] = useState<number | null>(null)
  const create = useCreateOutboundEmailReminder(owner)

  const startReminder = (emailTemplateId?: number | null) =>
    create.mutate(emailTemplateId ? { email_template_id: emailTemplateId } : undefined, {
      onSuccess: (email) => setDraftId(email.id),
    })

  return {
    startReminder,
    isStarting: create.isPending,
    error: create.error,
    draftId,
    closeComposer: () => setDraftId(null),
  }
}
