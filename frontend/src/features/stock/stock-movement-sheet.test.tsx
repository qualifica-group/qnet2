import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { stockMovementFormValues } from '@/features/stock/stock-movement-payload'
import { buildMovement, httpError, stockWrapper } from '@/features/stock/stock-fixtures'
import { StockMovementSheet } from '@/features/stock/stock-movement-sheet'
import type { StockMovementType } from '@/features/stock/types'

// The pickers render the quick-create "+" (spec 0028), gated by `<Can>`: no abilities here, so it stays hidden.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

const recordMock = vi.fn()
vi.mock('@/features/stock/api', () => ({
  recordStockMovement: (payload: unknown) => recordMock(payload),
}))

const pickers = vi.hoisted(() => [] as { resource: string; params?: Record<string, unknown> }[])
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    resource,
    params,
    value,
    onChange,
    labels,
    ...rest
  }: {
    resource: string
    params?: Record<string, unknown>
    value: number | null
    onChange: (value: number | null) => void
    labels: { triggerLabel: string }
    id?: string
    'aria-describedby'?: string
    'aria-invalid'?: boolean
  }) => {
    pickers.push({ resource, params })
    return (
      <select
        id={rest.id}
        aria-describedby={rest['aria-describedby']}
        aria-invalid={rest['aria-invalid']}
        aria-label={labels.triggerLabel}
        value={value === null ? '' : String(value)}
        onChange={(event) => onChange(event.target.value === '' ? null : Number(event.target.value))}
      >
        <option value="">-</option>
        <option value="3">Opzione 3</option>
        <option value="7">Opzione 7</option>
      </select>
    )
  },
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const label = (key: string) => i18n.t(key)
/** Anchored: a required label also renders a trailing "*". */
const pattern = (key: string) => new RegExp(`^${label(key)}\\s*\\*?$`)
const save = () => fireEvent.click(screen.getAllByRole('button', { name: label('stock.form.save') })[0])
const onRecorded = vi.fn()

function renderSheet(type: StockMovementType = 'LOAD', row?: Parameters<typeof stockMovementFormValues>[1]) {
  const { client, wrapper } = stockWrapper()
  render(<StockMovementSheet initialValues={stockMovementFormValues(type, row)} onClose={vi.fn()} onRecorded={onRecorded} />, {
    wrapper,
  })
  return client
}

async function pickType(type: StockMovementType) {
  fireEvent.keyDown(screen.getByRole('combobox', { name: pattern('stock.form.type') }), { key: 'ArrowDown' })
  fireEvent.click(await screen.findByRole('option', { name: label(`enums.stock_movement_type.${type}`) }))
}

function pick(field: string, id: number) {
  fireEvent.change(screen.getByRole('combobox', { name: pattern(field) }), { target: { value: String(id) } })
}

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  recordMock.mockReset()
  onRecorded.mockReset()
  pickers.length = 0
})

describe('StockMovementSheet (spec 0307 AC-018)', () => {
  it('asks only product, site and quantity for a Carico, and the product picker requests tracked products only', () => {
    renderSheet('LOAD')

    expect(screen.getByRole('combobox', { name: pattern('stock.form.product') })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: pattern('stock.form.site') })).toBeInTheDocument()
    expect(screen.getByLabelText(pattern('stock.form.quantity'))).toBeInTheDocument()
    expect(screen.queryByRole('combobox', { name: pattern('stock.form.optionalHolder') })).not.toBeInTheDocument()
    expect(screen.queryByRole('combobox', { name: pattern('stock.form.toSite') })).not.toBeInTheDocument()
    expect(screen.queryByLabelText(pattern('stock.form.countedQuantity'))).not.toBeInTheDocument()
    expect(pickers.find((picker) => picker.resource === 'products')?.params).toEqual({ usage: 'INTERNAL' })
    expect(pickers.map((picker) => picker.resource)).toEqual(expect.arrayContaining(['products', 'operational-sites']))
  })

  it('changes its fields with the type: recipient, destination site, counted quantity', async () => {
    renderSheet('LOAD')

    await pickType('ASSIGN')
    expect(screen.getByRole('combobox', { name: pattern('stock.form.recipient') })).toBeInTheDocument()

    await pickType('TRANSFER')
    expect(screen.getByRole('combobox', { name: pattern('stock.form.toSite') })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: pattern('stock.form.fromSite') })).toBeInTheDocument()

    await pickType('ADJUSTMENT')
    expect(screen.getByLabelText(pattern('stock.form.countedQuantity'))).toBeInTheDocument()
    expect(screen.queryByLabelText(pattern('stock.form.quantity'))).not.toBeInTheDocument()
  })

  it.each(['UNLOAD', 'ADJUSTMENT'] as const)('requires the note for %s and sends nothing without it', async (type) => {
    renderSheet(type, { product_id: 3, operational_site_id: 7, user_id: null })
    fireEvent.change(screen.getByLabelText(pattern(type === 'ADJUSTMENT' ? 'stock.form.countedQuantity' : 'stock.form.quantity')), {
      target: { value: '2' },
    })
    save()

    expect(await screen.findByText(label('stock.form.noteRequired'))).toBeInTheDocument()
    expect(recordMock).not.toHaveBeenCalled()
  })

  it('shows the required errors with the accessible triad', async () => {
    renderSheet('ASSIGN')
    save()

    const alert = await screen.findByText(label('stock.form.userRequired'))
    expect(alert).toHaveAttribute('role', 'alert')
    const recipient = screen.getByRole('combobox', { name: pattern('stock.form.recipient') })
    expect(recipient).toHaveAttribute('aria-invalid', 'true')
    expect(recipient.getAttribute('aria-describedby')).toContain(alert.id)
    expect(recordMock).not.toHaveBeenCalled()
  })

  it('prefills from the row (an Assegna starts from the free row and leaves the recipient to pick)', () => {
    renderSheet('ASSIGN', { product_id: 3, operational_site_id: 7, user_id: null })

    expect(screen.getByRole('combobox', { name: pattern('stock.form.product') })).toHaveValue('3')
    expect(screen.getByRole('combobox', { name: pattern('stock.form.site') })).toHaveValue('7')
    expect(screen.getByRole('combobox', { name: pattern('stock.form.recipient') })).toHaveValue('')
  })

  it('maps a server 422 onto the field with the accessible triad', async () => {
    recordMock.mockRejectedValue(httpError(422, { errors: { quantity: ['Disponibili solo 4.'] } }))
    renderSheet('UNLOAD', { product_id: 3, operational_site_id: 7, user_id: null })
    fireEvent.change(screen.getByLabelText(pattern('stock.form.quantity')), { target: { value: '5' } })
    fireEvent.change(screen.getByLabelText(pattern('stock.form.note')), { target: { value: 'Dismesso' } })
    save()

    const alert = await screen.findByText('Disponibili solo 4.')
    expect(alert).toHaveAttribute('role', 'alert')
    const quantity = screen.getByLabelText(pattern('stock.form.quantity'))
    expect(quantity).toHaveAttribute('aria-invalid', 'true')
    expect(quantity.getAttribute('aria-describedby')).toContain(alert.id)
    expect(onRecorded).not.toHaveBeenCalled()
  })

  it('sends a Carico without user and refreshes the stock queries and the grids on success', async () => {
    recordMock.mockResolvedValue({ movement: buildMovement(), balances: [] })
    const client = renderSheet('LOAD')
    const invalidate = vi.spyOn(client, 'invalidateQueries')
    pick('stock.form.product', 3)
    pick('stock.form.site', 7)
    fireEvent.change(screen.getByLabelText(pattern('stock.form.quantity')), { target: { value: '5,5' } })
    save()

    await waitFor(() => expect(onRecorded).toHaveBeenCalledTimes(1))
    expect(recordMock).toHaveBeenCalledWith({ type: 'LOAD', product_id: 3, operational_site_id: 7, quantity: '5.5' })
    expect(invalidate).toHaveBeenCalledWith({ queryKey: ['stock'] })
    expect(invalidate).toHaveBeenCalledWith({ queryKey: ['products', 'detail'] })
  })

  it('sends the counted quantity of an Adjustment instead of a quantity', async () => {
    recordMock.mockResolvedValue({ movement: buildMovement({ type: 'ADJUSTMENT' }), balances: [] })
    renderSheet('ADJUSTMENT', { product_id: 3, operational_site_id: 7, user_id: 7 })
    fireEvent.change(screen.getByLabelText(pattern('stock.form.countedQuantity')), { target: { value: '0' } })
    fireEvent.change(screen.getByLabelText(pattern('stock.form.note')), { target: { value: 'Inventario' } })
    save()

    await waitFor(() => expect(onRecorded).toHaveBeenCalled())
    expect(recordMock).toHaveBeenCalledWith({
      type: 'ADJUSTMENT',
      product_id: 3,
      operational_site_id: 7,
      user_id: 7,
      counted_quantity: '0',
      note: 'Inventario',
    })
  })
})
