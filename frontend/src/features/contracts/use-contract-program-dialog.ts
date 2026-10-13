import { useState } from 'react'
import { useForm, type FieldErrors } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createContractWorkOrdersBatch } from '@/features/contracts/api'
import { failingGroupIndices, groupErrorPaths } from '@/features/contracts/contract-program-errors'
import { groupDisplayTitle } from '@/features/contracts/contract-program-groups'
import {
  buildContractProgramSchema,
  contractProgramDefaultValues,
  toBatchPayload,
  type ContractProgramFormValues,
} from '@/features/contracts/contract-program-schema'
import { useContractProgramGroups } from '@/features/contracts/use-contract-program-groups'
import { useContractProgrammableLines } from '@/features/contracts/use-contract-program-lines'
import type { ContractProgrammableLine } from '@/features/contracts/types'
import type { WorkOrderDetail } from '@/features/work-orders/types'

const EMPTY_LINES: ContractProgrammableLine[] = []

interface UseContractProgramDialogArgs {
  contractId: number
  open: boolean
  onOpenChange: (open: boolean) => void
  onCreated: (workOrders: WorkOrderDetail[]) => void
}

/**
 * Orchestrates the "Programma" dialog (spec 0215): the form of N groups, the
 * offer lines, the selection driving the quick actions, one all-or-nothing
 * save (D-1) and the unsaved-groups guard on close (D-11). The component
 * that uses it only renders.
 */
export function useContractProgramDialog({ contractId, open, onOpenChange, onCreated }: UseContractProgramDialogArgs) {
  const { t } = useTranslation()
  const form = useForm<ContractProgramFormValues>({
    resolver: zodResolver(buildContractProgramSchema(t)),
    defaultValues: contractProgramDefaultValues(),
  })
  const linesQuery = useContractProgrammableLines(contractId, open)
  const lines = linesQuery.data ?? EMPTY_LINES
  const groupsApi = useContractProgramGroups(form, lines)
  const [selected, setSelected] = useState<number[]>([])
  const [collapsedKeys, setCollapsedKeys] = useState<string[]>([])
  const [leaveOpen, setLeaveOpen] = useState(false)
  const { groups } = groupsApi

  const createMutation = useMutation({
    mutationFn: (values: ContractProgramFormValues) => createContractWorkOrdersBatch(contractId, toBatchPayload(values)),
  })

  const close = () => {
    form.reset(contractProgramDefaultValues())
    setSelected([])
    setCollapsedKeys([])
    setLeaveOpen(false)
    onOpenChange(false)
  }

  /** Closing with groups asks first (D-11); a save in flight cannot be dismissed. */
  const requestClose = () => {
    if (createMutation.isPending) {
      return
    }
    if (groups.length > 0) {
      setLeaveOpen(true)
      return
    }
    close()
  }

  const expandGroups = (indices: number[]) => {
    const keys = indices.flatMap((index) => groups[index]?.key ?? [])
    setCollapsedKeys((current) => current.filter((key) => !keys.includes(key)))
  }

  const onInvalid = (errors: FieldErrors<ContractProgramFormValues>) => {
    expandGroups(Object.keys(errors.groups ?? {}).filter((key) => /^\d+$/.test(key)).map(Number))
  }

  const onValid = async (values: ContractProgramFormValues) => {
    // Step 1: one request carries every group (all-or-nothing on the server).
    try {
      const workOrders = await createMutation.mutateAsync(values)
      // Step 2: report up, tell the user, reset.
      toast.success(t('contracts.actions.programDialog.success', { count: workOrders.length }))
      onCreated(workOrders)
      close()
    } catch (error) {
      // Step 3: a 422 lands on the failing group's fields and opens it; anything else is a toast.
      const handled = applyServerValidationErrors(error, form.setError, groupErrorPaths(values.groups.length))
      const failing = failingGroupIndices(error)
      expandGroups(failing)
      if (!handled || failing.length === 0) {
        toast.error(t('contracts.actions.programDialog.genericError'))
      }
    }
  }

  /** Runs a group action; a selection that was just consumed is cleared. */
  const runAction = (action: () => boolean) => {
    if (action()) {
      setSelected([])
    }
  }

  return {
    form,
    lines,
    linesQuery,
    ...groupsApi,
    selected,
    setSelected,
    collapsedKeys,
    toggleGroup: (key: string, isOpen: boolean) =>
      setCollapsedKeys((current) => (isOpen ? current.filter((k) => k !== key) : [...current, key])),
    groupLabels: groups.map(
      (group, index) =>
        `${t('contracts.actions.programDialog.groupShort', { number: index + 1 })} · ${groupDisplayTitle(group, lines)}`,
    ),
    runAction,
    submit: form.handleSubmit(onValid, onInvalid),
    isSaving: createMutation.isPending,
    leaveOpen,
    setLeaveOpen,
    requestClose,
    close,
  }
}
