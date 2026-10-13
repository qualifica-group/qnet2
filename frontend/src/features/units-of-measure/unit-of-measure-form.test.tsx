import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { UnitOfMeasureForm } from '@/features/units-of-measure/unit-of-measure-form'
import type { UnitOfMeasureDetailWithPermissions } from '@/features/units-of-measure/types'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Wave 3 of the "no edit pages" plan (spec 0236): the form is create-only, a
 * replica of the detail with its rows closed plus the create-only `code`; the
 * in-place edits live in `unit-of-measure-detail.test.tsx`.
 */

const createUnitOfMeasureMock = vi.fn()

vi.mock('@/features/units-of-measure/api', () => ({
  createUnitOfMeasure: (...args: unknown[]) => createUnitOfMeasureMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

/** Every field editable, matching the create-context metadata (`code.editable === true` while `$model === null`). */
const CREATE_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/authorization/use-resource-meta', () => ({
  useResourceMeta: () => ({ data: { permissions: CREATE_PERMISSIONS }, isError: false, refetch: vi.fn() }),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function unitOfMeasure(): UnitOfMeasureDetailWithPermissions {
  return {
    id: 9,
    name: 'Kilogram',
    symbol: 'kg',
    code: 'kilogram',
    description: null,
    created_at: null as unknown as string,
    updated_at: null as unknown as string,
    permissions: CREATE_PERMISSIONS,
  }
}

/** Opens a closed row through its pencil. */
function openRow(key: string) {
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(key) }) }))
}

const save = () => fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

function fillRequired(name: string, code: string) {
  openRow('unitsOfMeasure.form.name')
  fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: name } })
  openRow('unitsOfMeasure.form.symbol')
  fireEvent.change(screen.getByLabelText(/^Symbol/), { target: { value: 'kg' } })
  openRow('unitsOfMeasure.form.code')
  fireEvent.change(screen.getByLabelText(/^Code/), { target: { value: code } })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createUnitOfMeasureMock.mockReset()
})

describe('UnitOfMeasureForm — create (spec 0099)', () => {
  it('starts with every row closed; each opens on its own control', () => {
    render(<UnitOfMeasureForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()

    openRow('unitsOfMeasure.form.name')
    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
    openRow('unitsOfMeasure.form.symbol')
    expect(screen.getByLabelText(/^Symbol/)).toBeInTheDocument()
    openRow('unitsOfMeasure.form.code')
    expect(screen.getByLabelText(/^Code/)).toBeInTheDocument()
    openRow('unitsOfMeasure.form.description')
    expect(screen.getByLabelText(/^Description/)).toBeInTheDocument()
  })

  it('shows the error on the closed row and does not call the API when name is empty', async () => {
    render(<UnitOfMeasureForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    save()

    expect(await screen.findByText('Name is required.')).toHaveAttribute('role', 'alert')

    openRow('unitsOfMeasure.form.name')
    const nameInput = screen.getByLabelText(/^Name/)
    expect(nameInput).toHaveAttribute('aria-invalid', 'true')
    expect(nameInput.getAttribute('aria-describedby')).toBeTruthy()
    expect(createUnitOfMeasureMock).not.toHaveBeenCalled()
  })

  it('submits the create payload on save', async () => {
    createUnitOfMeasureMock.mockResolvedValue(unitOfMeasure())
    const onSuccess = vi.fn()

    render(<UnitOfMeasureForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper: wrapper() })

    fillRequired('Kilogram', 'kilogram')
    save()

    await waitFor(() => expect(createUnitOfMeasureMock).toHaveBeenCalledTimes(1))
    expect(createUnitOfMeasureMock).toHaveBeenCalledWith({
      name: 'Kilogram',
      symbol: 'kg',
      code: 'kilogram',
      description: null,
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(unitOfMeasure()))
  })
})
