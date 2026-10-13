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

vi.mock('@/features/enasarco-parameters/use-enasarco-parameter-form-meta', () => ({
  useEnasarcoParameterFormMeta: () => ({ status: 'ready', permissions: PERMISSIONS }),
}))

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

function fill() {
  fireEvent.change(screen.getByLabelText(/^Year/), { target: { value: '2026' } })
  fireEvent.change(screen.getByLabelText(/^Total rate/), { target: { value: '17' } })
  fireEvent.change(screen.getByLabelText(/^Agent share/), { target: { value: '50' } })
  fireEvent.change(screen.getByLabelText(/^Single-firm ceiling/), { target: { value: '13000' } })
  fireEvent.change(screen.getByLabelText(/^Multi-firm ceiling/), { target: { value: '26000' } })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  apiMocks.create.mockReset()
})

describe('EnasarcoParameterForm (spec 0213 AC-023)', () => {
  it('flags an out-of-range year accessibly without calling the API', async () => {
    render(<EnasarcoParameterForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    fill()
    fireEvent.change(screen.getByLabelText(/^Year/), { target: { value: '1999' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    const year = screen.getByLabelText(/^Year/)
    await waitFor(() => expect(year).toHaveAttribute('aria-invalid', 'true'))
    const message = screen.getByText('Enter a year between 2000 and 2100.')
    expect(message).toHaveAttribute('role', 'alert')
    expect(year.getAttribute('aria-describedby')).toContain(message.id)
    expect(apiMocks.create).not.toHaveBeenCalled()
  })

  it('shows the server 422 of a duplicated year on the year field', async () => {
    apiMocks.create.mockRejectedValue(unprocessable({ year: ['The year has already been taken.'] }))
    render(<EnasarcoParameterForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    fill()
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    const message = await screen.findByText('The year has already been taken.')
    expect(message).toHaveAttribute('role', 'alert')
    expect(screen.getByLabelText(/^Year/)).toHaveAttribute('aria-invalid', 'true')
  })
})
