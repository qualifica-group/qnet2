import { useTranslation } from 'react-i18next'
import { useAbilities } from '@/features/auth/use-abilities'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type { TableRow } from '@/features/table/types'

/**
 * `useModuleOpener` speaks the grid's `TableRow` (it is the type every other
 * call site hands it) but only ever reads `id` off it: the detail screen is
 * mounted by id and fetches its own data. The empty `actions` satisfies the
 * shape without pretending this row came from the grid.
 */
function asRow(id: number): TableRow {
  return { id, actions: [] }
}

/** Link-looking affordance on a field value; it is a `<button>` because it opens a modal, not a route. */
const RECORD_LINK_CLASS =
  'rounded-sm text-left font-medium text-primary underline-offset-4 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring'

interface RelatedRecordLinkProps {
  /** Registry domain of the record to open. */
  domain: string
  id: number
  /** Visible text AND accessible name: the record's own denomination (WCAG 2.5.3). */
  label: string
  /** Ability gating the affordance; without it the label renders as plain text. */
  permission: string
}

/**
 * The related record reachable straight from the field that names it (user
 * directive 2026-08-31): the Opportunità and the Offerta of a contract are
 * links ON their own value, not buttons in the actions bar.
 *
 * They open a MODAL, never another page (`forceMode: 'modal'`, the documented
 * use case of `useModuleOpener`, spec 0067 D-3): the contract being read is
 * never abandoned. Not lifecycle-gated either — a closed contract keeps them.
 *
 * Without the ability the label is still shown, just not actionable: hiding the
 * name of the record this contract belongs to would remove information, not an
 * action.
 */
export function RelatedRecordLink({ domain, id, label, permission }: RelatedRecordLinkProps) {
  const { can } = useAbilities()
  const opener = useModuleOpener(domain, { forceMode: OPEN_MODE_MODAL })

  if (!can(permission)) {
    return <>{label}</>
  }

  return (
    <>
      <button type="button" className={RECORD_LINK_CLASS} onClick={() => opener.openView(asRow(id))}>
        {label}
      </button>
      {opener.sheet}
    </>
  )
}

/** The underlying Offerta, opened in a modal from its own field value. */
export function ContractQuoteLink({ quoteId, title }: { quoteId: number; title: string }) {
  return <RelatedRecordLink domain="quotes" id={quoteId} label={title} permission="quotes.view" />
}

/** The linked Opportunità, opened in a modal from its own field value. */
export function ContractOpportunityLink({ id, name }: { id: number; name: string }) {
  return <RelatedRecordLink domain="opportunities" id={id} label={name} permission="opportunities.view" />
}

/** Placeholder shown where a contract has no linked opportunity at all. */
export function NoOpportunityText() {
  const { t } = useTranslation()
  return <span className="text-muted-foreground">{t('contracts.detail.noOpportunity')}</span>
}
