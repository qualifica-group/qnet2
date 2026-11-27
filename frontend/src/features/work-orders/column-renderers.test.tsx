import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { formatDate } from '@/lib/formatting/date-display'
import { buildWorkOrderColumnRenderers } from '@/features/work-orders/column-renderers'

/**
 * Spec 0093 `data_contract`. `type`/`status` declare an `enumKey`
 * (`work_order_type`/`work_order_work_phase`, confirmed by the backend) and are
 * therefore covered by the generic `BadgeCell` fallback, not by this map —
 * this suite only locks down the entries `workOrderColumnRenderers` DOES
 * own: `code`, `registry`, `is_force_closed` (a plain boolean with no backend `enumKey`/
 * `badges`) and the date columns.
 */

const workOrderColumnRenderers = buildWorkOrderColumnRenderers(vi.fn(), vi.fn())

function renderCell(columnId: string, value: unknown) {
  const renderer = workOrderColumnRenderers[columnId]
  if (!renderer) {
    throw new Error(`Missing renderer for column "${columnId}"`)
  }
  const params = { value } as unknown as ICellRendererParams
  return render(<>{renderer(params)}</>)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('workOrderColumnRenderers.code', () => {
  it('renders the code as a badge', () => {
    renderCell('code', 'COM-0003')
    expect(screen.getByText('COM-0003')).toBeInTheDocument()
  })
})

describe('workOrderColumnRenderers.registry', () => {
  it('renders the registry name', () => {
    renderCell('registry', { id: 7, name: 'Acme S.p.A.' })
    expect(screen.getByText('Acme S.p.A.')).toBeInTheDocument()
  })

  it('renders an em dash when unset', () => {
    renderCell('registry', null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('workOrderColumnRenderers.is_force_closed', () => {
  it('renders true as "Yes"', () => {
    renderCell('is_force_closed', true)
    expect(screen.getByText('Yes')).toBeInTheDocument()
  })

  it('renders false as "No"', () => {
    renderCell('is_force_closed', false)
    expect(screen.getByText('No')).toBeInTheDocument()
  })
})

describe('workOrderColumnRenderers date columns', () => {
  it('renders callback_date without a time part', () => {
    renderCell('callback_date', '2026-09-30')
    expect(screen.getByText(formatDate('2026-09-30'))).toBeInTheDocument()
  })

  it('renders the read-only contract_expiry_date without a time part', () => {
    renderCell('contract_expiry_date', '2027-03-31T00:00:00.000000Z')
    expect(screen.getByText(formatDate('2027-03-31T00:00:00.000000Z'))).toBeInTheDocument()
  })

  it.each(['created_at', 'updated_at'])('renders %s as a datetime', (columnId) => {
    renderCell(columnId, '2026-09-30T14:30:00Z')
    expect(screen.queryByText('—')).not.toBeInTheDocument()
  })
})

describe('workOrderColumnRenderers.renewal_number (spec 0358 AC-029)', () => {
  it('renders "Initial" for period 0', () => {
    renderCell('renewal_number', 0)
    expect(screen.getByText('Initial')).toBeInTheDocument()
  })

  it('renders "Renewal N" for a renewed period', () => {
    renderCell('renewal_number', 2)
    expect(screen.getByText('Renewal 2')).toBeInTheDocument()
  })
})

describe('workOrderColumnRenderers amount columns', () => {
  it.each(['consultancy_amount', 'total_revenue'])('renders %s as a formatted amount', (columnId) => {
    renderCell(columnId, '1250.50')
    expect(screen.getByText('1,250.50')).toBeInTheDocument()
  })
})

describe('workOrderColumnRenderers.line_payment_statuses', () => {
  const groups = [
    {
      name: 'Consulenza: Saldato, Nessuno stato',
      typology: { name: 'Consulenza', color: 'blue' },
      statuses: [{ name: 'Saldato', color: 'green' }, null],
      products: [
        { code: 'CON-001', name: 'Consulenza qualita', status: 'Saldato' },
        { code: 'CON-002', name: 'Consulenza ambiente', status: null },
      ],
    },
    {
      name: 'Ente: Da pagare',
      typology: { name: 'Ente', color: 'violet' },
      statuses: [{ name: 'Da pagare', color: 'red' }],
      products: [{ code: 'ENT-001', name: 'Certificazione', status: 'Da pagare' }],
    },
  ]

  it('shows each typology badge followed by the statuses of its products, a product without status as "No status"', () => {
    renderCell('line_payment_statuses', groups)

    const cell = screen.getByRole('group', { name: 'Consulenza: Saldato, Nessuno stato; Ente: Da pagare' })
    expect(within(cell).getByText('Consulenza')).toHaveClass('bg-blue-100')
    expect(within(cell).getByText('Ente')).toHaveClass('bg-violet-100')
    expect(within(cell).getByText('Saldato')).toBeInTheDocument()
    expect(within(cell).getByText('No status')).toBeInTheDocument()
    expect(within(cell).getByText('Da pagare')).toBeInTheDocument()
  })

  it('lists every product with its own status on hover', async () => {
    renderCell('line_payment_statuses', groups)

    fireEvent.focus(screen.getByRole('group'))

    const tooltip = await screen.findByRole('tooltip')
    expect(within(tooltip).getByText('CON-002')).toBeInTheDocument()
    expect(within(tooltip).getByText('Consulenza ambiente')).toBeInTheDocument()
    expect(within(tooltip).getByText('ENT-001')).toBeInTheDocument()
  })

  it('renders an em dash when the work order has no products', () => {
    renderCell('line_payment_statuses', [])
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('workOrderColumnRenderers last note columns', () => {
  function renderLastNote(columnId: string, row: Record<string, unknown>) {
    const renderer = workOrderColumnRenderers[columnId]
    if (!renderer) {
      throw new Error(`Missing renderer for column "${columnId}"`)
    }
    const params = { value: row[columnId], data: row, colDef: { field: columnId } } as unknown as ICellRendererParams
    return render(<>{renderer(params)}</>)
  }

  it.each(['last_note_at', 'supervisor_last_note_at'])('%s shows the date and names author and text', (columnId) => {
    const at = '2026-11-20T15:30:00+01:00'
    renderLastNote(columnId, { [columnId]: at, [`${columnId}_author`]: 'Anna Bianchi', [`${columnId}_excerpt`]: 'Called the client' })

    const trigger = screen.getByText(formatDate(at))
    expect(trigger).toHaveAccessibleName(expect.stringContaining('Anna Bianchi'))
    expect(trigger).toHaveAccessibleName(expect.stringContaining('Called the client'))
  })

  it('renders an empty cell when the commessa has no note', () => {
    const { container } = renderLastNote('last_note_at', { last_note_at: null })
    expect(container).not.toHaveTextContent('Anna')
    expect(screen.queryByRole('generic', { name: /Note by/ })).toBeNull()
  })
})
