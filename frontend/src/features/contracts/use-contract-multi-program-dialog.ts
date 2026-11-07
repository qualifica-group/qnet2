import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { apiErrorMessage } from '@/lib/forbidden-message'
import {
  createMultiQuoteWorkOrder,
  fetchMultiProgrammableLines,
  multiProgrammableLinesQueryKey,
} from '@/features/contracts/api'
import { buildContractProgramSchema, type ContractProgramFormValues } from '@/features/contracts/contract-program-schema'
import {
  distinctQuoteCount,
  multiProgramDefaultValues,
  MULTI_ERROR_FIELDS,
  multiProgramRefusal,
  selectableLineIds,
  toMultiErrorPath,
  toMultiPayload,
} from '@/features/contracts/contract-multi-program-schema'
import type { MultiProgrammableLine } from '@/features/contracts/types'

const EMPTY_LINES: MultiProgrammableLine[] = []
const MIN_OFFERS = 2
const WORK_ORDERS_PATH = '/work-orders'

interface UseContractMultiProgramDialogArgs {
  contractIds: number[]
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Orchestrates "Programma commessa unica" (spec 0267 D-2/D-3): the lines query,
 * the same-branch selection, the single all-or-nothing POST, the 422
 * `quote_line_ids` mapping onto the lines panel and the drop of the contracts
 * the server refuses (D-14). The component only renders.
 */
export function useContractMultiProgramDialog({ contractIds, open, onOpenChange }: UseContractMultiProgramDialogArgs) {
  const { t, i18n } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const form = useForm<ContractProgramFormValues>({
    resolver: zodResolver(buildContractProgramSchema(t)),
    defaultValues: multiProgramDefaultValues(),
  })
  const [selected, setSelectedState] = useState<number[]>([])
  // Contracts the user dropped after a refusal (D-14): the grid selection itself is left untouched.
  const [excludedIds, setExcludedIds] = useState<number[]>([])
  const activeIds = contractIds.filter((id) => !excludedIds.includes(id))

  const linesQuery = useQuery({
    queryKey: multiProgrammableLinesQueryKey(activeIds),
    queryFn: () => fetchMultiProgrammableLines(activeIds),
    enabled: open && activeIds.length >= MIN_OFFERS,
    // A refusal of the selection is the server's final answer: never retried.
    retry: (failureCount, error) => multiProgramRefusal(error) === undefined && failureCount < 1,
  })
  const linesRefusal = multiProgramRefusal(linesQuery.error)
  const refusedIds = linesRefusal?.contracts.map((contract) => contract.id) ?? []
  const remainingAfterRefusal = activeIds.filter((id) => !refusedIds.includes(id)).length
  const lines = linesQuery.data?.lines ?? EMPTY_LINES
  const selectableIds = selectableLineIds(lines, selected)
  const offersCount = distinctQuoteCount(lines, selected)

  const createMutation = useMutation({
    mutationFn: (values: ContractProgramFormValues) => createMultiQuoteWorkOrder(toMultiPayload(values, activeIds)),
  })

  const setSelected = (next: number[]) => {
    setSelectedState(next)
    form.setValue('groups.0.quote_line_ids', next, { shouldValidate: form.formState.isSubmitted })
  }

  const close = () => {
    form.reset(multiProgramDefaultValues())
    setSelectedState([])
    setExcludedIds([])
    onOpenChange(false)
  }

  const excludeRefused = () => setExcludedIds((current) => [...current, ...refusedIds])

  const requestClose = () => {
    if (!createMutation.isPending) {
      close()
    }
  }

  const submit = form.handleSubmit(async (values) => {
    // Step 1: one request creates the single work order (all-or-nothing on the server).
    try {
      const workOrder = await createMutation.mutateAsync(values)
      // Step 2: refresh what the new commessa touches, tell the user, open it.
      toast.success(t('contracts.actions.multiProgramDialog.success'))
      void queryClient.invalidateQueries({ queryKey: ['contracts'] })
      void queryClient.invalidateQueries({ queryKey: ['work-orders'] })
      close()
      void navigate(`${WORK_ORDERS_PATH}/${workOrder.id}`)
    } catch (error) {
      // Step 3: a 422 lands on the form fields (lines panel included); anything else is a toast.
      const errors = axios.isAxiosError(error) && error.response?.status === 422
        ? (error.response.data?.errors as Record<string, string[]> | undefined)
        : undefined
      const mapped = Object.entries(errors ?? {}).filter(([key]) => MULTI_ERROR_FIELDS.includes(toMultiErrorPath(key)))
      mapped.forEach(([key, messages]) => form.setError(toMultiErrorPath(key), { message: messages[0] }))
      if (mapped.length === 0) {
        toast.error(apiErrorMessage(error, t('contracts.actions.multiProgramDialog.genericError'), i18n))
      }
    }
  })

  return {
    form,
    linesQuery,
    linesRefusal,
    // The refused contracts can be dropped only if at least two contracts remain to group.
    canExcludeRefused: refusedIds.length > 0 && remainingAfterRefusal >= MIN_OFFERS,
    excludeRefused,
    excludedCount: excludedIds.length,
    data: linesQuery.data,
    selected,
    setSelected,
    selectableIds,
    offersCount,
    hasEnoughOffers: offersCount >= MIN_OFFERS,
    isSaving: createMutation.isPending,
    submit,
    requestClose,
  }
}
