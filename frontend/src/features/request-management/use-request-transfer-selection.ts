import { useCallback, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation } from '@tanstack/react-query'
import { toast } from 'sonner'
import type {
  AssignOperatorsDialogInput,
  AssignOperatorsDialogSite,
} from '@/features/leads/assign-operators-dialog'
import { useAbilities } from '@/features/auth/use-abilities'
import { transferRequests } from '@/features/request-management/api'
import type { TransferRequestsPayload } from '@/features/request-management/request-write-types'
import { useQuoteAssignmentScope } from '@/features/request-management/use-quote-assignment-scope'
import type { TableSelection } from '@/features/table/use-bulk-actions-slot'
import type { TableRow } from '@/features/table/types'

/**
 * The Sede to precompile in the "Trasferisci contatto" popup, the only surface
 * left where the user picks one (spec 0113 D-2: there it is the transfer
 * DESTINATION): present only when every selected row's `operational_site` (the
 * `{id, label}` shape the definition projects onto the grid row, or null)
 * shares one non-null id.
 */
function resolveSharedOperationalSite(rows: TableRow[]): AssignOperatorsDialogSite | null {
  const [first, ...rest] = rows.map(
    (row) => row.operational_site as AssignOperatorsDialogSite | null,
  )
  if (!first) {
    return null
  }
  return rest.every((site) => site?.id === first.id) ? first : null
}

/**
 * State + mutation behind the table's "Trasferisci contatto" (spec 0079): the
 * row action and the bulk action share ONE dialog and one mutation, a row
 * transfer being just a one-element selection. Extracted out of
 * `RequestManagementTable` (engineering.md §6: the table crossed the file size
 * threshold when spec 0113 reshaped the assignment flow next to it), and the
 * table-side sibling of `use-request-transfer.ts`, which drives the very same
 * popup from the work panel on a single record.
 */
export function useRequestTransferSelection(onTransferred: () => void) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const [isOpen, setIsOpen] = useState(false)
  const [ids, setIds] = useState<number[]>([])
  const [defaultSite, setDefaultSite] = useState<AssignOperatorsDialogSite | null>(null)

  // Same double gate as the bulk assignment: the popup writes the Sede AND
  // the Operatore.
  const canTransfer = can('request-management.update') && can('request-management.transferContact')

  const transferMutation = useMutation({
    // Kept as a wrapper, not a bare reference: TanStack hands the mutation
    // context as a second argument, which the api client would forward.
    mutationFn: (payload: TransferRequestsPayload) => transferRequests(payload),
    onSuccess: (result) => {
      toast.success(t('requestManagement.transfer.success', { count: result.transferred }))
      onTransferred()
    },
  })

  const handleTransfer = useCallback(
    async (input: AssignOperatorsDialogInput) => {
      try {
        // `showSiteField` + `lockedMode="single"` make the dialog refuse to
        // confirm without a destination Sede and an operator (spec 0113
        // AC-027), so an absent one is a wiring bug rather than a user path:
        // surface it instead of casting it away or letting the endpoint 422.
        if (input.operational_site_id === undefined || input.operator_id === undefined) {
          throw new Error('Transfer requires both a destination Sede and an operator.')
        }
        await transferMutation.mutateAsync({
          request_ids: ids,
          operational_site_id: input.operational_site_id,
          operator_id: input.operator_id,
        })
      } catch (error) {
        toast.error(t('requestManagement.transfer.errors.generic'))
        throw error
      }
    },
    [transferMutation, ids, t],
  )

  const open = useCallback((selection: TableSelection) => {
    setIds(selection.ids)
    setDefaultSite(resolveSharedOperationalSite(selection.rows))
    setIsOpen(true)
  }, [])

  // The transfer writes the same GA2 Operatore slot as the bulk assignment, so
  // its picker filters on the same competence — but NOT on the offers' own
  // Sede, which here is the destination the user picks (spec 0113 D-2).
  const { competenceCategoryIds, isResolvingCompetence } = useQuoteAssignmentScope(ids, isOpen)

  // The locked-mode popup reads `title`/`description` (no mode hints — Step 1
  // never renders).
  const copy = useMemo(
    () => ({
      title: t('requestManagement.transfer.title'),
      description: t('requestManagement.transfer.description', { count: ids.length }),
      modeHints: { balanced: '', single: '' },
    }),
    [t, ids.length],
  )

  return {
    canTransfer,
    isOpen,
    onOpenChange: setIsOpen,
    selectionCount: ids.length,
    defaultSite,
    copy,
    competenceCategoryIds,
    isResolvingCompetence,
    open,
    handleTransfer,
  }
}
