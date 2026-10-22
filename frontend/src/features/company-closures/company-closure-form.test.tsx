import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { CompanyClosureFormBody } from '@/features/company-closures/company-closure-form-body'
import { CompanyClosureDetailView } from '@/features/company-closures/company-closure-detail'
import type { CompanyClosure } from '@/features/company-closures/types'

const createMock = vi.fn()
const updateMock = vi.fn()

vi.mock('@/features/company-closures/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/company-closures/api')>()),
  createCompanyClosure: (...args: unknown[]) => createMock(...args),
  updateCompanyClosure: (...args: unknown[]) => updateMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

vi.mock('@/features/absences/requests/use-absence-type-options', () => ({
  useAbsenceTypeOptions: () => ({
    data: [{ id: 3, name: 'Ferie collettive' }],
    isPending: false,
    isError: false,
    refetch: vi.fn(),
  }),
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

/** The server-searched multi-select is stubbed: it is covered by its own suite. */
vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({ value, onChange }: { value: number[]; onChange: (ids: number[]) => void }) => (
    <button type="button" onClick={() => onChange([5, 6])}>
      pick sites ({value.length})
    </button>
  ),
}))

/** A QueryClient per test, never per render. */
function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>
}

const SAVED: CompanyClosure = {
  id: 7,
  name: 'August break',
  starts_on: '2026-08-10',
  ends_on: '2026-08-21',
  kind: 'collective_leave',
  absence_type: { id: 3, name: 'Ferie collettive' },
  applies_to_all_sites: true,
  operational_sites: [],
  repeats_yearly: false,
  is_started: false,
  generated_requests_count: 0,
  created_at: '2026-07-01T08:00:00Z',
  updated_at: '2026-07-01T08:00:00Z',
}

const t = (key: string) => i18n.t(key)
const field = (key: string) => screen.getByLabelText(new RegExp(`^${t(key)}`))
const save = () => fireEvent.click(screen.getAllByRole('button', { name: t('companyClosures.form.save') })[0])

function fill(name: string, startsOn: string, endsOn: string) {
  fireEvent.change(field('companyClosures.form.name'), { target: { value: name } })
  fireEvent.change(field('companyClosures.form.startsOn'), { target: { value: startsOn } })
  fireEvent.change(field('companyClosures.form.endsOn'), { target: { value: endsOn } })
}

function rejection(status: number, errors: Record<string, string[]> = {}) {
  return new AxiosError('error', String(status), undefined, undefined, {
    status,
    data: { success: false, message: 'Rejected', errors },
  } as never)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createMock.mockReset()
  updateMock.mockReset()
})

describe('CompanyClosureFormBody - conditional rules (spec 0304 D-13)', () => {
  it('shows the recurrence only for a single-day holiday', () => {
    render(<CompanyClosureFormBody onDone={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    expect(screen.queryByRole('switch', { name: t('companyClosures.form.repeatsYearly') })).not.toBeInTheDocument()
    fill('Patron saint', '2026-06-24', '2026-06-24')
    expect(screen.getByRole('switch', { name: t('companyClosures.form.repeatsYearly') })).toBeInTheDocument()
    fireEvent.change(field('companyClosures.form.endsOn'), { target: { value: '2026-06-25' } })
    expect(screen.queryByRole('switch', { name: t('companyClosures.form.repeatsYearly') })).not.toBeInTheDocument()
  })

  it('asks for the absence type only on a collective leave and blocks the save without it', async () => {
    render(<CompanyClosureFormBody onDone={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    expect(screen.queryByText(t('companyClosures.form.absenceType'))).not.toBeInTheDocument()
    fireEvent.click(screen.getByRole('radio', { name: t('companyClosures.kinds.collective_leave') }))
    fill('August break', '2026-08-10', '2026-08-21')
    save()

    expect(await screen.findByText(t('companyClosures.form.absenceTypeRequired'))).toHaveAttribute('role', 'alert')
    expect(createMock).not.toHaveBeenCalled()
  })

  it('asks for the sites when it is not for all sites and sends them', async () => {
    createMock.mockResolvedValue({ ...SAVED, kind: 'holiday', absence_type: null })
    const onDone = vi.fn()
    render(<CompanyClosureFormBody onDone={onDone} onCancel={vi.fn()} />, { wrapper: wrapper() })

    fill('Patron saint', '2026-06-24', '2026-06-24')
    fireEvent.click(screen.getByRole('switch', { name: t('companyClosures.form.appliesToAllSites') }))
    save()
    expect(await screen.findByText(t('companyClosures.form.sitesRequired'))).toHaveAttribute('role', 'alert')

    fireEvent.click(screen.getByRole('button', { name: /pick sites/ }))
    save()

    await waitFor(() =>
      expect(createMock).toHaveBeenCalledWith({
        name: 'Patron saint',
        starts_on: '2026-06-24',
        ends_on: '2026-06-24',
        kind: 'holiday',
        applies_to_all_sites: false,
        operational_site_ids: [5, 6],
        repeats_yearly: false,
      }),
    )
    await waitFor(() => expect(onDone).toHaveBeenCalled())
  })

  it('maps a server 422 onto the field with the accessible error triad', async () => {
    createMock.mockRejectedValue(rejection(422, { name: ['The name has already been taken.'] }))
    render(<CompanyClosureFormBody onDone={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    fill('Patron saint', '2026-06-24', '2026-06-24')
    save()

    const message = await screen.findByText('The name has already been taken.')
    expect(message).toHaveAttribute('role', 'alert')
    expect(field('companyClosures.form.name')).toHaveAttribute('aria-invalid', 'true')
    expect(field('companyClosures.form.name').getAttribute('aria-describedby')).toContain(message.id)
  })

  it('says the closure already started when the server answers 409', async () => {
    updateMock.mockRejectedValue(rejection(409))
    render(<CompanyClosureFormBody closure={SAVED} onDone={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    save()

    expect(await screen.findByText(t('companyClosures.form.startedLocked'))).toBeInTheDocument()
  })
})

describe('CompanyClosureFormBody - generation summary', () => {
  it('shows what the collective leave generated before leaving the form', async () => {
    createMock.mockResolvedValue({
      ...SAVED,
      generation: { created: 12, skipped: [{ user: { id: 4, name: 'Mario Rossi' }, reason: 'overlap' }] },
    })
    const onDone = vi.fn()
    render(<CompanyClosureFormBody onDone={onDone} onCancel={vi.fn()} />, { wrapper: wrapper() })

    fireEvent.click(screen.getByRole('radio', { name: t('companyClosures.kinds.collective_leave') }))
    fill('August break', '2026-08-10', '2026-08-21')
    fireEvent.click(screen.getByRole('combobox'))
    fireEvent.click(await screen.findByText('Ferie collettive'))
    save()

    expect(await screen.findByText('12 approved requests were created.')).toBeInTheDocument()
    expect(screen.getByText('Mario Rossi')).toBeInTheDocument()
    expect(screen.getByText(t('companyClosures.generation.reasons.overlap'))).toBeInTheDocument()
    expect(onDone).not.toHaveBeenCalled()

    fireEvent.click(screen.getByRole('button', { name: t('companyClosures.generation.close') }))
    expect(onDone).toHaveBeenCalledWith(expect.objectContaining({ id: 7 }))
  })
})

describe('CompanyClosureFormBody - started closure', () => {
  const STARTED = { ...SAVED, is_started: true }

  it('is read-only with the reason', () => {
    render(<CompanyClosureFormBody closure={STARTED} onDone={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    expect(field('companyClosures.form.name')).toBeDisabled()
    expect(screen.getByText(t('companyClosures.form.startedLocked'))).toBeInTheDocument()
  })

  it('stays editable when it repeats yearly', () => {
    render(
      <CompanyClosureFormBody
        closure={{ ...STARTED, kind: 'holiday', absence_type: null, ends_on: SAVED.starts_on, repeats_yearly: true }}
        onDone={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(field('companyClosures.form.name')).toBeEnabled()
  })
})

describe('CompanyClosureDetailView', () => {
  it('offers Edit while the closure can still change and hides it once started', () => {
    const onEdit = vi.fn()
    const { rerender } = render(<CompanyClosureDetailView closure={SAVED} onEdit={onEdit} />)

    fireEvent.click(screen.getByRole('button', { name: t('companyClosures.detail.edit') }))
    expect(onEdit).toHaveBeenCalled()

    rerender(<CompanyClosureDetailView closure={{ ...SAVED, is_started: true }} onEdit={onEdit} />)
    expect(screen.queryByRole('button', { name: t('companyClosures.detail.edit') })).not.toBeInTheDocument()
  })
})
