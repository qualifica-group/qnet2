import { useMemo } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { toast } from 'sonner'
import { useTranslation } from 'react-i18next'
import { saveBlob } from '@/lib/download'
import {
  accountStatementKeys,
  exportStatement,
  fetchBalances,
  fetchStatement,
  fetchUnassigned,
  toStatementParams,
} from '@/features/account-statements/api'
import type {
  AccountBalances,
  Statement,
  StatementFilters,
  UnassignedList,
} from '@/features/account-statements/types'

const UNPROCESSABLE = 422

export function useBalances(companyId: number | null) {
  return useQuery<AccountBalances, AxiosError>({
    queryKey: accountStatementKeys.balances(companyId),
    queryFn: () => fetchBalances(companyId),
  })
}

export function useUnassigned(companyId: number | null) {
  return useQuery<UnassignedList, AxiosError>({
    queryKey: accountStatementKeys.unassigned(companyId),
    queryFn: () => fetchUnassigned(companyId),
  })
}

/** Statement of one account for the filters; disabled until an account is chosen. */
export function useStatement(accountId: number | null, filters: StatementFilters) {
  const params = useMemo(() => toStatementParams(filters), [filters])
  return useQuery<Statement, AxiosError>({
    queryKey: accountStatementKeys.statement(accountId ?? 0, params),
    queryFn: () => fetchStatement(accountId as number, params),
    enabled: accountId !== null,
  })
}

/** Downloads the xlsx of the current statement through the axios client. */
export function useStatementExport(accountId: number | null, filters: StatementFilters) {
  const { t } = useTranslation()
  return useMutation<void, AxiosError>({
    mutationFn: async () => {
      const file = await exportStatement(accountId as number, toStatementParams(filters))
      saveBlob(file.blob, file.filename)
    },
    onError: (error) =>
      toast.error(t(error.response?.status === UNPROCESSABLE ? 'accountStatements.tooManyRows' : 'accountStatements.exportError')),
  })
}
