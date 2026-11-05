import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { useState } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { fetchRevenueTypesForSelect } from '@/features/revenue-types/api'
import { RevenueTypeSelect } from '@/features/revenue-types/revenue-type-select'
import type { RevenueTypeForSelectData, RevenueTypeOption } from '@/features/revenue-types/types'

vi.mock('@/features/revenue-types/api', () => ({ fetchRevenueTypesForSelect: vi.fn() }))

const fetchMock = vi.mocked(fetchRevenueTypesForSelect)

function option(id: number, code: string, name: string, account: string, accountName: string): RevenueTypeOption {
  return {
    id,
    code,
    name,
    path: '',
    is_active: true,
    group: { code: '70', name: 'Sales' },
    account: { code: account, name: accountName },
  }
}

const SERVICES = option(1, '70.01.001', 'Consulting', '70.01', 'Services')
const GOODS = option(2, '70.02.001', 'Hardware', '70.02', 'Goods')

function page(items: RevenueTypeOption[]): RevenueTypeForSelectData {
  return { items, total: items.length, offset: 0 }
}

function Harness({ initial = null, onChange }: { initial?: number | null; onChange?: (value: number | null) => void }) {
  const [value, setValue] = useState<number | null>(initial)
  return (
    <RevenueTypeSelect
      value={value}
      onChange={(next) => {
        setValue(next)
        onChange?.(next)
      }}
    />
  )
}

function renderSelect(props: Parameters<typeof Harness>[0] = {}) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <Harness {...props} />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchMock.mockReset().mockImplementation(async (params) => {
    if (params?.ids?.length) {
      return page([SERVICES, GOODS].filter((item) => params.ids?.includes(item.id)))
    }
    const search = params?.search?.toLowerCase() ?? ''
    return page([SERVICES, GOODS].filter((item) => `${item.code} ${item.name}`.toLowerCase().includes(search)))
  })
})

describe('RevenueTypeSelect', () => {
  it('lists sub-accounts grouped by group and account, headings are not options', async () => {
    renderSelect()

    fireEvent.click(screen.getByRole('combobox', { name: 'Revenue sub-account' }))

    const group = await screen.findByRole('group', { name: '70 Sales' })
    expect(within(group).getByRole('group', { name: '70.01 Services' })).toBeInTheDocument()
    expect(within(group).getByRole('group', { name: '70.02 Goods' })).toBeInTheDocument()
    expect(screen.getAllByRole('option').map((item) => item.textContent)).toEqual(['70.01.001 Consulting', '70.02.001 Hardware'])
  })

  it('picks an option, then clears it', async () => {
    const onChange = vi.fn()
    renderSelect({ onChange })

    fireEvent.click(screen.getByRole('combobox', { name: 'Revenue sub-account' }))
    fireEvent.click(await screen.findByRole('option', { name: '70.02.001 Hardware' }))

    expect(onChange).toHaveBeenLastCalledWith(2)
    await waitFor(() => expect(screen.getByRole('combobox', { name: 'Revenue sub-account' })).toHaveTextContent('70.02.001 Hardware'))

    fireEvent.click(screen.getByRole('button', { name: 'Clear the sub-account' }))
    expect(onChange).toHaveBeenLastCalledWith(null)
  })

  it('searches by code or name on the server', async () => {
    renderSelect()

    fireEvent.click(screen.getByRole('combobox', { name: 'Revenue sub-account' }))
    await screen.findAllByRole('option')
    fireEvent.change(screen.getByRole('textbox', { name: 'Search by code or name…' }), { target: { value: 'hard' } })

    await waitFor(() => expect(fetchMock).toHaveBeenCalledWith({ search: 'hard' }))
    await waitFor(() => expect(screen.getAllByRole('option')).toHaveLength(1))
  })

  it('hydrates the label of the current value by id', async () => {
    renderSelect({ initial: 1 })

    await waitFor(() => expect(screen.getByRole('combobox', { name: 'Revenue sub-account' })).toHaveTextContent('70.01.001 Consulting'))
    expect(fetchMock).toHaveBeenCalledWith({ ids: [1], limit: 1 })
  })

  it('shows the empty state', async () => {
    fetchMock.mockResolvedValue(page([]))
    renderSelect()

    fireEvent.click(screen.getByRole('combobox', { name: 'Revenue sub-account' }))

    expect(await screen.findByText('No sub-accounts found.')).toBeInTheDocument()
  })
})
