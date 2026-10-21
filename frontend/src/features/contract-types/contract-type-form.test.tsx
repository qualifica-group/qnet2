import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { ContractTypeForm } from '@/features/contract-types/contract-type-form'
import type { ContractType } from '@/features/contract-types/types'
import type { ResourcePermissions } from '@/features/authorization/types'

/** Spec 0290: the create form replicates the detail with closed rows; Save creates everything at once. */

const createMock = vi.fn()

vi.mock('@/features/contract-types/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/contract-types/api')>()),
  createContractType: (...args: unknown[]) => createMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

const PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/authorization/use-resource-meta', () => ({
  useResourceMeta: () => ({ data: { permissions: PERMISSIONS }, isError: false, refetch: vi.fn() }),
}))

/** A QueryClient per test, never per render: a shared cache makes suites flaky. */
function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>
}

const CREATED: ContractType = {
  id: 9,
  name: 'Tempo determinato',
  is_active: true,
  sort_order: 0,
  created_at: '2026-10-19T08:00:00Z',
  updated_at: '2026-10-19T08:00:00Z',
}

const editButton = (key: string) =>
  screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(key) }) })

function typeName(value: string) {
  fireEvent.click(editButton('contractTypes.form.name'))
  fireEvent.change(screen.getByRole('textbox'), { target: { value } })
}

const save = () => fireEvent.click(screen.getAllByRole('button', { name: i18n.t('contractTypes.form.save') })[0])

function rejection(status: number, errors: Record<string, string[]>) {
  return new AxiosError('error', String(status), undefined, undefined, {
    status,
    data: { success: false, message: 'Validation failed', errors },
  } as never)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createMock.mockReset()
})

describe('ContractTypeForm - create (spec 0290)', () => {
  it('lists the three catalogue rows with the agreed labels', () => {
    render(<ContractTypeForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    for (const label of ['Name', 'Active', 'Order']) {
      expect(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: label }) })).toBeInTheDocument()
    }
  })

  it('does not call the API when the name is empty', async () => {
    render(<ContractTypeForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    save()

    expect(await screen.findByText('The name is required.')).toHaveAttribute('role', 'alert')
    expect(createMock).not.toHaveBeenCalled()
  })

  it('creates with the typed name and the defaults', async () => {
    createMock.mockResolvedValue(CREATED)
    const onSuccess = vi.fn()
    render(<ContractTypeForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper: wrapper() })

    typeName('  Tempo determinato ')
    save()

    await waitFor(() =>
      expect(createMock).toHaveBeenCalledWith({
        name: 'Tempo determinato',
        is_active: true,
        sort_order: 0,
      }),
    )
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(CREATED))
  })

  it('maps a server 422 on a duplicated name onto the field with the accessible error triad', async () => {
    createMock.mockRejectedValue(rejection(422, { name: ['The name has already been taken.'] }))
    render(<ContractTypeForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    typeName('Tempo determinato')
    save()

    const message = await screen.findByText('The name has already been taken.')
    expect(message).toHaveAttribute('role', 'alert')
    const input = screen.getByRole('textbox')
    expect(input).toHaveAttribute('aria-invalid', 'true')
    expect(input.getAttribute('aria-describedby')).toContain(message.id)
  })
})
