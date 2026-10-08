import {
  BadgeCheck,
  BellRing,
  CheckCircle2,
  Lock,
  MessagesSquare,
  RotateCcw,
  Unlock,
  XOctagon,
} from 'lucide-react'
import type { ActionIconMap } from '@/features/table/action-icon-map'

/**
 * The icon names `TaskColumnCatalog::actions()` advertises that the shared
 * `defaultActionIconMap` does not know: without them every domain action fell
 * back to the neutral three-dots glyph. Same glyphs as the detail's
 * `TaskActionsBar`, so an action looks the same in the row and in the detail.
 * Its own module because two grids render these actions: the Task page and the
 * anagrafica detail's Task tab (spec 0199).
 */
export const TASK_ACTION_ICONS: ActionIconMap = {
  check: CheckCircle2,
  'rotate-ccw': RotateCcw,
  'badge-check': BadgeCheck,
  'badge-x': XOctagon,
  lock: Lock,
  'lock-open': Unlock,
  'message-circle-question': BellRing,
  'messages-square': MessagesSquare,
}
