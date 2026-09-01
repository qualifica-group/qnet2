import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { QuoteWorkflowDetailView } from '@/features/quote-workflows/quote-workflow-detail'
import type { QuoteWorkflowDetailWithPermissions } from '@/features/quote-workflows/types'

/**
 * Spec 0047 amendment 2026-07-27, AC-036: the detail view must resolve a
 * criterion's field label from `field_label`/`field_source` (never
 * reconstructing the i18n key from `field`), translating only when
 * `field_source === 'native'`.
 */

const BASE: QuoteWorkflowDetailWithPermissions = {
  id: 9,
  name: 'EMEA workflow',
  is_active: true,
  criteria: [
    {
      id: 1,
      field: 'source_id',
      value_id: 5,
      value_label: 'Fiera',
      field_label: 'quoteWorkflows.criterionFields.source_id',
      field_source: 'native',
    },
    {
      id: 2,
      field: 'custom.preferred_supplier',
      value_id: 42,
      value_label: 'Acme Corp',
      field_label: 'Preferred supplier',
      field_source: 'custom',
    },
  ],
  statuses: [
    { id: 10, name: 'Open', color: null, sort_order: 0, system_key: 'open', group: 'open', description: null, requires_note: false },
  ],
  created_at: '2026-01-01T00:00:00Z',
  updated_at: '2026-01-01T00:00:00Z',
  permissions: {
    resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
    fields: {},
    actions: { view_activity: false },
  },
}

describe('QuoteWorkflowDetailView — criterion field labels (AC-036)', () => {
  it('translates a native criterion field label', () => {
    render(<QuoteWorkflowDetailView quoteWorkflow={BASE} />)
    expect(screen.getByText(i18n.t('quoteWorkflows.criterionFields.source_id'))).toBeInTheDocument()
  })

  it('renders a custom criterion field label literally, without translating it', () => {
    render(<QuoteWorkflowDetailView quoteWorkflow={BASE} />)
    expect(screen.getByText('Preferred supplier')).toBeInTheDocument()
  })

  it('renders the resolved value label for both native and custom criteria', () => {
    render(<QuoteWorkflowDetailView quoteWorkflow={BASE} />)
    expect(screen.getByText('Fiera')).toBeInTheDocument()
    expect(screen.getByText('Acme Corp')).toBeInTheDocument()
  })
})
