import { render, screen } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import { describe, expect, it } from 'vitest'
import { userColumnRenderers } from '@/features/users/column-renderers'

/**
 * Spec 0166 AC-015/AC-010: `reports_to` is now zero or more managers, rendered
 * as an avatar stack (`UserStackCell`) instead of the single-user chip.
 */

function renderCell(columnId: string, value: unknown) {
  const renderer = userColumnRenderers[columnId]
  if (!renderer) {
    throw new Error(`Missing renderer for column "${columnId}"`)
  }
  const params = { value } as unknown as ICellRendererParams
  return render(<>{renderer(params)}</>)
}

describe('userColumnRenderers.reports_to', () => {
  it('renders one avatar per manager', () => {
    renderCell('reports_to', [
      { id: 1, name: 'Ada Lovelace' },
      { id: 2, name: 'Grace Hopper' },
    ])

    expect(screen.getByText('AL')).toBeInTheDocument()
    expect(screen.getByText('GH')).toBeInTheDocument()
  })

  it('renders an em dash when the user has no manager', () => {
    renderCell('reports_to', [])

    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

/** Spec 0272 D-8: the `employment_status` column reads "In forza" / "Cessato" (the suite runs in the default Italian). */
describe('userColumnRenderers.employment_status', () => {
  it('labels both states', () => {
    renderCell('employment_status', 'employed')
    expect(screen.getByText('In forza')).toBeInTheDocument()
    renderCell('employment_status', 'terminated')
    expect(screen.getByText('Cessato')).toBeInTheDocument()
  })

  it('renders an em dash for an unknown value', () => {
    renderCell('employment_status', null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

/** Spec 0291 AC-009: the grid's contract expiry arrives as an ISO datetime, like `hired_at`, and reads as a date. */
describe('userColumnRenderers.contract_expires_on', () => {
  it('formats the ISO value as a date', () => {
    renderCell('contract_expires_on', '2027-03-01T00:00:00.000000Z')
    expect(screen.getByText('01/03/2027')).toBeInTheDocument()
  })

  it('renders an em dash when there is no expiry', () => {
    renderCell('contract_expires_on', null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})
