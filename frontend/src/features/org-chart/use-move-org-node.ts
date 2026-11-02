import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query'
import axios, { type AxiosError } from 'axios'
import type { TFunction } from 'i18next'
import type { ApiErrorResponse } from '@/api/types'
import i18n from '@/i18n'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { moveOrgFunctionParent, moveOrgPersonFunction, moveOrgPersonManager, orgChartKeys } from '@/features/org-chart/api'
import type { OrgMove } from '@/features/org-chart/move-types'

const UNPROCESSABLE_STATUS = 422

function sendMove(move: OrgMove): Promise<void> {
  switch (move.type) {
    case 'manager':
      return moveOrgPersonManager(move.userId, { from_manager_id: move.fromId, to_manager_id: move.toId })
    case 'parent':
      return moveOrgFunctionParent(move.functionId, { parent_id: move.toId })
    case 'function':
      return moveOrgPersonFunction(move.userId, { from_function_id: move.fromId, to_function_id: move.toId })
  }
}

/** Runs one of the three move endpoints and refreshes both trees on success. */
export function useMoveOrgNode(): UseMutationResult<void, AxiosError<ApiErrorResponse>, OrgMove> {
  const queryClient = useQueryClient()
  return useMutation<void, AxiosError<ApiErrorResponse>, OrgMove>({
    mutationFn: sendMove,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: orgChartKeys.all }),
  })
}

/** 422 carries the server's own reason; a 403 uses the specific forbidden sentence, anything else the i18n copy. */
export function moveErrorMessage(error: unknown, t: TFunction): string {
  if (!axios.isAxiosError<ApiErrorResponse>(error)) return t('orgChart.move.error')
  if (error.response?.status === UNPROCESSABLE_STATUS && error.response.data?.message) return error.response.data.message
  return apiErrorMessage(error, t('orgChart.move.error'), i18n)
}
