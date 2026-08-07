import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestWorkPanelScreen } from '@/features/request-management/request-work-panel'
import { OFFER_LINE_FIBRA } from '@/features/request-management/request-work-panel-fixtures'
import type { RequestWorkPanelWithPermissions } from '@/features/request-management/types'
import type { ForSelectItem } from '@/features/for-select/types'

/**
 * Spec 0078 AC-043/044/046: the "Fonte" picker's field-change-request
 * interception, mounted through the real work panel (mirrors
 * `request-attribution-operator-link.test.tsx`) so the wiring in
 * `InterceptedRelationSelectField`/`RequestAttributionSection` is exercised
 * against the actor's server-derived permissions, not just the shared
 * component in isolation.
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

const requestFieldChangeMock = vi.fn()
vi.mock('@/features/field-change-requests/use-field-change-request-dialog', () => ({
  useRequestFieldChange: () => ({ requestFieldChange: requestFieldChangeMock }),
}))

/** The Fonte picker offers this option in every test below. */
const REFERRAL: ForSelectItem = { id: 40, label: 'Referral' }

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    onItemChange,
    labels,
    disabled,
  }: {
    value: number | null
    onChange: (value: number | null) => void
    onItemChange?: (item: ForSelectItem | null) => void
    labels: { triggerLabel: string }
    disabled?: boolean
  }) => (
    <button
      type="button"
      disabled={disabled}
      data-testid={`select-${labels.triggerLabel}`}
      onClick={() => {
        if (labels.triggerLabel === 'Source') {
          onChange(REFERRAL.id)
          onItemChange?.(REFERRAL)
        }
      }}
    >
      {labels.triggerLabel}: {value ?? 'none'}
    </button>
  ),
}))

const READONLY_SOURCE_PERMISSION = {
  visible: true,
  hidden: false,
  editable: false,
  readonly: true,
  required: false,
  disabled: false,
}

function panel(overrides: Partial<RequestWorkPanelWithPermissions> = {}): RequestWorkPanelWithPermissions {
  return {
    // Deliberately DIFFERENT from `opportunity_id` (spec 0086 D-10): the Fonte
    // picker's field-change-request interception keys on THIS one (the Quote),
    // never the Opportunity — an id that coincided with `opportunity_id` would
    // let an inverted wiring pass the assertion below by accident.
    id: 4001,
    opportunity_id: 8001,
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
    offer_lines: [OFFER_LINE_FIBRA],
    client_identity: null,
    client_contacts: { owner: null, items: [] },
    client_address: null,
    referent_contacts: { owner: null, items: [] },
    next_callback_at: null,
    attribute_values: {},
    applicable_attributes: [],
    attribute_layout: null,
    quote_workflow_status_id: null,
    quote_workflow_status: null,
    quote_workflow_statuses: [],
    context: { estimated_value: null, expected_close_date: null, success_probability: null },
    permissions: { resource: { view: true, create: true, update: true, delete: true, export: true, import: true }, fields: {}, actions: {} },
    ...overrides,
  }
}

function renderPanel() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <RequestWorkPanelScreen id={4001} />
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

const sourceField = () => screen.getByTestId('select-Source')

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchRequestWorkPanelMock.mockReset()
  updateRequestWorkMock.mockReset()
  requestFieldChangeMock.mockReset()
})

describe('RequestAttributionSection — Fonte field-change-request interception (spec 0078)', () => {
  it('readonly + canRequestChange: a pick opens the proposal and never writes the form value (AC-043)', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(
      panel({
        permissions: {
          resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
          fields: { source_id: READONLY_SOURCE_PERMISSION },
          actions: {},
          change_requestable_fields: ['source_id'],
        },
      }),
    )

    renderPanel()
    fireEvent.click(await screen.findByTestId('select-Source'))

    await waitFor(() =>
      expect(requestFieldChangeMock).toHaveBeenCalledWith({
        resource: 'request-management',
        // The Quote id (spec 0086 D-10), not the Opportunity id — the fixture
        // keeps the two apart so an inverted wiring would fail this assertion.
        subjectId: 4001,
        field: 'source_id',
        requestedValue: 40,
        currentLabel: 'Web',
        requestedLabel: 'Referral',
        fieldLabelKey: 'requestManagement.columns.source',
      }),
    )
    // The RHF field never moved: the mock's trigger still reflects the OLD id.
    expect(sourceField()).toHaveTextContent('Source: 30')
  })

  it('editable: a pick writes the form value directly, unchanged behaviour (AC-046)', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderPanel()
    fireEvent.click(await screen.findByTestId('select-Source'))

    await waitFor(() => expect(sourceField()).toHaveTextContent('Source: 40'))
    expect(requestFieldChangeMock).not.toHaveBeenCalled()
  })

  it('readonly WITHOUT canRequestChange: the picker stays disabled, exactly as today', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(
      panel({
        permissions: {
          resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
          fields: { source_id: READONLY_SOURCE_PERMISSION },
          actions: {},
          change_requestable_fields: [],
        },
      }),
    )

    renderPanel()

    expect(await screen.findByTestId('select-Source')).toBeDisabled()
    expect(requestFieldChangeMock).not.toHaveBeenCalled()
  })
})
