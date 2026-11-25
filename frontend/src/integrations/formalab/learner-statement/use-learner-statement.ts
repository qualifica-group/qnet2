import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { serverMessage } from '@/integrations/formalab/error-message'
import { formalabKeys } from '@/integrations/formalab/query-keys'
import {
  downloadLearnerStatement,
  fetchLearnerStatement,
  updateLearnerCommission,
  updatePartnerSupplier,
} from '@/integrations/formalab/learner-statement/api'
import type {
  CommissionPayload,
  CommissionScenario,
  LearnerStatement,
  StatementExportVariant,
} from '@/integrations/formalab/learner-statement/types'

const UNPROCESSABLE = 422

/**
 * The prospetto of a work order with its edits (spec 0427). The commission
 * scenario starts as the server default (financial once the balance is in)
 * and follows the toggle; every edit answers with the recomputed prospetto,
 * written straight into the cache of the scenario on screen.
 */
export function useLearnerStatement(workOrderId: number) {
  const { t } = useTranslation('formalab')
  const queryClient = useQueryClient()
  const [chosenScenario, setScenario] = useState<CommissionScenario | null>(null)
  const queryKey = formalabKeys.learnerStatement(workOrderId, chosenScenario)

  const query = useQuery<LearnerStatement>({
    queryKey,
    queryFn: () => fetchLearnerStatement(workOrderId, chosenScenario),
    placeholderData: (previous) => previous,
    retry: false,
  })
  const scenario: CommissionScenario = query.data?.commission_scenario ?? 'economic'
  const store = (statement: LearnerStatement) => queryClient.setQueryData(queryKey, statement)

  const refresh = useMutation({
    mutationFn: () => fetchLearnerStatement(workOrderId, chosenScenario, true),
    onSuccess: store,
    onError: (error) => toast.error(serverMessage(error, t('learnerStatement.loadError'))),
  })

  const commission = useMutation({
    mutationFn: ({ taxCode, payload }: { taxCode: string; payload: CommissionPayload }) =>
      updateLearnerCommission(workOrderId, taxCode, payload, scenario),
    onSuccess: store,
    onError: (error) => toast.error(serverMessage(error, t('learnerStatement.saveFailed'))),
  })

  const supplier = useMutation({
    mutationFn: ({ partner, supplierId }: { partner: string; supplierId: number | null }) =>
      updatePartnerSupplier(workOrderId, partner, supplierId, scenario),
    onSuccess: store,
    onError: (error) => toast.error(serverMessage(error, t('learnerStatement.saveFailed'))),
  })

  const exportFile = useMutation({
    mutationFn: (variant: StatementExportVariant) => downloadLearnerStatement(workOrderId, variant, scenario),
    onError: (error) => {
      // A blob error body carries no readable message: the one 422 is "no partner".
      const noPartners = axios.isAxiosError(error) && error.response?.status === UNPROCESSABLE
      toast.error(t(noPartners ? 'learnerStatement.export.noPartners' : 'learnerStatement.export.failed'))
    },
  })

  return { query, scenario, setScenario, refresh, commission, supplier, exportFile }
}

export type LearnerStatementController = ReturnType<typeof useLearnerStatement>
