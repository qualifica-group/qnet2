import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { AbsenceTypeForm } from '@/features/absence-types/absence-type-form'
import type { AbsenceType } from '@/features/absence-types/types'
import type { ResourcePermissions } from '@/features/authorization/types'

/** Spec 0278: the create form replicates the detail with closed rows; Save creates everything at once. */

const createMock = vi.fn()

vi.mock('@/features/absence-types/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/absence-types/api')>()),
  createAbsenceType: (...args: unknown[]) => createMock(...args),
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

const CREATED: AbsenceType = {
  id: 9,
  name: 'Ferie',
  deducts_balance: true,
  requires_approval: true,
  is_active: true,
  sort_order: 0,
  allows_full_day: true,
  allows_half_day: true,
  allows_hourly: true,
  min_notice_days: null,
  color: 'slate',
  default_annual_days: null,
  created_at: '2026-10-19T08:00:00Z',
  updated_at: '2026-10-19T08:00:00Z',
}

const editButton = (key: string) =>
  screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(key) }) })

function typeName(value: string) {
  fireEvent.click(editButton('absenceTypes.form.name'))
  fireEvent.change(screen.getByRole('textbox'), { target: { value } })
}

const save = () => fireEvent.click(screen.getAllByRole('button', { name: i18n.t('absenceTypes.form.save') })[0])

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

describe('AbsenceTypeForm - create (spec 0278)', () => {
  it('lists the catalogue rows with the agreed labels', () => {
    render(<AbsenceTypeForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    for (const label of ['Name', 'Deducts a balance', 'Requires approval', 'Active', 'Order', 'Full day', 'Half day', 'Hourly', 'Minimum notice', 'Color']) {
      expect(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: label }) })).toBeInTheDocument()
    }
  })

  it('does not call the API when the name is empty', async () => {
    render(<AbsenceTypeForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    save()

    expect(await screen.findByText('The name is required.')).toHaveAttribute('role', 'alert')
    expect(createMock).not.toHaveBeenCalled()
  })

  it('creates with the typed name and the defaults', async () => {
    createMock.mockResolvedValue(CREATED)
    const onSuccess = vi.fn()
    render(<AbsenceTypeForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper: wrapper() })

    typeName('  Ferie ')
    save()

    await waitFor(() =>
      expect(createMock).toHaveBeenCalledWith({
        name: 'Ferie',
        deducts_balance: false,
        requires_approval: true,
        is_active: true,
        sort_order: 0,
        allows_full_day: true,
        allows_half_day: true,
        allows_hourly: true,
        min_notice_days: null,
        color: 'slate',
        default_annual_days: null,
      }),
    )
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(CREATED))
  })

  it('refuses to save with no mode allowed and wires the error on the full day row', async () => {
    render(<AbsenceTypeForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    typeName('Ferie')
    for (const field of ['Full day', 'Half day', 'Hourly']) {
      fireEvent.click(editButton(`absenceTypes.form.${{ 'Full day': 'allowsFullDay', 'Half day': 'allowsHalfDay', Hourly: 'allowsHourly' }[field]}`))
      fireEvent.click(screen.getByRole('switch'))
    }
    save()

    expect(await screen.findByText(i18n.t('absenceTypes.form.modesRequired'))).toHaveAttribute('role', 'alert')
    expect(createMock).not.toHaveBeenCalled()
  })

  it('shows the default entitlement only when the type deducts a balance and sends it', async () => {
    createMock.mockResolvedValue(CREATED)
    render(<AbsenceTypeForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    expect(screen.queryByText('Default entitlement')).not.toBeInTheDocument()
    typeName('Ferie')
    fireEvent.click(editButton('absenceTypes.form.deductsBalance'))
    fireEvent.click(screen.getByRole('switch'))
    fireEvent.click(editButton('absenceTypes.form.defaultAnnualDays'))
    fireEvent.change(screen.getByRole('spinbutton'), { target: { value: '26.5' } })
    fireEvent.click(editButton('absenceTypes.form.minNoticeDays'))
    fireEvent.change(screen.getByRole('spinbutton'), { target: { value: '5' } })
    save()

    await waitFor(() =>
      expect(createMock).toHaveBeenCalledWith(
        expect.objectContaining({ deducts_balance: true, default_annual_days: 26.5, min_notice_days: 5 }),
      ),
    )
  })

  it('rejects a default entitlement with more than two decimals', async () => {
    render(<AbsenceTypeForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    typeName('Ferie')
    fireEvent.click(editButton('absenceTypes.form.deductsBalance'))
    fireEvent.click(screen.getByRole('switch'))
    fireEvent.click(editButton('absenceTypes.form.defaultAnnualDays'))
    fireEvent.change(screen.getByRole('spinbutton'), { target: { value: '1.234' } })
    save()

    expect(await screen.findByText(i18n.t('absenceTypes.form.defaultDaysInvalid'))).toHaveAttribute('role', 'alert')
    expect(createMock).not.toHaveBeenCalled()
  })

  it('picks a color from the palette by name', async () => {
    createMock.mockResolvedValue(CREATED)
    render(<AbsenceTypeForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    typeName('Ferie')
    fireEvent.click(editButton('absenceTypes.form.color'))
    fireEvent.click(screen.getByRole('radio', { name: 'Emerald' }))
    save()

    await waitFor(() => expect(createMock).toHaveBeenCalledWith(expect.objectContaining({ color: 'emerald' })))
  })

  it('maps a server 422 on allows_full_day with the accessible error triad', async () => {
    createMock.mockRejectedValue(rejection(422, { allows_full_day: ['Pick at least one mode.'] }))
    render(<AbsenceTypeForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    typeName('Ferie')
    save()

    expect(await screen.findByText('Pick at least one mode.')).toHaveAttribute('role', 'alert')
  })

  it('maps a server 422 on a duplicated name onto the field with the accessible error triad', async () => {
    createMock.mockRejectedValue(rejection(422, { name: ['The name has already been taken.'] }))
    render(<AbsenceTypeForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    typeName('Ferie')
    save()

    const message = await screen.findByText('The name has already been taken.')
    expect(message).toHaveAttribute('role', 'alert')
    const input = screen.getByRole('textbox')
    expect(input).toHaveAttribute('aria-invalid', 'true')
    expect(input.getAttribute('aria-describedby')).toContain(message.id)
  })
})
