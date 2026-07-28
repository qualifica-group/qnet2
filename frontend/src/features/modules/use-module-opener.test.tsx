import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter, useLocation } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import type { OpenMode } from '@/features/modules/types'
import type { TableRow } from '@/features/table/types'

/**
 * AC-011/018/019: `useModuleOpener` routes view/edit/create to a modal Sheet
 * or to the dedicated-page deep-links depending on the resolved open mode.
 * The resolved mode and the registry entry are mocked so the test exercises
 * the routing decision alone, with trivial stub screens (no real fetch).
 *
 * AC-001/002/003/004 (spec 0045): `openCreateWith(params)` forwards params to
 * the Sheet's `FormScreen` in modal mode, or serializes them to a query string
 * in page mode. `openCreate` stays a strict zero-parameter function — the
 * regression tests below mount it as `onClick={openCreate}` (the exact shape
 * every production call site uses) and prove React's `MouseEvent` argument
 * never leaks into `mode.params`/the URL.
 */

let currentMode: OpenMode = 'modal'

vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => currentMode,
}))

vi.mock('@/features/modules/module-registry', () => ({
  getModuleRegistryEntry: (domain: string) =>
    domain === 'projects'
      ? {
          domain: 'projects',
          basePath: '/projects',
          defaultMode: 'modal',
          labelKey: 'navigation.projects',
          DetailScreen: ({ id, onEdit }: { id: number; onEdit?: () => void }) => (
            <div>
              <div>{`detail-${id}`}</div>
              {onEdit && <button onClick={onEdit}>detail-edit</button>}
            </div>
          ),
          FormScreen: ({
            mode,
          }: {
            mode: { type: string; params?: Record<string, string | number> }
          }) => (
            <div>
              <div>{`form-${mode.type}`}</div>
              {mode.type === 'create' && <div>{`params:${JSON.stringify(mode.params ?? null)}`}</div>}
            </div>
          ),
        }
      : undefined,
}))

function LocationProbe() {
  const location = useLocation()
  return <div data-testid="location">{`${location.pathname}${location.search}`}</div>
}

function Harness() {
  const { openCreate, openCreateWith, openView, openEdit, sheet } = useModuleOpener('projects')
  return (
    <div>
      <button onClick={() => openView({ id: 5 } as TableRow)}>view</button>
      <button onClick={() => openEdit({ id: 7 } as TableRow)}>edit</button>
      <button onClick={() => openCreate()}>create</button>
      {/*
        Passed straight to `onClick`, exactly as the 24 production call sites do
        (`<Button onClick={openCreate}>`). React hands the handler a MouseEvent,
        so this is the shape that regresses if `openCreate` ever declares a
        parameter: the event would be read as `params` and serialized into the
        query string. The arrow-wrapped button above cannot catch that.
      */}
      <button onClick={openCreate}>create-direct-handler</button>
      <button onClick={() => openCreateWith({ lead_id: 7 })}>create-with-params</button>
      {sheet}
      <LocationProbe />
    </div>
  )
}

function renderHarness() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/projects']}>
        <Harness />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('useModuleOpener', () => {
  describe("page mode", () => {
    beforeEach(() => {
      currentMode = 'page'
    })

    it('AC-019: view/edit/create navigate to the deep-link routes and mount no Sheet', () => {
      renderHarness()

      // No Sheet is returned in page mode.
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument()

      fireEvent.click(screen.getByRole('button', { name: 'view' }))
      expect(screen.getByTestId('location')).toHaveTextContent('/projects/5')

      fireEvent.click(screen.getByRole('button', { name: 'edit' }))
      expect(screen.getByTestId('location')).toHaveTextContent('/projects/7/edit')

      fireEvent.click(screen.getByRole('button', { name: 'create' }))
      expect(screen.getByTestId('location')).toHaveTextContent('/projects/new')

      // Never mounted the modal content.
      expect(screen.queryByText(/^form-/)).not.toBeInTheDocument()
      expect(screen.queryByText(/^detail-/)).not.toBeInTheDocument()
    })

    it('AC-002: openCreate(params) navigates to the deep-link with a query string', () => {
      renderHarness()

      fireEvent.click(screen.getByRole('button', { name: 'create-with-params' }))

      expect(screen.getByTestId('location')).toHaveTextContent('/projects/new?lead_id=7')
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    })

    it('AC-003: openCreate() with no arguments navigates without a trailing "?"', () => {
      renderHarness()

      fireEvent.click(screen.getByRole('button', { name: 'create' }))

      expect(screen.getByTestId('location')).toHaveTextContent('/projects/new')
      expect(screen.getByTestId('location').textContent).not.toContain('?')
    })

    it('AC-003: openCreate passed directly to onClick never serializes the click event', () => {
      renderHarness()

      fireEvent.click(screen.getByRole('button', { name: 'create-direct-handler' }))

      // Regression guard: React passes a MouseEvent to the handler. If
      // `openCreate` ever accepts a parameter, that event lands in `params` and
      // URLSearchParams turns it into `?_reactName=onClick&type=click&...`.
      expect(screen.getByTestId('location')).toHaveTextContent('/projects/new')
      expect(screen.getByTestId('location').textContent).not.toContain('?')
      expect(screen.getByTestId('location').textContent).not.toContain('reactName')
    })
  })

  describe('modal mode', () => {
    beforeEach(() => {
      currentMode = 'modal'
    })

    it('AC-018: create opens the Sheet with the form and does not navigate', () => {
      renderHarness()

      // Sheet is closed initially: no content mounted, still on the list route.
      expect(screen.queryByText('form-create')).not.toBeInTheDocument()

      fireEvent.click(screen.getByRole('button', { name: 'create' }))

      expect(screen.getByText('form-create')).toBeInTheDocument()
      expect(screen.getByTestId('location')).toHaveTextContent('/projects')
    })

    it('AC-001: openCreate(params) mounts the Sheet with mode.params set, no navigation', () => {
      renderHarness()

      fireEvent.click(screen.getByRole('button', { name: 'create-with-params' }))

      expect(screen.getByText('form-create')).toBeInTheDocument()
      expect(screen.getByText('params:{"lead_id":7}')).toBeInTheDocument()
      expect(screen.getByTestId('location')).toHaveTextContent('/projects')
    })

    it('AC-004: openCreate() with no arguments keeps mode.params undefined (retrocompatibility)', () => {
      renderHarness()

      fireEvent.click(screen.getByRole('button', { name: 'create' }))

      expect(screen.getByText('form-create')).toBeInTheDocument()
      expect(screen.getByText('params:null')).toBeInTheDocument()
    })

    it('AC-004: openCreate passed directly to onClick keeps mode.params undefined', () => {
      renderHarness()

      fireEvent.click(screen.getByRole('button', { name: 'create-direct-handler' }))

      // Same regression guard as page mode: a leaked MouseEvent would show up
      // here as a params object instead of `null`.
      expect(screen.getByText('form-create')).toBeInTheDocument()
      expect(screen.getByText('params:null')).toBeInTheDocument()
    })

    it('AC-018: view opens the Sheet with the detail for the row and does not navigate', () => {
      renderHarness()

      fireEvent.click(screen.getByRole('button', { name: 'view' }))

      expect(screen.getByText('detail-5')).toBeInTheDocument()
      expect(screen.getByTestId('location')).toHaveTextContent('/projects')
    })

    it("view's onEdit swaps the SAME Sheet to the edit form for that row, never a second Sheet", () => {
      renderHarness()

      fireEvent.click(screen.getByRole('button', { name: 'view' }))
      expect(screen.getByText('detail-5')).toBeInTheDocument()
      expect(screen.getAllByRole('dialog')).toHaveLength(1)

      fireEvent.click(screen.getByRole('button', { name: 'detail-edit' }))

      expect(screen.queryByText('detail-5')).not.toBeInTheDocument()
      expect(screen.getByText('form-edit')).toBeInTheDocument()
      expect(screen.getAllByRole('dialog')).toHaveLength(1)
      expect(screen.getByTestId('location')).toHaveTextContent('/projects')
    })
  })
})
