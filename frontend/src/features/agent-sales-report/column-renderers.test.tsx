import { describe, expect, it } from 'vitest'
import { agentSalesReportRenderers } from '@/features/agent-sales-report/column-renderers'
import { quoteColumnRenderers } from '@/features/quotes/column-renderers'

describe('agentSalesReportRenderers', () => {
  it('AC-025 renders the commercial partner like the Quotes grid relation cell', () => {
    expect(agentSalesReportRenderers.commercial_partner).toBe(quoteColumnRenderers.commercial_partner)
  })
})
