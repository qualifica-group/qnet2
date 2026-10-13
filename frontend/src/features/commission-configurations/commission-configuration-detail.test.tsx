import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { formatDate } from '@/lib/formatting/date-display'
import { CommissionConfigurationDetailView } from './commission-configuration-detail'
import {
  EDITABLE,
  FULL_RESOURCE,
  HIDDEN,
  READ_ONLY,
  configurationFixture,
  configurationWrapper,
  permissionsFor,
} from './commission-configuration-test-fixtures'

/**
 * Wave 4 of the "no edit pages" plan (spec 0241): every row edits in place,
 * each save a PATCH of that row's field(s) alone; recipient, scope and validity
 * are grouped rows that save their changed fields together.
 */

const updateMock = vi.fn()

vi.mock('./api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('./api')>()),
  updateCommissionConfiguration: (...args: unknown[]) => updateMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/components/form/relation-select-field', async () => ({
  RelationSelectField: (await import('./commission-configuration-relation-double')).RelationSelectFieldDouble,
}))

// Related-record links open their target through `useModuleOpener`, whose mode
// resolver reads the authenticated user's preference: stubbed, not under test.
vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: () => <div>activity log</div>,
}))

const pencilOf = (label: string) => screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: label }) })
const openRow = (label: string) => fireEvent.click(pencilOf(label) as HTMLElement)
const saveRow = () => fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))
const pickOption = (combobox: RegExp, option: string) => {
  fireEvent.click(screen.getByRole('combobox', { name: combobox }))
  fireEvent.click(screen.getByRole('option', { name: option }))
}

const RECIPIENT = 'Recipient'
const SCOPE = 'Application scope'
const VALIDITY = 'Validity'

function renderDetail(configuration = configurationFixture(), onChanged?: () => void) {
  return render(<CommissionConfigurationDetailView configuration={configuration} onChanged={onChanged} />, {
    wrapper: configurationWrapper(),
  })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  updateMock.mockReset()
})

describe('CommissionConfigurationDetailView read-only content', () => {
  it('does not expose hidden fields or render their empty sections', () => {
    const hidden = Object.fromEntries(
      [
        'name', 'recipient_role', 'application_scope', 'product_id', 'product_category_id', 'recipient_id', 'commission_type',
        'value', 'priority', 'status', 'valid_from', 'valid_until', 'internal_note', 'updated_at',
      ].map((field) => [field, HIDDEN]),
    )
    renderDetail(
      configurationFixture({
        internal_note: 'Secret internal note',
        permissions: permissionsFor(hidden, { ...FULL_RESOURCE, update: false }),
      }),
    )

    expect(screen.getByText('Commission configuration')).toBeInTheDocument()
    expect(screen.queryByText('Router rule')).not.toBeInTheDocument()
    expect(screen.queryByText('Router Pro')).not.toBeInTheDocument()
    expect(screen.queryByText('Secret internal note')).not.toBeInTheDocument()
    expect(screen.queryByText('Recipient and scope')).not.toBeInTheDocument()
    expect(screen.queryByText('Calculation')).not.toBeInTheDocument()
    expect(screen.queryByText('Validity and notes')).not.toBeInTheDocument()
  })

  it.each([
    {
      overrides: {},
      expected: ['Router Pro', '12.5', '%', 'Commercial', 'Jane Referent'],
      validity: `From ${formatDate('2026-07-29')}, no expiry`,
    },
    {
      overrides: {
        application_scope: 'PRODUCT_CATEGORY' as const,
        product: null,
        product_id: null,
        product_category: { id: 4, name: 'Networking' },
        product_category_id: 4,
        commission_type: 'FIXED_AMOUNT' as const,
        value: '25',
        status: 'SUSPENDED' as const,
        valid_until: '2026-12-31',
      },
      expected: ['Networking', '25', '€', 'Suspended'],
      validity: `From ${formatDate('2026-07-29')} to ${formatDate('2026-12-31')}`,
    },
  ])('renders the rule with its scope, unit and validity', ({ overrides, expected, validity }) => {
    renderDetail(configurationFixture(overrides))

    expect(screen.getByRole('heading', { name: 'Router rule' })).toBeInTheDocument()
    for (const text of expected) {
      expect(screen.getByText(text)).toBeInTheDocument()
    }
    expect(screen.getByText(validity)).toBeInTheDocument()
    expect(screen.getByText('activity log')).toBeInTheDocument()
    expect(screen.getByText('Last updated')).toBeInTheDocument()
  })
})

describe('CommissionConfigurationDetailView in-place editing', () => {
  it('has no Edit button: every row is closed with its pencil', () => {
    renderDetail()

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    for (const label of ['Configuration name', RECIPIENT, SCOPE, 'Commission type', 'Commission value', 'Rule priority', 'Status', VALIDITY, 'Internal service note']) {
      expect(pencilOf(label)).toBeInTheDocument()
    }
  })

  it('Save sends only the changed field and reports through onChanged', async () => {
    updateMock.mockResolvedValue(configurationFixture({ name: 'Renamed' }))
    const onChanged = vi.fn()
    renderDetail(configurationFixture(), onChanged)

    openRow('Configuration name')
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    saveRow()

    await waitFor(() => expect(updateMock).toHaveBeenCalledWith(1, { name: 'Renamed' }))
    await waitFor(() => expect(onChanged).toHaveBeenCalled())
  })

  it('the value row saves a number and nothing else', async () => {
    updateMock.mockResolvedValue(configurationFixture())
    renderDetail()

    openRow('Commission value')
    fireEvent.change(screen.getByLabelText(/^Commission value/), { target: { value: '8.5' } })
    saveRow()

    await waitFor(() => expect(updateMock).toHaveBeenCalledWith(1, { value: 8.5 }))
  })

  it('the commission type and status rows pick among their options', async () => {
    updateMock.mockResolvedValue(configurationFixture())
    renderDetail()

    openRow('Commission type')
    pickOption(/^Commission type/, 'Fixed amount')
    saveRow()
    await waitFor(() => expect(updateMock).toHaveBeenLastCalledWith(1, { commission_type: 'FIXED_AMOUNT' }))

    openRow('Status')
    pickOption(/^Status/, 'Suspended')
    saveRow()
    await waitFor(() => expect(updateMock).toHaveBeenLastCalledWith(1, { status: 'SUSPENDED' }))
  })

  it('Cancel restores the row without calling the API', () => {
    renderDetail()

    openRow('Configuration name')
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.cancel') }))

    expect(screen.queryByText('Renamed')).not.toBeInTheDocument()
    expect(screen.getAllByText('Router rule')).toHaveLength(2)
    expect(updateMock).not.toHaveBeenCalled()
  })

  it('offers no pencil without the update permission, nor on a read-only field', () => {
    const { unmount } = renderDetail(
      configurationFixture({ permissions: permissionsFor({}, { ...FULL_RESOURCE, update: false }) }),
    )
    expect(pencilOf('Configuration name')).not.toBeInTheDocument()
    expect(pencilOf(RECIPIENT)).not.toBeInTheDocument()
    expect(pencilOf(VALIDITY)).not.toBeInTheDocument()
    unmount()

    renderDetail(configurationFixture({ permissions: permissionsFor({ name: READ_ONLY, value: EDITABLE }) }))
    expect(pencilOf('Configuration name')).not.toBeInTheDocument()
    expect(pencilOf('Commission value')).toBeInTheDocument()
  })
})
