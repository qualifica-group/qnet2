import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestWorkPanelScreen } from '@/features/request-management/request-work-panel'
import type { RequestWorkPanelWithPermissions } from '@/features/request-management/types'
import type { ForSelectItem } from '@/features/for-select/types'
import type { UserForSelectItem } from '@/features/users/for-select-api'

/**
 * Sede <-> Operatore reciprocal filtering/linking in the "Lavora" panel (user
 * directive 2026-07-23): the same three behaviours the Lead form already has
 * (spec 0048 AC-060..062, `lead-form-body-operator-link.test.tsx`), whose
 * mocking approach this file mirrors — `AsyncPaginatedSelect` is stubbed by
 * its accessible trigger label so a pick can be driven without a real
 * dropdown, and the `params` each render received are exposed for assertion.
 * Mounted through the real panel (like `request-callback-section.test.tsx`)
 * because the payload diff lives in `useRequestWorkForm`/
 * `buildRequestWorkPayload`, not in the section.
 */

const fetchRequestWorkPanelMock = vi.fn()
const updateRequestWorkMock = vi.fn()
vi.mock('@/features/request-management/api', () => ({
  fetchRequestWorkPanel: (...args: unknown[]) => fetchRequestWorkPanelMock(...args),
  updateRequestWork: (...args: unknown[]) => updateRequestWorkMock(...args),
}))

vi.mock('@/features/personal-data/api', () => ({
  createContact: vi.fn(),
  updateContact: vi.fn(),
  deleteContact: vi.fn(),
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

/** An operator carrying its own employment Sede in `meta` — drives the auto-fill. */
const OPERATOR_WITH_SITE: UserForSelectItem = {
  id: 5,
  label: 'Ada Lovelace',
  meta: { operational_site_id: 66, operational_site_label: 'Depot Z' },
}

/** An operator with no employment Sede — must leave the Sede untouched. */
const OPERATOR_NO_SITE: UserForSelectItem = { id: 6, label: 'Bob Noyce', meta: undefined }

const SITE_A: ForSelectItem = { id: 77, label: 'Warehouse A' }
const SITE_B: ForSelectItem = { id: 88, label: 'Warehouse B' }

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    onItemChange,
    labels,
    params,
  }: {
    value: number | null
    onChange: (value: number | null) => void
    onItemChange?: (item: ForSelectItem | null) => void
    labels: { triggerLabel: string }
    params?: Record<string, string | number>
  }) => (
    <button
      type="button"
      data-testid={`select-${labels.triggerLabel}`}
      data-params={params ? JSON.stringify(params) : ''}
      onClick={() => {
        if (labels.triggerLabel === 'Operational site') {
          const next = value === SITE_A.id ? SITE_B : SITE_A
          onChange(next.id)
          onItemChange?.(next)
          return
        }
        if (labels.triggerLabel === 'Operator (GA2)') {
          const next = value === OPERATOR_WITH_SITE.id ? OPERATOR_NO_SITE : OPERATOR_WITH_SITE
          onChange(next.id)
          onItemChange?.(next)
          return
        }
        onChange(3)
        onItemChange?.(null)
      }}
    >
      {value ?? ''}
    </button>
  ),
}))

const FULL_PERMISSIONS = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

function panel(overrides: Partial<RequestWorkPanelWithPermissions> = {}): RequestWorkPanelWithPermissions {
  return {
    id: 1,
    name: 'Enterprise deal',
    registry: { id: 10, name: 'Acme S.p.A.' },
    referent: { id: 20, name: 'Mario Rossi' },
    commercial: null,
    source_id: 30,
    source: { id: 30, name: 'Web' },
    reporter_id: null,
    reporter: null,
    operator_id: null,
    operator: null,
    operational_site_id: null,
    operational_site: null,
    is_transferred: false,
    transferred_from: null,
    status: { source: 'default', distinct_count: 0, entries: [] },
    product_lines: [],
    products_of_interest: [{ id: 700, name: 'Fibra 1000', product_category: { id: 500, name: 'Consulting' } }],
    client_identity: null,
    client_contacts: { owner: null, items: [] },
    client_address: null,
    referent_contacts: { owner: null, items: [] },
    applicable_attributes: [],
    attribute_values: {},
    next_callback_at: null,
    context: { estimated_value: null, expected_close_date: null, success_probability: null },
    permissions: FULL_PERMISSIONS,
    ...overrides,
  }
}

function renderPanel() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <RequestWorkPanelScreen id={1} />
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

const siteField = () => screen.getByTestId('select-Operational site')
const operatorField = () => screen.getByTestId('select-Operator (GA2)')

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchRequestWorkPanelMock.mockReset()
  updateRequestWorkMock.mockReset()
})

describe('Work panel — the Sede scopes the Operatore picker', () => {
  it('sends no filter while the request has no Sede', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderPanel()

    await waitFor(() => expect(operatorField()).toHaveAttribute('data-params', ''))
    expect(screen.queryByText('Only the operators of the selected site.')).not.toBeInTheDocument()
  })

  it('passes the persisted Sede as `operational_site_id` on mount', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(
      panel({ operational_site_id: 77, operational_site: { id: 77, label: 'Warehouse A' } }),
    )

    renderPanel()

    await waitFor(() =>
      expect(operatorField()).toHaveAttribute('data-params', JSON.stringify({ operational_site_id: 77 })),
    )
    expect(screen.getByText('Only the operators of the selected site.')).toBeInTheDocument()
  })

  it('drops the scoping hint when the two fields are not visible', async () => {
    const hidden = { visible: false, hidden: true, editable: false, readonly: false, required: false, disabled: true }
    fetchRequestWorkPanelMock.mockResolvedValue(
      panel({
        operational_site_id: 77,
        operational_site: { id: 77, label: 'Warehouse A' },
        permissions: {
          ...FULL_PERMISSIONS,
          fields: { operator_id: hidden, operational_site_id: hidden },
        },
      }),
    )

    renderPanel()

    await waitFor(() => expect(screen.queryByTestId('select-Operator (GA2)')).not.toBeInTheDocument())
    expect(screen.queryByText('Only the operators of the selected site.')).not.toBeInTheDocument()
  })

  it('re-scopes the picker as soon as a Sede is picked', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderPanel()
    fireEvent.click(await screen.findByTestId('select-Operational site'))

    await waitFor(() =>
      expect(operatorField()).toHaveAttribute('data-params', JSON.stringify({ operational_site_id: SITE_A.id })),
    )
  })
})

describe('Work panel — Operatore auto-fills the Sede', () => {
  it('hydrates the Sede from the picked operator meta, with no extra fetch', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderPanel()
    fireEvent.click(await screen.findByTestId('select-Operator (GA2)'))

    await waitFor(() => expect(siteField()).toHaveTextContent(String(OPERATOR_WITH_SITE.meta?.operational_site_id)))
  })

  it('leaves the Sede untouched for an operator with no Sede', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(
      panel({ operational_site_id: 77, operational_site: { id: 77, label: 'Warehouse A' } }),
    )

    renderPanel()
    // The stub picks OPERATOR_WITH_SITE first, then OPERATOR_NO_SITE.
    fireEvent.click(await screen.findByTestId('select-Operator (GA2)'))
    await waitFor(() => expect(siteField()).toHaveTextContent('66'))
    fireEvent.click(operatorField())

    await waitFor(() => expect(operatorField()).toHaveTextContent(String(OPERATOR_NO_SITE.id)))
    expect(siteField()).toHaveTextContent('66')
  })

  it('does NOT clear the operator it just auto-filled the Sede from', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderPanel()
    fireEvent.click(await screen.findByTestId('select-Operator (GA2)'))

    await waitFor(() => expect(operatorField()).toHaveTextContent(String(OPERATOR_WITH_SITE.id)))
  })
})

describe('Work panel — changing the Sede clears the Operatore', () => {
  it('clears an operator belonging to the previous Sede', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderPanel()
    fireEvent.click(await screen.findByTestId('select-Operator (GA2)')) // operator 5, Sede 66
    await waitFor(() => expect(siteField()).toHaveTextContent('66'))

    fireEvent.click(siteField()) // a REAL Sede pick, different from 66

    // The stub renders `value ?? ''`, so an emptied operator has no text node
    // at all — asserted positively, since `toHaveTextContent('')` passes on
    // any content.
    await waitFor(() => expect(operatorField().textContent).toBe(''))
  })

  it('submits both fields when the Sede pick reassigns the request', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(
      panel({
        operator_id: 5,
        operator: { id: 5, name: 'Ada Lovelace' },
        operational_site_id: 77,
        operational_site: { id: 77, label: 'Warehouse A' },
      }),
    )
    updateRequestWorkMock.mockResolvedValue(panel())

    renderPanel()
    fireEvent.click(await screen.findByTestId('select-Operational site')) // 77 -> SITE_B
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalledTimes(1))
    expect(updateRequestWorkMock.mock.calls[0][1]).toEqual({
      operational_site_id: SITE_B.id,
      operator_id: null,
    })
  })
})
