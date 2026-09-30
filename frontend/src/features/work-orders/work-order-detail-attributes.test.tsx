import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { WorkOrderDetailAttributesSection } from '@/features/work-orders/work-order-detail-attributes'
import type { ApplicableAttributeSummary } from '@/features/work-orders/types'

const TABLE_ATTRIBUTE: ApplicableAttributeSummary = {
  id: 1,
  code: 'inspections',
  name: 'Inspections',
  type: 'table',
  description: null,
  help_text: null,
  placeholder: null,
  icon: null,
  config: {
    columns: [
      { key: 'outcome', label: 'Outcome', type: 'enum', options: [{ value: 'ok', label: 'Compliant' }] },
      { key: 'certified', label: 'Certified', type: 'boolean' },
    ],
  },
  relation_target: null,
  is_required: false,
  sort_order: 0,
  options: [],
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('WorkOrderDetailAttributesSection table attribute (spec 0180, AC-023)', () => {
  it('renders a filled table attribute as a read-only mini table', () => {
    render(
      <WorkOrderDetailAttributesSection
        attributes={[TABLE_ATTRIBUTE]}
        values={{ inspections: { rows: [{ id: 'a', outcome: 'ok', certified: true }], summary: null } }}
      />,
    )

    expect(screen.getByRole('columnheader', { name: 'Certified' })).toBeInTheDocument()
    expect(screen.getByRole('cell', { name: 'Compliant' })).toBeInTheDocument()
    expect(screen.getByRole('cell', { name: 'Yes' })).toBeInTheDocument()
  })

  it('falls back to the empty placeholder when the value is unset', () => {
    render(<WorkOrderDetailAttributesSection attributes={[TABLE_ATTRIBUTE]} values={{ inspections: null }} />)

    expect(screen.queryByRole('table')).not.toBeInTheDocument()
  })
})
