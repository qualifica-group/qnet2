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
