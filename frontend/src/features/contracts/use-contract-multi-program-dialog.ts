import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
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
 * the same-branch selection, the single all-or-nothing POST and the 422
 * `quote_line_ids` mapping onto the lines panel. The component only renders.
 */
export function useContractMultiProgramDialog({ contractIds, open, onOpenChange }: UseContractMultiProgramDialogArgs) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const form = useForm<ContractProgramFormValues>({
    resolver: zodResolver(buildContractProgramSchema(t)),
    defaultValues: multiProgramDefaultValues(),
  })
  const [selected, setSelectedState] = useState<number[]>([])

  const linesQuery = useQuery({
    queryKey: multiProgrammableLinesQueryKey(contractIds),
    queryFn: () => fetchMultiProgrammableLines(contractIds),
    enabled: open && contractIds.length >= MIN_OFFERS,
    // A refusal of the selection is the server's final answer: never retried.
    retry: (failureCount, error) => multiProgramRefusal(error) === undefined && failureCount < 1,
  })
  const lines = linesQuery.data?.lines ?? EMPTY_LINES
  const selectableIds = selectableLineIds(lines, selected)
  const offersCount = distinctQuoteCount(lines, selected)

  const createMutation = useMutation({
    mutationFn: (values: ContractProgramFormValues) => createMultiQuoteWorkOrder(toMultiPayload(values, contractIds)),
  })

  const setSelected = (next: number[]) => {
    setSelectedState(next)
    form.setValue('groups.0.quote_line_ids', next, { shouldValidate: form.formState.isSubmitted })
  }

  const close = () => {
    form.reset(multiProgramDefaultValues())
    setSelectedState([])
    onOpenChange(false)
  }

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
        toast.error(t('contracts.actions.multiProgramDialog.genericError'))
      }
    }
  })

  return {
    form,
    linesQuery,
    linesRefusal: multiProgramRefusal(linesQuery.error),
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
