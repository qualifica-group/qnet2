import { beforeAll, describe, expect, it, vi } from 'vitest'
import { useState, type ReactElement } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { AddressesManager } from '@/features/personal-data/addresses-manager'
import type { AddressDraft } from '@/features/personal-data/types'

/**
 * AddressesManager consumes `useConfirm` (dialog); wrap in a QueryClient too so
 * any config-backed lookup resolves, mirroring the app's root providers.
 */
function renderWithConfirm(ui: ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{ui}</ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

// Stub the inline editor so the manager's buffer logic is tested in isolation,
// without pulling in the GeoSelect/config the real form needs. The stub submits a
// non-primary address.
vi.mock('@/features/personal-data/address-form', () => ({
  AddressForm: ({
    onSubmit,
  }: {
    onSubmit: (fields: Omit<AddressDraft, '_key'>) => void
  }) => (
    <button
      type="button"
      data-testid="stub-submit"
      onClick={() =>
        onSubmit({
          line1: 'New Street',
          line2: null,
          postal_code: null,
          city_id: null,
          province_id: null,
          state_id: null,
          country_id: null,
          is_primary: false,
          site_type: 'billing',
        })
      }
    >
      stub-save
    </button>
  ),
}))

// Immediate-persistence path hits the per-entity address endpoints.
const createAddressMock = vi.fn()
const updateAddressMock = vi.fn()
const deleteAddressMock = vi.fn()
vi.mock('@/features/personal-data/api', () => ({
  createAddress: (...a: unknown[]) => createAddressMock(...a),
  updateAddress: (...a: unknown[]) => updateAddressMock(...a),
  deleteAddress: (...a: unknown[]) => deleteAddressMock(...a),
}))

// `createMode` renders `AddressCreateField`, which talks to `GeoSelect`
// directly (not through the stubbed dialog `AddressForm` above). A
// controllable stub, mirroring `address-form.test.tsx`.
vi.mock('@/features/geo/geo-select', () => ({
  GeoSelect: ({
    value,
    onChange,
    requiredLevels,
  }: {
    value: {
      country_id: number | null
      state_id: number | null
      province_id: number | null
      city_id: number | null
    }
    onChange: (next: {
      country_id: number | null
      state_id: number | null
      province_id: number | null
      city_id: number | null
    }) => void
    // Surfaced so a test can read WHICH levels the cascade was told to mark.
    requiredLevels?: ReadonlyArray<string>
  }) => (
    <>
      <button
        type="button"
        data-testid="geo-select"
        data-required-levels={(requiredLevels ?? []).join(',')}
        data-city={value.city_id ?? ''}
        onClick={() => onChange({ country_id: 5, state_id: 6, province_id: 8, city_id: 7 })}
      >
        geo
      </button>
      {/* The national-default seed: the real cascade emits it from an effect on
          a pristine value, here it is driven explicitly. */}
      <button
        type="button"
        data-testid="geo-default-country"
        data-country={value.country_id ?? ''}
        onClick={() => onChange({ country_id: 5, state_id: null, province_id: null, city_id: null })}
      >
        default country
      </button>
    </>
  ),
}))

function address(overrides: Partial<AddressDraft> = {}): AddressDraft {
  return {
    _key: 'address-1',
    id: 1,
    line1: '10 Downing Street',
    line2: null,
    postal_code: 'SW1A 2AA',
    city_id: null,
    province_id: null,
    state_id: null,
    country_id: null,
    is_primary: false,
    site_type: 'billing',
    ...overrides,
  }
}

describe('AddressesManager (controlled)', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('en')
  })

  it('shows the empty state when there are no addresses', () => {
    renderWithConfirm(<AddressesManager value={[]} onChange={() => {}} />)
    expect(screen.getByText('No addresses yet.')).toBeInTheDocument()
  })

  it('renders each address line and its secondary/postal summary', () => {
    renderWithConfirm(
      <AddressesManager value={[address({ line2: 'Flat 2' })]} onChange={() => {}} />,
    )
    expect(screen.getByText('10 Downing Street')).toBeInTheDocument()
    expect(screen.getByText('Flat 2')).toBeInTheDocument()
    expect(screen.getByText('SW1A 2AA')).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Delete address' }),
    ).toBeInTheDocument()
  })

  it('renders the full hydrated location (postal, city, province, state, country)', () => {
    renderWithConfirm(
      <AddressesManager
        value={[
          address({
            postal_code: '80121',
            city: { id: 1, name: 'Napoli' },
            province: { id: 2, name: 'Napoli' },
            state: { id: 3, name: 'Campania' },
            country: { id: 4, name: 'Italia' },
          }),
        ]}
        onChange={() => {}}
      />,
    )
    expect(
      screen.getByText('80121 · Napoli · Napoli · Campania · Italia'),
    ).toBeInTheDocument()
  })

  it('hides the site-type badge unless the container opts into site types', () => {
    const { unmount } = renderWithConfirm(
      <AddressesManager value={[address({ site_type: 'legal_seat' })]} onChange={() => {}} />,
    )
    expect(screen.queryByText('Registered office')).not.toBeInTheDocument()
    unmount()

    renderWithConfirm(
      <AddressesManager
        value={[address({ site_type: 'legal_seat' })]}
        onChange={() => {}}
        showSiteType
      />,
    )
    expect(screen.getByText('Registered office')).toBeInTheDocument()
  })

  it('removes an address from the buffer without any network call', async () => {
    const onChange = vi.fn()

    renderWithConfirm(<AddressesManager value={[address()]} onChange={onChange} />)
    fireEvent.click(screen.getByRole('button', { name: 'Delete address' }))

    const dialog = await screen.findByRole('alertdialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Delete address' }))

    await waitFor(() => expect(onChange).toHaveBeenCalledWith([]))
    expect(onChange).toHaveBeenCalledTimes(1)
  })

  it('enforces a single primary across the buffer (first becomes default)', async () => {
    const onChange = vi.fn()

    const primary = address({ _key: 'address-1', id: 1, is_primary: true })
    const other = address({ _key: 'address-2', id: 2, is_primary: false })

    renderWithConfirm(<AddressesManager value={[primary, other]} onChange={onChange} />)
    // Delete the current primary; the remaining address must be promoted.
    fireEvent.click(
      screen.getAllByRole('button', { name: 'Delete address' })[0],
    )
    const dialog = await screen.findByRole('alertdialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Delete address' }))

    await waitFor(() => expect(onChange).toHaveBeenCalledTimes(1))
    const next = onChange.mock.calls[0][0] as AddressDraft[]
    expect(next).toHaveLength(1)
    expect(next[0]._key).toBe('address-2')
    expect(next[0].is_primary).toBe(true)
  })

  it('makes the first added address primary by default', () => {
    const onChange = vi.fn()

    renderWithConfirm(<AddressesManager value={[]} onChange={onChange} />)
    fireEvent.click(screen.getByRole('button', { name: 'Add address' }))
    fireEvent.click(screen.getByTestId('stub-submit'))

    expect(onChange).toHaveBeenCalledTimes(1)
    const next = onChange.mock.calls[0][0] as AddressDraft[]
    expect(next).toHaveLength(1)
    expect(next[0].line1).toBe('New Street')
    // No address was primary, so the first one becomes the default.
    expect(next[0].is_primary).toBe(true)
    expect(next[0].id).toBeUndefined()
  })

  it('persists a new address immediately and adopts the server primary flag', async () => {
    const onChange = vi.fn()
    // The backend auto-primaries the first address; the buffer must adopt that.
    createAddressMock.mockResolvedValue({
      id: 77,
      line1: 'New Street',
      line2: null,
      postal_code: null,
      city_id: null,
      province_id: null,
      state_id: null,
      country_id: null,
      is_primary: true,
      site_type: 'billing',
      addressable_type: 'personal_data',
      addressable_id: 99,
      created_at: null,
    })

    renderWithConfirm(
      <AddressesManager
        value={[]}
        onChange={onChange}
        persistence={{ type: 'personal_data', id: 99 }}
      />,
    )
    fireEvent.click(screen.getByRole('button', { name: 'Add address' }))
    fireEvent.click(screen.getByTestId('stub-submit'))

    await waitFor(() =>
      expect(createAddressMock).toHaveBeenCalledWith(
        expect.objectContaining({
          addressable_type: 'personal_data',
          addressable_id: 99,
          line1: 'New Street',
        }),
      ),
    )
    await waitFor(() => expect(onChange).toHaveBeenCalled())
    const next = onChange.mock.calls[0][0] as AddressDraft[]
    expect(next[0].id).toBe(77)
    expect(next[0].is_primary).toBe(true)
  })

  it('deletes a persisted address immediately through the endpoint', async () => {
    const onChange = vi.fn()
    deleteAddressMock.mockResolvedValue(undefined)

    renderWithConfirm(
      <AddressesManager
        value={[address()]}
        onChange={onChange}
        persistence={{ type: 'personal_data', id: 99 }}
      />,
    )
    fireEvent.click(screen.getByRole('button', { name: 'Delete address' }))
    const dialog = await screen.findByRole('alertdialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Delete address' }))

    await waitFor(() => expect(deleteAddressMock).toHaveBeenCalledWith(1))
    await waitFor(() => expect(onChange).toHaveBeenCalledWith([]))
  })
})

/** Owns the buffer across renders, mirroring how a parent form would. */
function ControlledAddresses() {
  const [value, setValue] = useState<AddressDraft[]>([])
  return <AddressesManager value={value} onChange={setValue} createMode />
}

describe('AddressesManager (createMode)', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('en')
  })

  it('renders a single inline form: no list, no dialog, no Add button', () => {
    renderWithConfirm(<AddressesManager value={[]} onChange={() => {}} createMode />)

    expect(screen.queryByRole('button', { name: 'Add address' })).not.toBeInTheDocument()
    expect(screen.getByLabelText(/^Address\*?$/)).toBeInTheDocument()
  })

  it('marks line1 and the city required only once the address is started', () => {
    renderWithConfirm(<ControlledAddresses />)
    // Typed as an input so the assertion can read its `labels` back.
    const line1 = screen.getByLabelText(/^Address\*?$/) as HTMLInputElement

    // Pristine: the whole address is optional, so nothing claims to be required.
    expect(line1).toHaveAttribute('aria-required', 'false')
    expect(line1.labels?.[0].textContent).not.toContain('*')

    expect(screen.getByTestId('geo-select')).toHaveAttribute('data-required-levels', '')

    fireEvent.change(line1, { target: { value: 'Via Roma 1' } })

    // Started: line1 and the city are mandatory from here on, and say so.
    expect(line1).toHaveAttribute('aria-required', 'true')
    expect(line1.labels?.[0].textContent).toContain('*')
    expect(screen.getByTestId('geo-select')).toHaveAttribute('data-required-levels', 'city')
  })

  it('creates the sole draft once any field is typed', () => {
    const onChange = vi.fn()
    renderWithConfirm(<AddressesManager value={[]} onChange={onChange} createMode />)

    fireEvent.change(screen.getByLabelText(/^Address\*?$/), { target: { value: 'Via Roma 1' } })

    expect(onChange).toHaveBeenCalledTimes(1)
    const next = onChange.mock.calls[0][0] as AddressDraft[]
    expect(next).toHaveLength(1)
    expect(next[0]).toMatchObject({ line1: 'Via Roma 1', is_primary: true })
  })

  it('clears the buffer once every field is emptied again (optional address)', () => {
    renderWithConfirm(<ControlledAddresses />)
    const line1 = screen.getByLabelText(/^Address\*?$/)

    fireEvent.change(line1, { target: { value: 'Via Roma 1' } })
    fireEvent.change(line1, { target: { value: '' } })

    expect(line1).toHaveValue('')
    expect(screen.queryByText('The address is required.')).not.toBeInTheDocument()
  })

  it('requires line1 and the city once the address is started', () => {
    renderWithConfirm(<ControlledAddresses />)

    // Choosing a geo value alone "starts" the address without a line1 yet.
    fireEvent.click(screen.getByTestId('geo-select'))

    expect(screen.getByText('The address is required.')).toBeInTheDocument()
  })

  it('does not start the address when only the default country is preselected', () => {
    const onChange = vi.fn()
    renderWithConfirm(<AddressesManager value={[]} onChange={onChange} createMode />)

    fireEvent.click(screen.getByTestId('geo-default-country'))

    expect(onChange).toHaveBeenCalledWith([])
    expect(screen.queryByText('The address is required.')).not.toBeInTheDocument()
    expect(screen.queryByText('The city is required.')).not.toBeInTheDocument()
  })

  it('keeps the preselected country visible while the buffer stays empty', () => {
    renderWithConfirm(<ControlledAddresses />)

    fireEvent.click(screen.getByTestId('geo-default-country'))

    expect(screen.getByTestId('geo-default-country')).toHaveAttribute('data-country', '5')
  })

  it('is valid once line1 and the city are both set', () => {
    renderWithConfirm(<ControlledAddresses />)

    fireEvent.change(screen.getByLabelText(/^Address\*?$/), { target: { value: 'Via Roma 1' } })
    fireEvent.click(screen.getByTestId('geo-select'))

    expect(screen.queryByText('The address is required.')).not.toBeInTheDocument()
    expect(screen.queryByText('The city is required.')).not.toBeInTheDocument()
  })
})
