import { FileText, MessagesSquare } from 'lucide-react'
import type { ActionIconMap } from '@/features/table/action-icon-map'

/**
 * Domain icon overrides for the Quotes action catalog: the backend fixes the
 * 'generate_document'/'notes' icon keys as 'file-text'/'messages-square', both
 * absent from the shared defaults in `action-icon-map.ts`. Lives in its own
 * module (not inside `quotes-table.tsx`) because every surface that renders
 * the Quotes actions needs it — the standalone Offerte grid AND the
 * Opportunity master/detail panel — and a component module is not the right
 * place to share a constant.
 */
export const QUOTES_ACTION_ICONS: ActionIconMap = {
  'file-text': FileText,
  'messages-square': MessagesSquare,
}
