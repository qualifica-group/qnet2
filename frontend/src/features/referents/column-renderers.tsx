/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import type { ICellRendererParams } from 'ag-grid-community'
import { Badge } from '@/components/ui/badge'
import { enumLabelOf } from '@/features/config/enum-label'
import { ContactsCell, DateTimeCell } from '@/features/table/cell-renderers'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import type {
  ReferentContactScope,
  ReferentTypeRef,
} from '@/features/referents/types'

/** Em-dash placeholder for an empty/unknown cell value. */
function EmptyCell() {
  return (
    <div className="flex h-full w-full items-center justify-center px-2 py-1">
      <span className="text-muted-foreground">—</span>
    </div>
  )
}

/** Renders the derived `referent_type` column: the hydrated type name, or an em dash. */
function ReferentTypeCell({ value }: ICellRendererParams) {
  const referentType = value as ReferentTypeRef | null | undefined
  if (!referentType) {
    return <EmptyCell />
  }
  return <span>{referentType.name}</span>
}

/**
 * Tone classes for the `contact_scope` badge — neutral distinction, not a
 * status signal, so both tones stay calm (mirrors the app's badge palette).
 */
const CONTACT_SCOPE_BADGE_CLASSES: Record<ReferentContactScope, string> = {
  internal: 'border-transparent bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-200',
  external: 'border-transparent bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200',
}

/**
 * Renders the `contact_scope` column as a readable, localized badge
 * (AC-023). The column is declared `type: 'text'` in the table config (its
 * value is the plain enum string, not backend badge metadata), so it is
 * rendered here rather than via the generic `BadgeCell`.
 */
function ContactScopeCell({ value }: ICellRendererParams) {
  const scope = value as ReferentContactScope | null | undefined
  if (scope !== 'internal' && scope !== 'external') {
    return <EmptyCell />
  }
  return (
    <div className="flex h-full w-full items-center justify-center px-2 py-1">
      <Badge className={CONTACT_SCOPE_BADGE_CLASSES[scope]}>
        {enumLabelOf('referent_contact_scope', scope)}
      </Badge>
    </div>
  )
}

/** Renders the `user` column (spec 0090 D-3): the linked user's name, or an em dash. */
function UserCell({ value }: ICellRendererParams) {
  const userName = value as string | null | undefined
  if (!userName) {
    return <EmptyCell />
  }
  return <span>{userName}</span>
}

/**
 * Custom cell renderers keyed by the backend column `id`. Only columns that
 * need special rendering appear here; `name` falls back to the AG Grid
 * default text cell and `created_at` reuses the shared domain-agnostic
 * renderer (spec 0016). `primary_contact` reuses the shared `ContactsCell`
 * (count badge + tooltip), identical to the Users column.
 */
export const referentColumnRenderers: TableRendererMap = {
  referent_type: (params) => <ReferentTypeCell {...params} />,
  contact_scope: (params) => <ContactScopeCell {...params} />,
  primary_contact: (params) => <ContactsCell {...params} />,
  user: (params) => <UserCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
