/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import type { ICellRendererParams } from 'ag-grid-community'
import { Briefcase, Check, UserRound, X } from 'lucide-react'
import i18n from '@/i18n'
import { Badge } from '@/components/ui/badge'
import { enumLabelOf } from '@/features/config/enum-label'
import { ContactsCell, DateTimeCell } from '@/features/table/cell-renderers'
import { RelationCell } from '@/features/table/rich-cells'
import { UserCell, UserStackCell } from '@/features/table/user-cell'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import type { AgreementStatus, ReferenceRef, SizeClass } from '@/features/registries/types'

/** Em-dash placeholder for an empty/unknown cell value. */
function EmptyCell() {
  return (
    <div className="flex h-full w-full items-center justify-center px-2 py-1">
      <span className="text-muted-foreground">—</span>
    </div>
  )
}

/** Renders the derived `source` column: the hydrated source name, or an em dash. */
function SourceCell({ value }: ICellRendererParams) {
  const source = value as ReferenceRef | null | undefined
  if (!source) {
    return <EmptyCell />
  }
  return <span>{source.name}</span>
}

/**
 * Colored tone classes for the boolean badge (green = yes, red = no), reusing
 * the same palette as the generic table BadgeCell so tones stay consistent
 * across the app and adapt to dark mode.
 */
const BOOLEAN_BADGE_YES = 'border-transparent bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-200'
const BOOLEAN_BADGE_NO = 'border-transparent bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-200'

/**
 * Renders the `is_supplier` column as a colored Sì/No badge with a leading
 * icon (check for yes, cross for no). Color is not the only signal — icon
 * plus text keep it accessible.
 */
function IsSupplierCell({ value }: ICellRendererParams) {
  const isTrue = value === true

  return (
    <div className="flex h-full items-center justify-center">
      <Badge className={isTrue ? BOOLEAN_BADGE_YES : BOOLEAN_BADGE_NO}>
        {isTrue ? <Check aria-hidden="true" /> : <X aria-hidden="true" />}
        {isTrue ? i18n.t('common.yes') : i18n.t('common.no')}
      </Badge>
    </div>
  )
}

/**
 * Renders the `agreement_status` column as a readable, localized badge. The
 * column is declared `type: 'text'` in the table config (its value is the
 * plain enum string, not backend badge metadata), so it is rendered here
 * rather than via the generic `BadgeCell`.
 */
function AgreementStatusCell({ value }: ICellRendererParams) {
  const status = value as AgreementStatus | null | undefined
  if (!status) {
    return <EmptyCell />
  }
  return (
    <div className="flex h-full w-full items-center justify-center px-2 py-1">
      <Badge variant="secondary">{enumLabelOf('agreement_status', status)}</Badge>
    </div>
  )
}

/** Renders the `size_class` column as a readable, localized badge (same rationale as above). */
function SizeClassCell({ value }: ICellRendererParams) {
  const sizeClass = value as SizeClass | null | undefined
  if (!sizeClass) {
    return <EmptyCell />
  }
  return (
    <div className="flex h-full w-full items-center justify-center px-2 py-1">
      <Badge variant="secondary">{enumLabelOf('size_class', sizeClass)}</Badge>
    </div>
  )
}

/**
 * Custom cell renderers keyed by the backend column `id`. Only columns that
 * need special rendering appear here; `name` falls back to the AG Grid
 * default text cell and `created_at`/`primary_contact` reuse the shared
 * domain-agnostic renderers (spec 0020). The team columns reuse the
 * Opportunita'/Offerte cells: `commercial`/`reporter` are referent relations
 * with a kind icon, `supervisor` one person, `managers` ("Operatori") an
 * avatar stack.
 */
export const registryColumnRenderers: TableRendererMap = {
  source: (params) => <SourceCell {...params} />,
  is_supplier: (params) => <IsSupplierCell {...params} />,
  agreement_status: (params) => <AgreementStatusCell {...params} />,
  size_class: (params) => <SizeClassCell {...params} />,
  primary_contact: (params) => <ContactsCell {...params} />,
  commercial: (params) => <RelationCell {...params} icon={Briefcase} />,
  supervisor: (params) => <UserCell {...params} />,
  reporter: (params) => <RelationCell {...params} icon={UserRound} />,
  managers: (params) => <UserStackCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
