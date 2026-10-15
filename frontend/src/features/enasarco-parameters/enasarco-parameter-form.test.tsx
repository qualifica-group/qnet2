import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { EnasarcoParameterForm } from '@/features/enasarco-parameters/enasarco-parameter-form'
import type { ResourcePermissions } from '@/features/authorization/types'

const apiMocks = vi.hoisted(() => ({ create: vi.fn() }))

vi.mock('@/features/enasarco-parameters/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/enasarco-parameters/api')>()),
  createEnasarcoParameter: (...args: unknown[]) => apiMocks.create(...args),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

const PERMISSIONS: ResourcePermissions = { resource: {}, fields: {}, actions: {} } as unknown as ResourcePermissions

vi.mock('@/features/authorization/use-resource-meta', () => ({
  useResourceMeta: () => ({ data: { permissions: PERMISSIONS }, isError: false, refetch: vi.fn() }),
}))

const openRow = (label: string) =>
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: label }) }))
const applyRow = () => fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.apply') }))
const save = () => fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

/** Each value goes in through its own closed row (spec 0264 D-5: the create form replicates the detail). */
function enter(label: string, value: string) {
  openRow(label)
  // One row open at a time: its input is the only one on screen.
  fireEvent.change(screen.getByRole('spinbutton'), { target: { value } })
  applyRow()
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>
}

function unprocessable(errors: Record<string, string[]>) {
  return new AxiosError('Unprocessable', '422', undefined, undefined, {
    status: 422,
    data: { success: false, message: 'Validation failed', errors },
  } as never)
}

function fill(year = '2026') {
  enter('Year', year)
  enter('Total rate (%)', '17')
  enter('Agent share (%)', '50')
  enter('Single-firm ceiling', '13000')
  enter('Multi-firm ceiling', '26000')
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  apiMocks.create.mockReset()
})

describe('EnasarcoParameterForm - create draft (spec 0213 AC-023, spec 0264 D-5)', () => {
  it('starts with every row closed', () => {
    render(<EnasarcoParameterForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    expect(screen.queryByRole('spinbutton')).not.toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'New year' })).toBeInTheDocument()
  })

  it('flags an out-of-range year on its closed row without calling the API', async () => {
    render(<EnasarcoParameterForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    fill('1999')
    save()

    const message = await screen.findByText('Enter a year between 2000 and 2100.')
    expect(message).toHaveAttribute('role', 'alert')
    expect(apiMocks.create).not.toHaveBeenCalled()
  })

  it('posts the whole draft on Save', async () => {
    apiMocks.create.mockResolvedValue({ id: 3 })
    render(<EnasarcoParameterForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    fill()
    save()

    await waitFor(() =>
      expect(apiMocks.create).toHaveBeenCalledWith({
        year: 2026,
        total_rate: 17,
        agent_share_percent: 50,
        single_firm_ceiling: 13000,
        multi_firm_ceiling: 26000,
        notes: null,
      }),
    )
  })

  it('shows the server 422 of a duplicated year on the year field', async () => {
    apiMocks.create.mockRejectedValue(unprocessable({ year: ['The year has already been taken.'] }))
    render(<EnasarcoParameterForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    fill()
    save()

    const message = await screen.findByText('The year has already been taken.')
    expect(message).toHaveAttribute('role', 'alert')
  })
})
