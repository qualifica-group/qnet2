import { useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import {
  STATUS_COLUMN_KEYS,
  fetchColumnStatuses,
  saveColumnStatuses,
  type ColumnStatusesSelection,
  type StatusColumnKey,
} from '@/features/request-management/column-statuses-api'
import {
  filterWorkflows,
  selectionOf,
  withStatuses,
} from '@/features/request-management/column-statuses-selection'
import { requestManagementKeys } from '@/features/request-management/query-keys'
import { useRequestModule } from '@/features/request-management/request-module'
import { apiErrorMessage } from '@/lib/forbidden-message'

/**
 * The "Colonne e stati" configurator (spec 0391): loads the configuration
 * while the sheet is `open`, keeps the edits in a draft until "Salva", then
 * saves every column at once and refreshes the dashboard, whose numbers
 * depend on it.
 */
export function useRequestStatisticsColumnStatuses(open: boolean, onSaved: () => void) {
  const { t, i18n } = useTranslation()
  const module = useRequestModule()
  const queryClient = useQueryClient()
  const queryKey = requestManagementKeys.reportColumnStatuses(module.key)
  const [draft, setDraft] = useState<ColumnStatusesSelection | null>(null)
  const [activeColumn, setActiveColumn] = useState<StatusColumnKey>(STATUS_COLUMN_KEYS[0])
  const [search, setSearch] = useState('')

  const query = useQuery({
    queryKey,
    queryFn: () => fetchColumnStatuses(module.apiBasePath),
    enabled: open,
  })

  const mutation = useMutation({
    mutationFn: (columns: ColumnStatusesSelection) => saveColumnStatuses(module.apiBasePath, columns),
    onSuccess: (saved) => {
      queryClient.setQueryData(queryKey, saved)
      void queryClient.invalidateQueries({ queryKey: requestManagementKeys.dashboardAll(module.key) })
      toast.success(t('requestStatisticsColumns.saved'))
      setDraft(null)
      onSaved()
    },
    onError: (error) => {
      toast.error(apiErrorMessage(error, t('requestStatisticsColumns.saveError'), i18n))
    },
  })

  const saved = useMemo(() => (query.data ? selectionOf(query.data) : null), [query.data])
  const selection = draft ?? saved
  const workflows = useMemo(() => filterWorkflows(query.data?.workflows ?? [], search), [query.data, search])
  const twinKey = query.data?.columns.find((column) => column.key === activeColumn)?.twin_key ?? null

  // Step 1: every edit lands on the draft of the active column.
  const setStatuses = (ids: number[], checked: boolean) => {
    if (!selection) {
      return
    }
    setDraft({ ...selection, [activeColumn]: withStatuses(selection[activeColumn], ids, checked) })
  }

  // Step 2: closing or resetting drops the draft (and the search) unsaved.
  const discard = () => {
    setDraft(null)
    setSearch('')
  }

  // Step 3: "Salva" sends the choice of every column.
  const save = () => {
    if (selection) {
      mutation.mutate(selection)
    }
  }

  return {
    isLoading: query.isLoading,
    isError: query.isError,
    isSaving: mutation.isPending,
    isDirty: draft !== null,
    activeColumn,
    setActiveColumn,
    twinKey,
    search,
    setSearch,
    workflows,
    chosen: selection?.[activeColumn] ?? [],
    countFor: (key: StatusColumnKey) => selection?.[key].length ?? 0,
    setStatuses,
    discard,
    save,
  }
}
