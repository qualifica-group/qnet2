import { useWatch, type UseFormReturn } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import {
  MAX_PROGRAM_GROUPS,
  addLinesToGroup,
  applyCommonToAll,
  duplicateGroup,
  groupByCategory,
  groupOfLine,
  newGroupFromLines,
  oneGroupPerLine,
  removeGroup,
  removeLineFromGroup,
  resolveTargetLines,
} from '@/features/contracts/contract-program-groups'
import type { ContractProgramFormValues, ContractProgramGroup } from '@/features/contracts/contract-program-schema'
import type { ContractProgrammableLine } from '@/features/contracts/types'

/**
 * The "Programma" dialog's group operations (spec 0215): thin wrapper that
 * reads the form's `groups`/`common`, runs a pure transformation from
 * `contract-program-groups.ts` and writes the result back. A transformation
 * that would exceed `MAX_PROGRAM_GROUPS` returns the same array: the hook
 * detects it by identity and tells the user instead of applying it.
 */
export function useContractProgramGroups(
  form: UseFormReturn<ContractProgramFormValues>,
  lines: ContractProgrammableLine[],
) {
  const { t } = useTranslation()
  const groups = useWatch({ control: form.control, name: 'groups' })
  const common = useWatch({ control: form.control, name: 'common' })

  /** Applies a transformation; true when it changed something. */
  const commit = (compute: (current: ContractProgramGroup[]) => ContractProgramGroup[]): boolean => {
    const next = compute(form.getValues('groups'))
    if (next === form.getValues('groups')) {
      return false
    }
    form.clearErrors('groups')
    form.setValue('groups', next, { shouldDirty: true })
    return true
  }

  const commitLimited = (compute: (current: ContractProgramGroup[]) => ContractProgramGroup[]): boolean => {
    const applied = commit(compute)
    if (!applied) {
      toast.error(t('contracts.actions.programDialog.maxGroups', { max: MAX_PROGRAM_GROUPS }))
    }
    return applied
  }

  const targetLines = (selectedIds: number[]) => resolveTargetLines(lines, groups, selectedIds)

  return {
    groups,
    common,
    atLimit: groups.length >= MAX_PROGRAM_GROUPS,
    groupOfLine: (lineId: number) => groupOfLine(groups, lineId),
    newGroupFromLines: (lineIds: number[]) => commitLimited((current) => newGroupFromLines(current, common, lineIds)),
    addLinesToGroup: (index: number, lineIds: number[]) =>
      commit((current) => addLinesToGroup(current, index, lineIds)),
    removeLineFromGroup: (index: number, lineId: number) =>
      commit((current) => removeLineFromGroup(current, index, lineId)),
    removeGroup: (index: number) => commit((current) => removeGroup(current, index)),
    /** Lines the bulk actions would act on: the selection or, with none, every free line (D-8). */
    targetLines,
    oneGroupPerLine: (selectedIds: number[]) =>
      targetLines(selectedIds).length > 0 &&
      commitLimited((current) => oneGroupPerLine(current, common, targetLines(selectedIds))),
    groupByCategory: (selectedIds: number[]) =>
      targetLines(selectedIds).length > 0 &&
      commitLimited((current) => groupByCategory(current, common, targetLines(selectedIds))),
    duplicateGroup: (index: number) => commitLimited((current) => duplicateGroup(current, index)),
    applyCommonToAll: () => commit((current) => applyCommonToAll(current, common)),
  }
}
