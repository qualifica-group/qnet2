import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import type { ICellRendererParams, IRowNode } from 'ag-grid-community'
import i18n from '@/i18n'
import '@/features/imports/wizard/i18n'
import { ReviewGeoCell, type ReviewGeoCellParams, type ReviewGeoGridContext } from '@/features/imports/wizard/review-geo-editor'
import type { ImportRunRowItem } from '@/features/imports/wizard/types'

/**
 * Spec 0038: the 4 geo review columns (country/region/province/city) share
 * this one cell — click opens a popup with the cascade precompiled from the
 * ids already resolved onto the row, Applica sends a single PATCH via
 * `context.onApplyGeo`, Annulla/close send nothing, and `readOnly` disables
 * the popup entirely.
 */

const useCountriesMock = vi.fn()
const useStatesMock = vi.fn()
const useProvincesMock = vi.fn()
const useCitiesMock = vi.fn()

// The cascade's national default is resolved by a network-backed hook (public
// config + country list, covered by `use-default-country.test.ts`). Stub it to
// international mode: this suite asserts the ids that come from the row.
vi.mock('@/features/geo/use-default-country', () => ({
  useDefaultCountryId: () => null,
}))

vi.mock('@/features/geo/use-geo', () => ({
  useCountries: () => useCountriesMock(),
  useStates: (countryId: number | null) => useStatesMock(countryId),
  useProvinces: (stateId: number | null) => useProvincesMock(stateId),
  useCities: (stateId: number | null, provinceId?: number | null, search?: string) =>
    useCitiesMock(stateId, provinceId, search),
}))

function query<T>(data: T) {
  return { data, isPending: false, isError: false, refetch: vi.fn() }
}

function cityQuery(items: unknown[]) {
  return {
    data: { pages: [items] },
    isPending: false,
    isError: false,
    hasNextPage: false,
    isFetchingNextPage: false,
    fetchNextPage: vi.fn(),
    refetch: vi.fn(),
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  useCountriesMock.mockReset()
  useStatesMock.mockReset()
  useProvincesMock.mockReset()
  useCitiesMock.mockReset()

  useCountriesMock.mockReturnValue(query([{ id: 1, name: 'Italy', iso2: 'IT' }]))
  useStatesMock.mockReturnValue(query([{ id: 10, name: 'Campania', country_id: 1 }]))
  useProvincesMock.mockReturnValue(query([{ id: 51, name: 'Caserta', state_id: 10 }]))
  useCitiesMock.mockReturnValue(cityQuery([{ id: 200, name: 'Maddaloni', state_id: 10, province_id: 51 }]))
})

function rowItem(overrides: Partial<ImportRunRowItem> = {}): ImportRunRowItem {
  return {
    id: 10,
    row_number: 1,
    status: 'warning',
    is_edited: false,
    duplicate_of_id: null,
    operator_id: null,
    operator: null,
    operational_site_id: null,
    operational_site: null,
    product_ids: null,
    products: [],
    values: {
      country: 'Italy',
      region: 'Campania',
      province: 'Caserta',
      city: 'Maddaloni',
      country_id: 1,
      state_id: 10,
      province_id: 51,
      city_id: 200,
    },
    messages: [],
    ...overrides,
  }
}

function renderCell(overrides: Partial<ReviewGeoCellParams> = {}) {
  const node = { setData: vi.fn() } as unknown as IRowNode<ImportRunRowItem>
  const onApplyGeo = vi.fn().mockResolvedValue(undefined)
  const context: ReviewGeoGridContext = { onApplyGeo }
  render(
    <ReviewGeoCell
      {...({
        data: rowItem(),
        value: 'Maddaloni',
        node,
        context,
        ...overrides,
      } as ReviewGeoCellParams & ICellRendererParams)}
    />,
  )
  return { node, onApplyGeo }
}

describe('ReviewGeoCell', () => {
  it('renders the column resolved text as a button opening a popup precompiled from the row ids (AC-010)', () => {
    renderCell()
    expect(screen.getByRole('button', { name: 'Edit country/region/province/city' })).toHaveTextContent('Maddaloni')

    fireEvent.click(screen.getByRole('button', { name: 'Edit country/region/province/city' }))

    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(useStatesMock).toHaveBeenCalledWith(1)
    expect(useProvincesMock).toHaveBeenCalledWith(10)
    expect(useCitiesMock).toHaveBeenCalledWith(10, 51, '')
  })

  it('sends a single PATCH via context.onApplyGeo with the row and current 4 ids, then closes (AC-011)', async () => {
    const { node, onApplyGeo } = renderCell()

    fireEvent.click(screen.getByRole('button', { name: 'Edit country/region/province/city' }))
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    await waitFor(() =>
      expect(onApplyGeo).toHaveBeenCalledWith(
        rowItem(),
        { country_id: 1, state_id: 10, province_id: 51, city_id: 200 },
        node,
      ),
    )
    expect(onApplyGeo).toHaveBeenCalledTimes(1)
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })

  it('Annulla closes the popup without calling onApplyGeo (AC-012)', () => {
    const { onApplyGeo } = renderCell()

    fireEvent.click(screen.getByRole('button', { name: 'Edit country/region/province/city' }))
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(onApplyGeo).not.toHaveBeenCalled()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('renders plain text with no popup affordance in readOnly mode (AC-013)', () => {
    renderCell({ readOnly: true })

    expect(screen.getByText('Maddaloni')).toBeInTheDocument()
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })

  it('shows an accessible error and keeps the popup open when the PATCH fails (AC-014)', async () => {
    const onApplyGeo = vi.fn().mockRejectedValue({ isAxiosError: true, response: { status: 422 } })
    const node = { setData: vi.fn() } as unknown as IRowNode<ImportRunRowItem>
    render(
      <ReviewGeoCell
        {...({
          data: rowItem(),
          value: 'Maddaloni',
          node,
          context: { onApplyGeo },
        } as ReviewGeoCellParams & ICellRendererParams)}
      />,
    )

    fireEvent.click(screen.getByRole('button', { name: 'Edit country/region/province/city' }))
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Some values are not valid. Please check and try again.')
    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(node.setData).not.toHaveBeenCalled()
  })
})
