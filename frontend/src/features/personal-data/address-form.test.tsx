import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { AddressForm } from '@/features/personal-data/address-form'
import type { AddressDraft } from '@/features/personal-data/types'

// GeoSelect is covered by its own test; here we only need a controllable stub
// that lets us observe the wired value and (optionally) drive a change.
vi.mock('@/features/geo/geo-select', () => ({
  GeoSelect: ({
    value,
    onChange,
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
  }) => (
    <button
      type="button"
      data-testid="geo-select"
      data-country={value.country_id ?? ''}
      data-state={value.state_id ?? ''}
      data-province={value.province_id ?? ''}
      data-city={value.city_id ?? ''}
      onClick={() =>
        onChange({ country_id: 5, state_id: 6, province_id: 8, city_id: 7 })
      }
    >
      geo
    </button>
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

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('AddressForm (controlled)', () => {
  it('does not render latitude/longitude inputs', () => {
    render(<AddressForm onSubmit={() => {}} onCancel={() => {}} />)

    expect(screen.queryByText('Latitude')).not.toBeInTheDocument()
    expect(screen.queryByText('Longitude')).not.toBeInTheDocument()
  })

  it('returns the draft fields without coordinates and forwards is_primary', async () => {
    const onSubmit = vi.fn()
    render(<AddressForm onSubmit={onSubmit} onCancel={() => {}} />)

    fireEvent.change(screen.getByLabelText(/^Address\*?$/), {
      target: { value: '221B Baker Street' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(1))

    const fields = onSubmit.mock.calls[0][0]
    expect(fields).not.toHaveProperty('latitude')
    expect(fields).not.toHaveProperty('longitude')
    expect(fields).not.toHaveProperty('_key')
    expect(fields.is_primary).toBe(true)
    expect(fields.line1).toBe('221B Baker Street')
  })

  it('returns the geo ids selected through GeoSelect', async () => {
    const onSubmit = vi.fn()
    render(<AddressForm onSubmit={onSubmit} onCancel={() => {}} />)

    fireEvent.change(screen.getByLabelText(/^Address\*?$/), {
      target: { value: 'Somewhere' },
    })
    fireEvent.click(screen.getByTestId('geo-select'))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(1))

    const fields = onSubmit.mock.calls[0][0]
    expect(fields.country_id).toBe(5)
    expect(fields.state_id).toBe(6)
    expect(fields.province_id).toBe(8)
    expect(fields.city_id).toBe(7)
  })

  it('preserves the existing id when editing', async () => {
    const onSubmit = vi.fn()
    render(
      <AddressForm
        address={address({ id: 42 })}
        onSubmit={onSubmit}
        onCancel={() => {}}
      />,
    )

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(1))
    expect(onSubmit.mock.calls[0][0].id).toBe(42)
  })

  it('precompiles the geo selection when editing', () => {
    render(
      <AddressForm
        address={address({
          country_id: 1,
          state_id: 2,
          province_id: 4,
          city_id: 3,
        })}
        onSubmit={() => {}}
        onCancel={() => {}}
      />,
    )

    const geo = screen.getByTestId('geo-select')
    expect(geo).toHaveAttribute('data-country', '1')
    expect(geo).toHaveAttribute('data-state', '2')
    expect(geo).toHaveAttribute('data-province', '4')
    expect(geo).toHaveAttribute('data-city', '3')
  })

  it('does not render the site type select by default (Users/Referents/Companies)', () => {
    render(<AddressForm onSubmit={() => {}} onCancel={() => {}} />)

    expect(screen.queryByText('Site type')).not.toBeInTheDocument()
  })

  it('does not render the site type select when address has one but showSiteType is off', () => {
    render(
      <AddressForm
        address={address({ site_type: 'legal_seat' })}
        onSubmit={() => {}}
        onCancel={() => {}}
      />,
    )

    expect(screen.queryByText('Site type')).not.toBeInTheDocument()
  })

  it('renders the site type select and defaults it to billing when showSiteType is on', async () => {
    const onSubmit = vi.fn()
    render(<AddressForm onSubmit={onSubmit} onCancel={() => {}} showSiteType />)

    expect(screen.getByText('Site type')).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Site type' })).toHaveTextContent('Billing')

    fireEvent.change(screen.getByLabelText(/^Address\*?$/), {
      target: { value: '221B Baker Street' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(1))
    expect(onSubmit.mock.calls[0][0].site_type).toBe('billing')
  })

  it('preselects the persisted site type when editing with showSiteType on', () => {
    render(
      <AddressForm
        address={address({ site_type: 'operational_site' })}
        onSubmit={() => {}}
        onCancel={() => {}}
        showSiteType
      />,
    )

    expect(screen.getByRole('combobox', { name: 'Site type' })).toHaveTextContent(
      'Operational site',
    )
  })
  describe('primary address checkbox (user directive 2026-09-10)', () => {
    it('is checked by default on a brand new address', () => {
      render(<AddressForm onSubmit={() => {}} onCancel={() => {}} />)

      expect(screen.getByRole('checkbox', { name: 'Primary address' })).toBeChecked()
    })

    it('keeps the persisted value when editing, a stored false included', () => {
      const { unmount } = render(
        <AddressForm address={address()} onSubmit={() => {}} onCancel={() => {}} />,
      )
      expect(screen.getByRole('checkbox', { name: 'Primary address' })).not.toBeChecked()
      unmount()

      render(
        <AddressForm
          address={address({ is_primary: true })}
          onSubmit={() => {}}
          onCancel={() => {}}
        />,
      )
      expect(screen.getByRole('checkbox', { name: 'Primary address' })).toBeChecked()
    })

    it('forwards is_primary false once unchecked', async () => {
      const onSubmit = vi.fn()
      render(<AddressForm onSubmit={onSubmit} onCancel={() => {}} />)

      fireEvent.change(screen.getByLabelText(/^Address\*?$/), {
        target: { value: '221B Baker Street' },
      })
      fireEvent.click(screen.getByRole('checkbox', { name: 'Primary address' }))
      fireEvent.click(screen.getByRole('button', { name: 'Save' }))

      await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(1))
      expect(onSubmit.mock.calls[0][0].is_primary).toBe(false)
    })
  })
})
