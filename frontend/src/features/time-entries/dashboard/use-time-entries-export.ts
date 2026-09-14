/**
 * Filtered + monthly export orchestration (spec 0122 D-12, AC-039): two
 * mutations (`fetchFilteredTimeEntriesExport`/`fetchMonthlyTimeEntriesExport`)
 * plus the monthly dialog's own draft state (month/year/user, prefilled on
 * open from the current period/selected user, D-12 "la UI sceglie un
 * utente"). `saveBlob` triggers the download; the filename comes from the
 * response's `Content-Disposition` (fallback built in `api.ts`).
 */

import { useCallback, useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { toast } from 'sonner'
import type { ApiErrorResponse } from '@/api/types'
import { saveBlob } from '@/lib/download'
import { fetchFilteredTimeEntriesExport, fetchMonthlyTimeEntriesExport } from '@/features/time-entries/api'
import { parseDateString } from '@/features/time-entries/time-entry-period'
import type { FilteredTimeEntriesExportParams } from '@/features/time-entries/types'

function exportErrorMessage(error: unknown, fallback: string): string {
  if (axios.isAxiosError<ApiErrorResponse>(error) && error.response?.data?.message) {
    return error.response.data.message
  }
  return fallback
}

export interface UseTimeEntriesExportOptions {
  filteredParams: FilteredTimeEntriesExportParams
  /** Prefills the monthly dialog's user picker on open (`meta.selected_user`). */
  defaultUserId: number | null
  /** `Y-m-d` the monthly dialog's month/year are derived from on open. */
  anchorDate: string
}

export function useTimeEntriesExport({ filteredParams, defaultUserId, anchorDate }: UseTimeEntriesExportOptions) {
  const { t } = useTranslation()
  const [monthlyDialogOpen, setMonthlyDialogOpen] = useState(false)
  const [month, setMonth] = useState(() => new Date().getMonth() + 1)
  const [year, setYear] = useState(() => new Date().getFullYear())
  const [userId, setUserId] = useState<number | null>(null)

  const filteredExport = useMutation({
    mutationFn: () => fetchFilteredTimeEntriesExport(filteredParams),
    onSuccess: ({ blob, fileName }) => {
      saveBlob(blob, fileName)
      toast.success(t('timeEntries.export.ready'), { description: t('timeEntries.export.filteredDownloaded') })
    },
    onError: (error) => {
      toast.error(t('timeEntries.export.failed'), {
        description: exportErrorMessage(error, t('timeEntries.export.filteredFailed')),
      })
    },
  })

  const monthlyExport = useMutation({
    mutationFn: () => fetchMonthlyTimeEntriesExport({ month, year, user_ids: userId !== null ? [userId] : undefined }),
    onSuccess: ({ blob, fileName }) => {
      saveBlob(blob, fileName)
      toast.success(t('timeEntries.export.ready'), { description: t('timeEntries.export.monthlyDownloaded') })
      setMonthlyDialogOpen(false)
    },
    onError: (error) => {
      toast.error(t('timeEntries.export.failed'), {
        description: exportErrorMessage(error, t('timeEntries.export.monthlyFailed')),
      })
    },
  })

  const openMonthlyDialog = useCallback(() => {
    const anchor = parseDateString(anchorDate) ?? new Date()
    setMonth(anchor.getMonth() + 1)
    setYear(anchor.getFullYear())
    setUserId(defaultUserId)
    setMonthlyDialogOpen(true)
  }, [anchorDate, defaultUserId])

  const now = new Date()
  const isFuturePeriod = year > now.getFullYear() || (year === now.getFullYear() && month > now.getMonth() + 1)
  const missingUser = userId === null
  const canSubmitMonthly = !missingUser && !isFuturePeriod && month >= 1 && month <= 12 && year > 0

  return {
    exportFiltered: () => filteredExport.mutate(),
    isExportingFiltered: filteredExport.isPending,
    monthlyDialogOpen,
    openMonthlyDialog,
    closeMonthlyDialog: () => setMonthlyDialogOpen(false),
    month,
    setMonth,
    year,
    setYear,
    userId,
    setUserId,
    isFuturePeriod,
    missingUser,
    canSubmitMonthly,
    submitMonthly: () => monthlyExport.mutate(),
    isExportingMonthly: monthlyExport.isPending,
  }
}

export type UseTimeEntriesExportResult = ReturnType<typeof useTimeEntriesExport>
