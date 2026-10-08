import { MessagesSquare, Paperclip } from 'lucide-react'
import type { ActionIconMap } from '@/features/table/action-icon-map'

/**
 * Domain icon overrides for the 'documents'/'notes' row actions: the backend
 * action catalog fixes their icon keys as 'paperclip'/'messages-square',
 * absent from the shared defaults in `action-icon-map.ts`. `messages-square`
 * is the SAME `MessagesSquare` the notes surfaces themselves use
 * (`NotesSection`, `NotesDialog`, the detail tab): one visual identity for the
 * feature. Its own module because two surfaces render these actions: the
 * Opportunita' grid and the anagrafica detail's Opportunita' tab (spec 0199).
 */
export const OPPORTUNITIES_ACTION_ICONS: ActionIconMap = {
  paperclip: Paperclip,
  'messages-square': MessagesSquare,
}
