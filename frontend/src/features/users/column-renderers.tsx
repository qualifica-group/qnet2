/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import type { ICellRendererParams } from 'ag-grid-community'
import { Check, X } from 'lucide-react'
import i18n from '@/i18n'
import { UserAvatar } from '@/components/user-avatar'
import { Badge } from '@/components/ui/badge'
import {
  ContactsCell,
  DateTimeCell,
  TagsCountCell,
} from '@/features/table/cell-renderers'
import { DateCell } from '@/features/table/rich-cells'
import { UserCell } from '@/features/table/user-cell'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/** Renders the `is_manager` boolean column as a localized yes/no label. */
function BooleanCell({ value }: ICellRendererParams) {
  if (typeof value !== 'boolean') {
    return <span className="text-muted-foreground">—</span>
  }
  return <span>{i18n.t(value ? 'common.yes' : 'common.no')}</span>
}

/**
 * Colored tone classes for the boolean badge (green = yes, red = no), matching
 * the business-functions boolean badge so boolean status columns look the same
 * across the app and adapt to dark mode.
 */
const BOOLEAN_BADGE_YES =
  'border-transparent bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-200'
const BOOLEAN_BADGE_NO =
  'border-transparent bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-200'

/**
 * Renders the `is_active` column as a colored yes/no badge with a leading icon
 * (check for active, cross for inactive). Icon plus text keeps it accessible —
 * color is not the only signal.
 */
function ActiveBadgeCell({ value }: ICellRendererParams) {
  if (typeof value !== 'boolean') {
    return <span className="text-muted-foreground">—</span>
  }
  return (
    <div className="flex h-full items-center justify-center">
      <Badge className={value ? BOOLEAN_BADGE_YES : BOOLEAN_BADGE_NO}>
        {value ? <Check aria-hidden="true" /> : <X aria-hidden="true" />}
        {i18n.t(value ? 'common.yes' : 'common.no')}
      </Badge>
    </div>
  )
}

/** Renders an email as a mailto link (users-specific). */
function EmailCell({ value }: ICellRendererParams) {
  if (typeof value !== 'string' || value === '') {
    return <span className="text-muted-foreground">—</span>
  }
  return (
    <a className="text-primary underline-offset-4 hover:underline" href={`mailto:${value}`}>
      {value}
    </a>
  )
}

/**
 * Renders the user's avatar with an initials fallback. The cell value is the
 * avatar image inlined as a data: URI (or null); the row's name drives the
 * fallback initials.
 */
function AvatarCell({ value, data }: ICellRendererParams) {
  const src = typeof value === 'string' && value !== '' ? value : null
  const name = typeof data?.name === 'string' ? data.name : ''

  return (
    <div className="flex h-full items-center">
      <UserAvatar name={name} src={src} size="sm" />
    </div>
  )
}

/**
 * Custom cell renderers keyed by the backend column `id`. Only columns that need
 * special rendering appear here; everything else falls back to AG Grid defaults.
 * The generic `roles` (count badge + tooltip) and `created_at` (datetime) cells
 * come from the shared table renderers so they are not re-implemented per domain.
 */
export const userColumnRenderers: TableRendererMap = {
  avatar_url: (params) => <AvatarCell {...params} />,
  roles: (params) => <TagsCountCell {...params} />,
  email: (params) => <EmailCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
  // All primary contacts (one per type): icon + label badges, value in tooltip.
  primary_contact: (params) => <ContactsCell {...params} />,
  // Employment profile columns (spec 0015): relationship_type/qualification_type
  // are `badge` columns, rendered generically by the table's own `BadgeCell`
  // fallback (no entry needed here).
  is_active: (params) => <ActiveBadgeCell {...params} />,
  is_manager: (params) => <BooleanCell {...params} />,
  // `reports_to` is a person ({id, name}); the shared user cell renders it as an
  // avatar + name chip that opens the user's detail Sheet on click.
  reports_to: (params) => <UserCell {...params} />,
  hired_at: (params) => <DateCell {...params} />,
  terminated_at: (params) => <DateCell {...params} />,
}
