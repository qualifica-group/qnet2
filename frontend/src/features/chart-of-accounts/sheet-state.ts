import type { ChartAccountNode, ChartGroup, SubAccount } from '@/features/chart-of-accounts/types'

export type AccountSheetState =
  | { type: 'group-create' }
  | { type: 'group-edit'; group: ChartGroup }
  | { type: 'account-create'; group: ChartGroup }
  | { type: 'account-edit'; account: ChartAccountNode; group: ChartGroup }

export type SubAccountSheetState =
  | { type: 'sub-create'; account: ChartAccountNode; group: ChartGroup }
  /** `account` is null when placing an unplaced cost type. */
  | { type: 'sub-edit'; sub: SubAccount; account: ChartAccountNode | null; group: ChartGroup | null }

export interface ActivityState {
  type: 'activity'
  resource: string
  id: number
}

/** What the screen currently has open on top of the tree (at most one overlay). */
export type SheetState = AccountSheetState | SubAccountSheetState | ActivityState
