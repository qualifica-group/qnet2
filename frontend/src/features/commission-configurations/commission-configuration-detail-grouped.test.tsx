import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { CommissionConfigurationDetailView } from './commission-configuration-detail'
import {
  configurationFixture,
  configurationWrapper,
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

describe('CommissionConfigurationDetailView grouped rows', () => {
  it('the validity row saves start and end together', async () => {
    updateMock.mockResolvedValue(configurationFixture())
    renderDetail()

    openRow(VALIDITY)
    fireEvent.change(screen.getByLabelText(/^Valid from/), { target: { value: '2026-08-01' } })
    fireEvent.change(screen.getByLabelText(/^Valid until/), { target: { value: '2026-12-31' } })
    saveRow()

    await waitFor(() =>
      expect(updateMock).toHaveBeenCalledWith(1, { valid_from: '2026-08-01', valid_until: '2026-12-31' }),
    )
  })

  it('does not save an end before the start, and says so on the open row', async () => {
    renderDetail()

    openRow(VALIDITY)
    fireEvent.change(screen.getByLabelText(/^Valid until/), { target: { value: '2026-01-01' } })
    saveRow()

    expect(await screen.findByText('End date cannot precede start date.')).toBeInTheDocument()
    expect(updateMock).not.toHaveBeenCalled()
  })

  it('a role the recipient type no longer fits saves role, type and a cleared recipient together', async () => {
    updateMock.mockResolvedValue(configurationFixture())
    renderDetail()

    openRow(RECIPIENT)
    pickOption(/^Recipient role/, 'Supplier')
    pickOption(/^Supplier commission direction/, 'Paid')
    saveRow()

    await waitFor(() =>
      expect(updateMock).toHaveBeenCalledWith(1, {
        recipient_role: 'SUPPLIER',
        recipient_type: 'registry',
        recipient_id: null,
        supplier_commission_direction: 'PAID',
      }),
    )
  })

  it('a role switched to Supplier is not saved without a direction, the row says why (spec 0306 AC-010)', async () => {
    renderDetail()

    openRow(RECIPIENT)
    pickOption(/^Recipient role/, 'Supplier')
    saveRow()

    expect((await screen.findAllByText('The direction is required for a Supplier rule.')).length).toBeGreaterThan(0)
    expect(updateMock).not.toHaveBeenCalled()
  })

  it('a Supplier rule shows its direction row and clears it when the role leaves Supplier (spec 0306 AC-010)', async () => {
    updateMock.mockResolvedValue(configurationFixture())
    renderDetail(
      configurationFixture({
        recipient_role: 'SUPPLIER',
        recipient_type: 'registry',
        supplier_commission_direction: 'RECEIVED',
      }),
    )

    expect(screen.getByText('Supplier commission direction')).toBeInTheDocument()
    expect(screen.getByText('Received')).toBeInTheDocument()
    openRow(RECIPIENT)
    pickOption(/^Recipient role/, 'Reporter')
    saveRow()

    await waitFor(() =>
      expect(updateMock).toHaveBeenCalledWith(1, {
        recipient_role: 'REPORTER',
        recipient_type: 'referent',
        recipient_id: null,
        supplier_commission_direction: null,
      }),
    )
  })

  it('picking a recipient saves only the recipient, and a still-admitted role keeps it', async () => {
    updateMock.mockResolvedValue(configurationFixture())
    renderDetail()

    openRow(RECIPIENT)
    pickOption(/^Recipient role/, 'Reporter')
    expect(screen.getByText('Recipient: referents: 5')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'pick recipient_id' }))
    saveRow()

    await waitFor(() => expect(updateMock).toHaveBeenCalledWith(1, { recipient_role: 'REPORTER', recipient_id: 99 }))
  })

  it('hides the recipient type selector for a role that admits a single type', () => {
    renderDetail()

    openRow(RECIPIENT)
    expect(screen.getByRole('combobox', { name: /^Recipient type/ })).toBeInTheDocument()
    pickOption(/^Recipient role/, 'Supplier')
    expect(screen.queryByRole('combobox', { name: /^Recipient type/ })).not.toBeInTheDocument()
  })

  it('moving the scope to a category saves scope, category and the cleared product together', async () => {
    updateMock.mockResolvedValue(configurationFixture())
    renderDetail()

    openRow(SCOPE)
    pickOption(/^Application scope/, 'Product category')
    fireEvent.click(screen.getByRole('button', { name: 'pick product_category_id' }))
    saveRow()

    await waitFor(() =>
      expect(updateMock).toHaveBeenCalledWith(1, {
        application_scope: 'PRODUCT_CATEGORY',
        product_category_id: 99,
        product_id: null,
      }),
    )
  })

  it('does not save a category scope without a category, and says so on the open row', async () => {
    renderDetail()

    openRow(SCOPE)
    pickOption(/^Application scope/, 'Product category')
    saveRow()

    expect(await screen.findByText('Select a product category.')).toBeInTheDocument()
    expect(updateMock).not.toHaveBeenCalled()
  })

  it('shows a server cross-field refusal of the recipient on the open recipient row', async () => {
    updateMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { recipient_id: ['Recipient changed with the role.'] } },
      } as never),
    )
    renderDetail()

    openRow(RECIPIENT)
    pickOption(/^Recipient role/, 'Reporter')
    saveRow()

    expect(await screen.findByText('Recipient changed with the role.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') })).toBeInTheDocument()
  })

  it('shows a server refusal of the scope on the open scope row', async () => {
    updateMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { application_scope: ['Scope does not match.'] } },
      } as never),
    )
    renderDetail()

    openRow(SCOPE)
    pickOption(/^Application scope/, 'Specific recipient')
    saveRow()

    expect(await screen.findByText('Scope does not match.')).toBeInTheDocument()
  })

  it.each([
    { row: RECIPIENT, labels: [/^Recipient role/, /^Recipient type/] },
    { row: VALIDITY, labels: [/^Valid from/, /^Valid until/] },
  ])('the open $row row shows every sub-field label', ({ row, labels }) => {
    renderDetail()

    openRow(row)
    for (const label of labels) {
      const text = screen.getAllByText(label).find((node) => node.tagName === 'LABEL')
      expect(text).toBeDefined()
      expect(text).not.toHaveClass('sr-only')
    }
  })
})
