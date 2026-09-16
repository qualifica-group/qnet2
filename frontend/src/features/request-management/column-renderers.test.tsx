import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { requestManagementColumnRenderers } from '@/features/request-management/column-renderers'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

/**
 * Spec 0132 D-5: the "Categoria prodotto" cell reads the row's own
 * product-category pairs — the very collection its inline editor commits —
 * and renders the CATEGORY names out of them, with "root > category —
 * funzione: Function" pairs as the native tooltip (same format as the card
 * detail's `ProductLinesReadOnlyList`).
 */

function renderProductCategories(value: unknown) {
  const renderer = requestManagementColumnRenderers.product_categories

  return render(<>{renderer({ value } as ICellRendererParams)}</>)
}

const PAIRS = [
  {
    root_category_id: 6,
    root_category_name: 'Energia',
    business_function_id: 3,
    business_function_name: 'Energia',
    product_category_id: 7,
    product_category_name: 'Luce',
  },
  {
    root_category_id: 6,
    root_category_name: 'Energia',
    business_function_id: 3,
    business_function_name: 'Energia',
    product_category_id: 9,
    product_category_name: 'Gas',
  },
]

describe('request-management product_categories cell (spec 0132 D-5)', () => {
  it('joins the category names and shows "root > category — funzione: X" pairs in the tooltip', () => {
    renderProductCategories(PAIRS)

    const cell = screen.getByText('Luce, Gas')

    expect(cell).toHaveAttribute('title', 'Energia > Luce — function: Energia\nEnergia > Gas — function: Energia')
  })

  it('does not repeat the name when the category is its own root', () => {
    renderProductCategories([
      {
        root_category_id: 17,
        root_category_name: 'Consulenza',
        business_function_id: 3,
        business_function_name: 'Consulenza',
        product_category_id: 17,
        product_category_name: 'Consulenza',
      },
    ])

    const cell = screen.getByText('Consulenza')

    expect(cell).toHaveAttribute('title', 'Consulenza — function: Consulenza')
  })

  it('omits the funzione segment for a pair not yet round-tripped from the server', () => {
    renderProductCategories([
      {
        root_category_id: 6,
        root_category_name: 'Energia',
        product_category_id: 9,
        product_category_name: 'Gas',
      },
    ])

    const cell = screen.getByText('Gas')

    expect(cell).toHaveAttribute('title', 'Energia > Gas')
  })

  it('falls back to the shared empty cell with no product line', () => {
    const { container } = renderProductCategories([])

    expect(container.textContent).not.toContain('Luce')
    expect(container.textContent?.trim()).not.toBe('')
  })
})

function renderPendingChangeRequests(value: unknown) {
  const renderer = requestManagementColumnRenderers.pending_change_requests

  return render(<>{renderer({ value } as ICellRendererParams)}</>)
}

describe('request-management pending_change_requests cell (spec 0078, AC-037)', () => {
  it('renders an alert badge with the count when there are pending requests', () => {
    renderPendingChangeRequests(2)

    const badge = screen.getByLabelText('2 pending change requests')

    expect(badge).toHaveTextContent('2')
  })

  it('renders nothing at zero', () => {
    const { container } = renderPendingChangeRequests(0)

    expect(container).toBeEmptyDOMElement()
  })

  it('renders nothing when the value is missing', () => {
    const { container } = renderPendingChangeRequests(null)

    expect(container).toBeEmptyDOMElement()
  })
})

function renderIsTransferred(value: unknown) {
  const renderer = requestManagementColumnRenderers.is_transferred

  return render(<>{renderer({ value } as ICellRendererParams)}</>)
}

describe('request-management is_transferred cell (spec 0079, AC-022)', () => {
  it('renders the Yes badge, never the raw boolean', () => {
    renderIsTransferred(true)

    expect(screen.getByText('Yes')).toBeInTheDocument()
    expect(screen.queryByText('true')).not.toBeInTheDocument()
  })

  it('renders the No badge for false', () => {
    renderIsTransferred(false)

    expect(screen.getByText('No')).toBeInTheDocument()
  })
})

/**
 * Direttiva utente 2026-09-07: the GA1 column is a second Gestore Account
 * slot, so it must render through the very same shared `UserCell` as the GA2
 * "Operatore" — a plain text cell there would drop the avatar and the
 * hover-card the operator already gets on GA2.
 */
describe('request-management G.A. slot cells (GA2 + GA1)', () => {
  function renderManagerCell(columnId: string, value: unknown) {
    const renderer = requestManagementColumnRenderers[columnId]
    if (!renderer) {
      throw new Error(`Missing renderer for column "${columnId}"`)
    }

    return render(<>{renderer({ value } as ICellRendererParams)}</>)
  }

  it.each(['operator_ga2', 'manager_ga1'])('renders the person behind %s', (columnId) => {
    renderManagerCell(columnId, { id: 21, name: 'Mackenzie Stanton', avatar_url: null })

    expect(screen.getByText('Mackenzie Stanton')).toBeInTheDocument()
  })

  it.each(['operator_ga2', 'manager_ga1'])('renders an em dash when %s is unassigned', (columnId) => {
    renderManagerCell(columnId, null)

    expect(screen.getByText('—')).toBeInTheDocument()
  })
})
