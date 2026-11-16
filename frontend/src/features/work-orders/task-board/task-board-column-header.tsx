/**
 * The column labels above a phase's task rows (spec 0401 D-1/D-2), on the
 * same grid template as the rows so each value sits under its label. Shown
 * from `lg` up only: below it the rows stack and every cell labels itself.
 */

import { useTranslation } from 'react-i18next'
import {
  TASK_BOARD_CELLS_CLASS,
  TASK_BOARD_COLUMN_KEYS,
  TASK_BOARD_LEADING_CLASS,
} from '@/features/work-orders/task-board/task-board-row-columns'

export function TaskBoardColumnHeader() {
  const { t } = useTranslation()

  return (
    <div className="hidden items-center gap-2 border-b border-border/60 px-3 py-1.5 lg:flex">
      <span className={TASK_BOARD_LEADING_CLASS} aria-hidden="true" />
      <div className={TASK_BOARD_CELLS_CLASS}>
        {TASK_BOARD_COLUMN_KEYS.map((key) => (
          <span key={key} className="truncate px-1.5 text-[10px] leading-tight font-medium tracking-wide text-muted-foreground uppercase">
            {t(`workOrders.taskBoard.task.columns.${key}`)}
          </span>
        ))}
      </div>
    </div>
  )
}
