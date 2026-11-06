import { useMutation, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import {
  outboundEmailQueryKey,
  outboundEmailsListQueryKey,
  scheduleOutboundEmail,
  unscheduleOutboundEmail,
} from '@/features/outbound-emails/api'
import type { EmailOwnerRef, OutboundEmail, OutboundEmailRecurrenceInput } from '@/features/outbound-emails/types'

/** Both mutations replace the cached detail with the answer and refresh the list (status, next send). */
function useCacheSync(owner: EmailOwnerRef, emailId: number) {
  const queryClient = useQueryClient()
  return (email: OutboundEmail) => {
    queryClient.setQueryData(outboundEmailQueryKey(owner, emailId), email)
    void queryClient.invalidateQueries({ queryKey: outboundEmailsListQueryKey(owner) })
  }
}

/** POST `.../emails/{email}/schedule` (spec 0362): the caller PATCHes the draft first. */
export function useScheduleOutboundEmail(owner: EmailOwnerRef, emailId: number) {
  const sync = useCacheSync(owner, emailId)
  return useMutation<OutboundEmail, AxiosError, OutboundEmailRecurrenceInput>({
    mutationFn: (recurrence) => scheduleOutboundEmail(owner, emailId, recurrence),
    onSuccess: sync,
  })
}

/** POST `.../emails/{email}/unschedule` ("Riporta in bozza", spec 0362 D-8). */
export function useUnscheduleOutboundEmail(owner: EmailOwnerRef, emailId: number) {
  const sync = useCacheSync(owner, emailId)
  return useMutation<OutboundEmail, AxiosError>({
    mutationFn: () => unscheduleOutboundEmail(owner, emailId),
    onSuccess: sync,
  })
}
