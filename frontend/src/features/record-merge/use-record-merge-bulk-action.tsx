import { useCallback, useState, type RefObject } from 'react'
import { useTranslation } from 'react-i18next'
import { Merge } from 'lucide-react'
import { useAbilities } from '@/features/auth/use-abilities'
import { RecordMergeDialog } from '@/features/record-merge/record-merge-dialog'
import { MERGE_MAX_RECORDS, MERGE_MIN_RECORDS, type MergeModule } from '@/features/record-merge/types'
import type { TableViewHandle } from '@/features/table/table-view'
import type { BulkAction, TableSelection } from '@/features/table/use-bulk-actions-slot'

const NO_IDS: readonly number[] = []

/**
 * Wires the "Unisci duplicati" bulk action of a grid (spec 0394 D-1): offered only with update AND delete on
 * the module (the server re-authorizes), enabled with 2-5 selected rows. Returns the action factory and the dialog.
 */
export function useRecordMergeBulkAction(module: MergeModule, tableRef: RefObject<TableViewHandle | null>) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const allowed = can(`${module}.update`) && can(`${module}.delete`)
  const [ids, setIds] = useState<readonly number[]>(NO_IDS)

  const getBulkActions = useCallback(
    (selection: TableSelection): BulkAction[] => {
      if (!allowed) {
        return []
      }
      const outOfRange = selection.ids.length < MERGE_MIN_RECORDS || selection.ids.length > MERGE_MAX_RECORDS
      return [
        {
          key: 'merge-duplicates',
          label: t('recordMerge.bulkAction'),
          icon: Merge,
          disabled: outOfRange,
          disabledReason: outOfRange ? t('recordMerge.bulkDisabled', { max: MERGE_MAX_RECORDS }) : undefined,
          onSelect: () => setIds(selection.ids.map(Number)),
        },
      ]
    },
    [allowed, t],
  )

  const close = useCallback(() => setIds(NO_IDS), [])
  const onMerged = useCallback(() => {
    tableRef.current?.clearSelection()
    tableRef.current?.refresh()
  }, [tableRef])

  const dialog =
    ids.length > 0 ? (
      <RecordMergeDialog key={ids.join(',')} module={module} ids={ids} onClose={close} onMerged={onMerged} />
    ) : null

  return { getBulkActions, dialog }
}
