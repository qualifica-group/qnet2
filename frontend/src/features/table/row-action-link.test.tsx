import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { I18nextProvider } from 'react-i18next'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { ConfirmContext } from '@/components/confirm-dialog-context'
import { createRowActionsRenderer, type ActionHrefResolver, type RowActionsOptions } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * User directive 2026-11-10: a row action that leads to a page is a real link,
 * so Ctrl/Cmd+click opens the page in a new tab; a plain click keeps running
 * the domain's action (modal or same-tab page).
 */

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function action(key: string): TableActionDefinition {
  return { key, label: key, icon: 'eye', type: 'action', confirm: false, count_field: null }
}

/** `view` and `a4` lead to a page, the other actions do not. */
const resolveActionHref: ActionHrefResolver = (definition, row) =>
  definition.key === 'view' || definition.key === 'a4' ? `/things/${row.id}/${definition.key}` : undefined

function LocationProbe() {
  return <span data-testid="location">{useLocation().pathname}</span>
}

function renderActions(keys: string[], options: RowActionsOptions = {}) {
  const catalog = keys.map(action)
  const onAction = vi.fn()
  const Cell = createRowActionsRenderer(catalog, onAction, { resolveActionHref, ...options })
  const row: TableRow = { id: 7, actions: keys }
  render(
    <MemoryRouter initialEntries={['/things']}>
      <I18nextProvider i18n={i18n}>
        <ConfirmContext.Provider value={async () => true}>
          <Routes>
            <Route path="*" element={<Cell {...({ data: row } as ICellRendererParams)} />} />
          </Routes>
          <LocationProbe />
        </ConfirmContext.Provider>
      </I18nextProvider>
    </MemoryRouter>,
  )
  return { onAction }
}

function openOverflowMenu() {
  // Radix' DropdownMenu trigger opens on `pointerdown`, not `click`.
  fireEvent.pointerDown(screen.getByRole('button', { name: 'More actions' }), { button: 0, ctrlKey: false })
}

describe('RowActions links to the action page', () => {
  it('renders an inline action with a page as a link to it, the others as buttons', () => {
    renderActions(['view', 'a1'])

    expect(screen.getByRole('link', { name: 'view' })).toHaveAttribute('href', '/things/7/view')
    expect(screen.getByRole('button', { name: 'a1' })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'a1' })).not.toBeInTheDocument()
  })

  it('runs the action on a plain click without navigating', () => {
    const { onAction } = renderActions(['view'])

    fireEvent.click(screen.getByRole('link', { name: 'view' }))

    expect(onAction).toHaveBeenCalledWith(expect.objectContaining({ key: 'view' }), expect.objectContaining({ id: 7 }))
    expect(screen.getByTestId('location')).toHaveTextContent('/things')
  })

  it.each([{ ctrlKey: true }, { metaKey: true }])('leaves a modified click (%o) to the browser, without running the action', (modifier) => {
    const { onAction } = renderActions(['view'])

    fireEvent.click(screen.getByRole('link', { name: 'view' }), modifier)

    expect(onAction).not.toHaveBeenCalled()
    expect(screen.getByTestId('location')).toHaveTextContent('/things')
  })

  it('renders a disabled action as a plain button, never a link', () => {
    renderActions(['view'], { resolveActionState: () => ({ disabled: true }) })

    expect(screen.queryByRole('link', { name: 'view' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'view' })).toBeDisabled()
  })

  it('links an overflow menu action and runs it on a plain click, closing the menu', () => {
    const { onAction } = renderActions(['a0', 'a1', 'a2', 'a3', 'a4'])
    openOverflowMenu()

    const item = screen.getByRole('menuitem', { name: 'a4' })
    expect(item).toHaveAttribute('href', '/things/7/a4')
    fireEvent.click(item)

    expect(onAction).toHaveBeenCalledWith(expect.objectContaining({ key: 'a4' }), expect.objectContaining({ id: 7 }))
    expect(screen.getByTestId('location')).toHaveTextContent('/things')
    expect(screen.queryByRole('menu')).not.toBeInTheDocument()
  })

  it('leaves a modified click on an overflow menu action to the browser', () => {
    const { onAction } = renderActions(['a0', 'a1', 'a2', 'a3', 'a4'])
    openOverflowMenu()

    fireEvent.click(screen.getByRole('menuitem', { name: 'a4' }), { ctrlKey: true })

    expect(onAction).not.toHaveBeenCalled()
    expect(screen.getByTestId('location')).toHaveTextContent('/things')
  })

  it('keeps an overflow action without a page as a plain menu item', () => {
    const { onAction } = renderActions(['a0', 'a1', 'a2', 'a3', 'a4'])
    openOverflowMenu()

    const item = screen.getByRole('menuitem', { name: 'a3' })
    expect(item).not.toHaveAttribute('href')
    fireEvent.click(item)

    expect(onAction).toHaveBeenCalledWith(expect.objectContaining({ key: 'a3' }), expect.objectContaining({ id: 7 }))
  })
})
