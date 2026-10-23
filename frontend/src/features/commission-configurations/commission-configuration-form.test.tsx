import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { CommissionConfigurationForm } from './commission-configuration-form'
import { configurationFixture, configurationWrapper, permissionsFor } from './commission-configuration-test-fixtures'

/**
 * Wave 4 of the "no edit pages" plan (spec 0241): the form is create-only, a
 * replica of the detail with its rows closed; the in-place edits live in
 * `commission-configuration-detail.test.tsx`.
 */

const createMock = vi.fn()
const useMeta = vi.hoisted(() => vi.fn())

vi.mock('./api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('./api')>()),
  createCommissionConfiguration: (...args: unknown[]) => createMock(...args),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))
vi.mock('@/features/authorization/use-resource-meta', () => ({
  useResourceMeta: (...args: unknown[]) => useMeta(...args),
}))
vi.mock('@/components/form/relation-select-field', async () => ({
  RelationSelectField: (await import('./commission-configuration-relation-double')).RelationSelectFieldDouble,
}))
// The picked ids resolve their closed-row names through the for-select hydration.
vi.mock('@/features/for-select/use-for-select', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/for-select/use-for-select')>()),
  useForSelectLabels: ({ ids }: { ids: number[] }) => new Map(ids.map((id) => [id, { id, label: `Name ${id}` }])),
}))
vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

const openRow = (label: string) =>
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: label }) }))
const applyRow = () => fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.apply') }))
const save = () => fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

function renderForm(props: { supplierRegistryId?: number | null; onSuccess?: () => void; onCancel?: () => void } = {}) {
  return render(
    <CommissionConfigurationForm onSuccess={props.onSuccess ?? vi.fn()} onCancel={props.onCancel ?? vi.fn()} supplierRegistryId={props.supplierRegistryId} />,
    { wrapper: configurationWrapper() },
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createMock.mockReset()
  useMeta.mockReturnValue({ data: { permissions: permissionsFor() }, isError: false, refetch: vi.fn() })
})

describe('CommissionConfigurationForm metadata states', () => {
  it('renders a skeleton while create metadata loads', () => {
    useMeta.mockReturnValue({ data: undefined, isError: false, refetch: vi.fn() })
    const { container } = renderForm()

    expect(container.querySelectorAll('[data-slot="skeleton"]').length).toBeGreaterThan(0)
  })

  it('renders retry on metadata error', () => {
    const refetch = vi.fn()
    useMeta.mockReturnValue({ data: undefined, isError: true, refetch })
    renderForm()

    fireEvent.click(screen.getByRole('button', { name: 'Retry' }))
    expect(refetch).toHaveBeenCalled()
  })
})

describe('CommissionConfigurationForm create (spec 0241 AC-004)', () => {
  it('starts with every row closed, each opening on its own control', () => {
    renderForm()

    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    openRow('Configuration name')
    expect(screen.getByLabelText(/^Configuration name/)).toBeInTheDocument()
    applyRow()
    openRow('Recipient')
    expect(screen.getByRole('combobox', { name: /^Recipient role/ })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: /^Recipient type/ })).toBeInTheDocument()
    applyRow()
    openRow('Application scope')
    expect(screen.getByRole('combobox', { name: /^Application scope/ })).toBeInTheDocument()
    applyRow()
    openRow('Validity')
    expect(screen.getByLabelText(/^Valid from/)).toBeInTheDocument()
  })

  it('shows the errors on the closed rows and does not call the API when required data is missing', async () => {
    renderForm()

    save()

    expect(await screen.findByText('Configuration name is required.')).toHaveAttribute('role', 'alert')
    expect(await screen.findByText('Select a product category.')).toBeInTheDocument()
    expect(createMock).not.toHaveBeenCalled()
  })

  it('submits the create payload on save, the category scope nulling the product', async () => {
    createMock.mockResolvedValue(configurationFixture())
    const onSuccess = vi.fn()
    renderForm({ onSuccess })

    openRow('Configuration name')
    fireEvent.change(screen.getByLabelText(/^Configuration name/), { target: { value: 'Network rule' } })
    applyRow()
    openRow('Application scope')
    fireEvent.click(screen.getByRole('button', { name: 'pick product_category_id' }))
    applyRow()
    expect(screen.getByText('Name 99')).toBeInTheDocument()
    openRow('Commission value')
    fireEvent.change(screen.getByLabelText(/^Commission value/), { target: { value: '7' } })
    applyRow()
    save()

    await waitFor(() => expect(createMock).toHaveBeenCalledTimes(1))
    expect(createMock).toHaveBeenCalledWith(
      expect.objectContaining({
        name: 'Network rule',
        recipient_role: 'COMMERCIAL',
        application_scope: 'PRODUCT_CATEGORY',
        product_category_id: 99,
        product_id: null,
        recipient_type: 'referent',
        recipient_id: null,
        commission_type: 'PERCENTAGE',
        value: 7,
        valid_until: null,
        internal_note: null,
        status: 'ACTIVE',
      }),
    )
    await waitFor(() => expect(onSuccess).toHaveBeenCalled())
  })

  it('opens on a personal SUPPLIER rule for the supplier a registry tab seeded (spec 0204)', () => {
    renderForm({ supplierRegistryId: 12 })

    expect(screen.getByText('Supplier')).toBeInTheDocument()
    expect(screen.getByText('Name 12')).toBeInTheDocument()
    expect(screen.getByText('Specific recipient')).toBeInTheDocument()
    openRow('Recipient')
    expect(screen.getByText('Recipient: registries: 12')).toBeInTheDocument()
    expect(screen.queryByRole('combobox', { name: /^Recipient type/ })).not.toBeInTheDocument()
  })

  it('asks for the direction only with the Supplier role and sends it (spec 0306 AC-010)', async () => {
    createMock.mockResolvedValue(configurationFixture())
    renderForm()

    openRow('Recipient')
    expect(screen.queryByRole('combobox', { name: /^Supplier commission direction/ })).not.toBeInTheDocument()
    fireEvent.click(screen.getByRole('combobox', { name: /^Recipient role/ }))
    fireEvent.click(await screen.findByRole('option', { name: 'Supplier' }))
    expect(screen.getByRole('combobox', { name: /^Supplier commission direction/ })).toBeInTheDocument()
    applyRow()

    save()
    expect(await screen.findByText('The direction is required for a Supplier rule.')).toBeInTheDocument()
    expect(createMock).not.toHaveBeenCalled()
  })

  it('drops the direction and sends null when the role leaves Supplier (spec 0306 AC-010)', async () => {
    createMock.mockResolvedValue(configurationFixture())
    renderForm()

    openRow('Recipient')
    fireEvent.click(screen.getByRole('combobox', { name: /^Recipient role/ }))
    fireEvent.click(await screen.findByRole('option', { name: 'Supplier' }))
    fireEvent.click(screen.getByRole('combobox', { name: /^Supplier commission direction/ }))
    fireEvent.click(await screen.findByRole('option', { name: 'Received' }))
    fireEvent.click(screen.getByRole('combobox', { name: /^Recipient role/ }))
    fireEvent.click(await screen.findByRole('option', { name: 'Reporter' }))
    expect(screen.queryByRole('combobox', { name: /^Supplier commission direction/ })).not.toBeInTheDocument()
    applyRow()

    openRow('Configuration name')
    fireEvent.change(screen.getByLabelText(/^Configuration name/), { target: { value: 'Rule' } })
    applyRow()
    openRow('Application scope')
    fireEvent.click(screen.getByRole('button', { name: 'pick product_category_id' }))
    applyRow()
    save()

    await waitFor(() => expect(createMock).toHaveBeenCalledTimes(1))
    expect(createMock).toHaveBeenCalledWith(
      expect.objectContaining({ recipient_role: 'REPORTER', supplier_commission_direction: null }),
    )
  })

  it('Cancel leaves the form through onCancel', () => {
    const onCancel = vi.fn()
    renderForm({ onCancel })

    fireEvent.click(screen.getAllByRole('button', { name: 'Cancel' })[0])
    expect(onCancel).toHaveBeenCalled()
  })
})
