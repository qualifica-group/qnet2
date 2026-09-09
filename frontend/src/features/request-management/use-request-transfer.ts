import { useCallback, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import type { AssignOperatorsDialogInput } from '@/features/leads/assign-operators-dialog'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { transferRequests } from '@/features/request-management/api'
import { requestManagementKeys } from '@/features/request-management/query-keys'
import { useQuoteOperatorCompetence } from '@/features/request-management/use-quote-operator-competence'
import type { RequestWorkPanel } from '@/features/request-management/types'

/**
 * State + mutation behind the work panel's own "Trasferisci contatto"
 * button (spec 0079 addendum, user directive: same action repeated next to
 * Save in the sticky header and the footer). A single-record transfer, on
 * the SAME `POST /request-management/transfer` endpoint and
 * `AssignOperatorsDialog` (`lockedMode="single"`) the table's row/bulk
 * actions already drive. Extracted out of `RequestWorkPanelBody`
 * (engineering.md §1.3/§2) so the panel component stays render-only.
 */
export function useRequestTransfer(panel: RequestWorkPanel) {
  const { t } = useTranslation()
  const { canAction } = useResourcePermissions()
  const queryClient = useQueryClient()
  const [isOpen, setIsOpen] = useState(false)

  // `transfer_contact` already reflects the endpoint's own double gate
  // (`request-management.update` AND `.transferContact`,
  // `RequestManagementAuthorization::actionPermissions`) — the server owns
  // that combined rule, so it is not re-derived here: doing so would be a
  // second copy of the same predicate that can silently drift from the one
  // that actually decides the 403.
  const canTransfer = canAction('transfer_contact')

  const transferMutation = useMutation({
    mutationFn: (input: AssignOperatorsDialogInput) =>
      transferRequests({
        request_ids: [panel.id],
        operational_site_id: input.operational_site_id,
        operator_id: input.operator_id as number,
      }),
    onSuccess: (result) => {
      toast.success(t('requestManagement.transfer.success', { count: result.transferred }))
      // Transferring the panel's OWN request can move it out of the actor's
      // D-3 scope, same precedent as reassigning the operator from the
      // attribution section (request-attribution-section.tsx: "Changing the
      // operator REASSIGNS the request..."): the invalidated refetch may then
      // 403 for an actor without `request-management.viewAll` — the intended
      // semantics of handing the request over, not a bug to guard against.
      void queryClient.invalidateQueries({ queryKey: requestManagementKeys.panel(panel.id) })
    },
  })

  const handleTransfer = useCallback(
    async (input: AssignOperatorsDialogInput) => {
      try {
        await transferMutation.mutateAsync(input)
      } catch (error) {
        toast.error(t('requestManagement.transfer.errors.generic'))
        throw error
      }
    },
    [transferMutation, t],
  )

  // Same competence filter as the table's transfer/assign popups (spec 0110
  // AC-041): this endpoint writes the very same GA2 Operatore slot, on this
  // one offer.
  const competence = useQuoteOperatorCompetence([panel.id], isOpen)

  const copy = useMemo(
    () => ({
      title: t('requestManagement.transfer.title'),
      description: t('requestManagement.transfer.description', { count: 1 }),
      modeHints: { balanced: '', single: '' },
    }),
    [t],
  )

  return {
    canTransfer,
    isOpen,
    open: () => setIsOpen(true),
    onOpenChange: setIsOpen,
    defaultSite: panel.operational_site,
    copy,
    competence,
    handleTransfer,
  }
}
