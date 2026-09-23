import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
import { I18nextProvider } from 'react-i18next'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { ConfirmContext } from '@/components/confirm-dialog-context'
import { createRowActionsRenderer, type RowActionsOptions } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

// Assert against the English catalogue (the app default locale is Italian).
beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function action(
  key: string,
  type: TableActionDefinition['type'] = 'action',
  countField: string | null = null,
): TableActionDefinition {
  return { key, label: key, icon: 'eye', type, confirm: false, count_field: countField }
}

/** Builds a catalog with sequential keys `a0..a{n-1}`, order preserved. */
function catalogOf(n: number): TableActionDefinition[] {
  return Array.from({ length: n }, (_, i) => action(`a${i}`))
}

function renderActions(
  catalog: TableActionDefinition[],
  onAction = vi.fn(),
  rowOverrides: Partial<TableRow> = {},
  options: RowActionsOptions = {},
) {
  const Cell = createRowActionsRenderer(catalog, onAction, options)
  const row: TableRow = { id: 1, actions: catalog.map((a) => a.key), ...rowOverrides }
  render(
    <I18nextProvider i18n={i18n}>
      <ConfirmContext.Provider value={async () => true}>
        <Cell {...({ data: row } as ICellRendererParams)} />
      </ConfirmContext.Provider>
    </I18nextProvider>,
  )
  return { onAction }
}

describe('RowActions overflow behavior', () => {
  it('renders every action inline (no overflow menu) with up to 3 actions', () => {
    renderActions(catalogOf(3))

    expect(screen.getByRole('button', { name: 'a0' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'a1' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'a2' })).toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: 'More actions' }),
    ).not.toBeInTheDocument()
  })

  it('renders the first 3 inline plus a three-dots trigger with more than 3 actions', () => {
    renderActions(catalogOf(6))

    expect(screen.getByRole('button', { name: 'a0' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'a1' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'a2' })).toBeInTheDocument()
    // Overflowed actions are not rendered inline.
    expect(screen.queryByRole('button', { name: 'a3' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'a4' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'a5' })).not.toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'More actions' }),
    ).toBeInTheDocument()
  })

  it('shows only the remaining actions in the overflow menu, order preserved', () => {
    renderActions(catalogOf(6))

    // Radix' DropdownMenu trigger opens on `pointerdown`, not `click`.
    fireEvent.pointerDown(screen.getByRole('button', { name: 'More actions' }), {
      button: 0,
      ctrlKey: false,
    })
    const menu = screen.getByRole('menu')
    const items = within(menu).getAllByRole('menuitem')

    expect(items).toHaveLength(3)
    expect(items[0]).toHaveTextContent('a3')
    expect(items[1]).toHaveTextContent('a4')
    expect(items[2]).toHaveTextContent('a5')
    // The inline actions never leak into the menu.
    expect(within(menu).queryByText('a0')).not.toBeInTheDocument()
  })

  it('invokes the handler for both an inline and an overflow action', () => {
    const { onAction } = renderActions(catalogOf(6))

    fireEvent.click(screen.getByRole('button', { name: 'a0' }))
    expect(onAction).toHaveBeenCalledWith(
      expect.objectContaining({ key: 'a0' }),
      expect.objectContaining({ id: 1 }),
    )

    fireEvent.pointerDown(screen.getByRole('button', { name: 'More actions' }), {
      button: 0,
      ctrlKey: false,
    })
    fireEvent.click(within(screen.getByRole('menu')).getByText('a5'))
    expect(onAction).toHaveBeenCalledWith(
      expect.objectContaining({ key: 'a5' }),
      expect.objectContaining({ id: 1 }),
    )
  })
})

describe('RowActions count badge', () => {
  it('renders the count badge for an action with a count_field and includes it in the aria-label', () => {
    const catalog = [action('documents', 'action', 'documents_count')]
    renderActions(catalog, vi.fn(), { documents_count: 3 })

    const button = screen.getByRole('button', { name: 'documents (3)' })
    expect(button).toBeInTheDocument()
    expect(within(button).getByText('3')).toBeInTheDocument()
  })

  it('renders no badge for an action without a count_field', () => {
    const catalog = [action('view')]
    renderActions(catalog, vi.fn(), { documents_count: 3 })

    expect(screen.getByRole('button', { name: 'view' })).toBeInTheDocument()
    expect(screen.queryByText('3')).not.toBeInTheDocument()
  })

  it('renders no badge when the count is 0', () => {
    const catalog = [action('documents', 'action', 'documents_count')]
    renderActions(catalog, vi.fn(), { documents_count: 0 })

    expect(screen.getByRole('button', { name: 'documents' })).toBeInTheDocument()
    expect(screen.queryByText('0')).not.toBeInTheDocument()
  })
})

describe('RowActions labeledActions (spec 0150 D-8)', () => {
  it('renders inline actions with a visible text label when opted in', () => {
    const { onAction } = renderActions(catalogOf(2), vi.fn(), {}, { labeledActions: true })

    const button = screen.getByRole('button', { name: 'a0' })
    expect(button).toHaveTextContent('a0')
    fireEvent.click(button)
    expect(onAction).toHaveBeenCalledWith(expect.objectContaining({ key: 'a0' }), expect.objectContaining({ id: 1 }))
  })

  it('keeps icon-only buttons (no visible text) by default', () => {
    renderActions(catalogOf(2))

    expect(screen.getByRole('button', { name: 'a0' })).toHaveTextContent('')
  })
})
