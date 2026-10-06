import { createContext } from 'react'
import type { TableRow } from '@/features/table/types'

/**
 * The Task grid's "complete this row" entry point, handed to the title cell
 * (`TaskTitleCell`): the grid's cell renderers are a static map, so the screen
 * that owns the complete dialog provides it here. `null` outside the Task
 * grid, where the title cell shows no toggle action.
 */
export const TaskCompleteRowContext = createContext<((row: TableRow) => void) | null>(null)
