/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import { Link } from 'react-router-dom'
import type { ICellRendererParams } from 'ag-grid-community'
import { quoteColumnRenderers } from '@/features/quotes/column-renderers'
import { CodeBadgeCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/** The quote code as a link to the offer: the report rows open `/quotes/{id}` (spec 0365 D-4). */
function QuoteCodeLinkCell(params: ICellRendererParams) {
  const id = (params.data as { id?: number } | undefined)?.id
  if (id === undefined || typeof params.value !== 'string' || params.value === '') {
    return <CodeBadgeCell {...params} />
  }
  return (
    <Link
      to={`/quotes/${id}`}
      className="flex h-full items-center rounded-sm focus-visible:outline-2 focus-visible:outline-ring"
    >
      <CodeBadgeCell {...params} />
    </Link>
  )
}

/**
 * Cells of the report grid. Relations, status and money reuse the Quotes
 * grid renderers (same projection, spec 0365 contract); the remaining columns
 * are plain text or dates and take the framework defaults.
 */
export const agentSalesReportRenderers: TableRendererMap = {
  code: (params) => <QuoteCodeLinkCell {...params} />,
  quote_workflow_status: quoteColumnRenderers.quote_workflow_status,
  company_site: quoteColumnRenderers.company_site,
  commercial: quoteColumnRenderers.commercial,
  reporter: quoteColumnRenderers.reporter,
  managers: quoteColumnRenderers.managers,
  revenue_net: quoteColumnRenderers.revenue_net,
}
