import type { ActionHrefResolver } from '@/features/table/row-actions'

/** The only row action of the report (task 427625): it opens the offer, never a page of the report domain. */
const VIEW_ACTION = 'view'

/** The offer page of a `view` action; anything else has no page. */
export const agentSalesReportActionHref: ActionHrefResolver = (action, row) =>
  action.key === VIEW_ACTION && typeof row.id === 'number' ? `/quotes/${row.id}` : undefined
