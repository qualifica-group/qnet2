import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { formatDate } from '@/lib/formatting/date-display'
import { workOrderColumnRenderers } from '@/features/work-orders/column-renderers'

/**
 * Spec 0093 `data_contract`. `type`/`status` declare an `enumKey`
 * (`work_order_type`/`work_order_status`, confirmed by the backend) and are
 * therefore covered by the generic `BadgeCell` fallback, not by this map —
 * this suite only locks down the entries `workOrderColumnRenderers` DOES
 * own: `code`, `is_force_closed` (a plain boolean with no backend `enumKey`/
 * `badges`) and the date columns.
 */

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

  it.each(['created_at', 'updated_at'])('renders %s as a datetime', (columnId) => {
    renderCell(columnId, '2026-09-30T14:30:00Z')
    expect(screen.queryByText('—')).not.toBeInTheDocument()
  })
})
