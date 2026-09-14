import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import {
  AsyncPaginatedSelect,
  type AsyncPaginatedSelectLabels,
} from '@/components/ui/async-paginated-select'
import type { ForSelectItem } from '@/features/for-select/types'

const useForSelectMock = vi.fn()
const useForSelectLabelsMock = vi.fn()
const fetchNextPage = vi.fn()
const refetch = vi.fn()

vi.mock('@/features/for-select/use-for-select', async () => {
  const actual = await vi.importActual<
    typeof import('@/features/for-select/use-for-select')
  >('@/features/for-select/use-for-select')
  return {
    flattenForSelectPages: actual.flattenForSelectPages,
    useForSelect: (args: unknown) => useForSelectMock(args),
    useForSelectLabels: (args: unknown) => useForSelectLabelsMock(args),
  }
})

const labels: AsyncPaginatedSelectLabels = {
  placeholder: 'Select a manager…',
  searchPlaceholder: 'Search users…',
  empty: 'No users found.',
  error: 'Unable to load users.',
  retry: 'Retry',
  clearLabel: 'Clear manager',
  triggerLabel: 'Manager',
}

function queryState(overrides: Partial<ReturnType<typeof baseState>> = {}) {
  return { ...baseState(), ...overrides }
}

function baseState() {
  return {
    data: undefined as { pages: { items: ForSelectItem[] }[] } | undefined,
    isPending: false,
    isError: false,
    fetchNextPage,
    hasNextPage: false,
    isFetchingNextPage: false,
    refetch,
  }
}

function pagesOf(items: ForSelectItem[]) {
  return { pages: [{ items }] }
}

function renderSelect(props: Partial<Parameters<typeof AsyncPaginatedSelect>[0]> = {}) {
  const onChange = vi.fn()
  render(
    <AsyncPaginatedSelect
      resource="users"
      value={null}
      onChange={onChange}
      labels={labels}
      {...props}
    />,
  )
  return { onChange }
}

function open() {
  fireEvent.click(screen.getByRole('combobox', { name: 'Manager' }))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  useForSelectMock.mockReset()
  useForSelectLabelsMock.mockReset()
  fetchNextPage.mockReset()
  refetch.mockReset()
  useForSelectMock.mockReturnValue(queryState())
  useForSelectLabelsMock.mockReturnValue(new Map())
})

/**
 * `isItemDisabled` (spec 0123 AC-016): an option kept visible but not
 * selectable, e.g. a status the actor may only reach through a dedicated
 * action rather than a plain PATCH.
 */
describe('AsyncPaginatedSelect — isItemDisabled', () => {
  it('renders every option as selectable when the prop is omitted (unchanged default)', () => {
    useForSelectMock.mockReturnValue(
      queryState({ data: pagesOf([{ id: 5, label: 'Bob' }]) }),
    )
    renderSelect()
    open()
    expect(screen.getByRole('option', { name: /Bob/ })).toHaveAttribute(
      'aria-disabled',
      'false',
    )
  })

  it('marks a disabled option with aria-disabled, not by color alone', () => {
    useForSelectMock.mockReturnValue(
      queryState({ data: pagesOf([{ id: 5, label: 'Bob' }]) }),
    )
    renderSelect({ isItemDisabled: (item) => item.id === 5 })
    open()
    expect(screen.getByRole('option', { name: /Bob/ })).toHaveAttribute(
      'aria-disabled',
      'true',
    )
  })

  it('stays visible in the list while disabled', () => {
    useForSelectMock.mockReturnValue(
      queryState({ data: pagesOf([{ id: 5, label: 'Bob' }]) }),
    )
    renderSelect({ isItemDisabled: (item) => item.id === 5 })
    open()
    expect(screen.getByRole('option', { name: /Bob/ })).toBeInTheDocument()
  })

  it('does not emit onChange when a disabled option is clicked', () => {
    useForSelectMock.mockReturnValue(
      queryState({ data: pagesOf([{ id: 5, label: 'Bob' }]) }),
    )
    const { onChange } = renderSelect({ isItemDisabled: (item) => item.id === 5 })
    open()
    fireEvent.click(screen.getByRole('option', { name: /Bob/ }))
    expect(onChange).not.toHaveBeenCalled()
  })

  it('does not emit onChange when a disabled option is activated via keyboard (Enter)', () => {
    useForSelectMock.mockReturnValue(
      queryState({ data: pagesOf([{ id: 5, label: 'Bob' }]) }),
    )
    const { onChange } = renderSelect({ isItemDisabled: (item) => item.id === 5 })
    open()
    fireEvent.keyDown(screen.getByRole('option', { name: /Bob/ }), { key: 'Enter' })
    expect(onChange).not.toHaveBeenCalled()
  })

  it('keeps the popup open after clicking a disabled option', () => {
    useForSelectMock.mockReturnValue(
      queryState({ data: pagesOf([{ id: 5, label: 'Bob' }]) }),
    )
    renderSelect({ isItemDisabled: (item) => item.id === 5 })
    open()
    fireEvent.click(screen.getByRole('option', { name: /Bob/ }))
    expect(screen.getByRole('listbox')).toBeInTheDocument()
  })

  it('still selects the options the predicate leaves enabled', () => {
    useForSelectMock.mockReturnValue(
      queryState({
        data: pagesOf([
          { id: 5, label: 'Bob' },
          { id: 6, label: 'Alice' },
        ]),
      }),
    )
    const { onChange } = renderSelect({ isItemDisabled: (item) => item.id === 5 })
    open()
    fireEvent.click(screen.getByRole('option', { name: /Alice/ }))
    expect(onChange).toHaveBeenCalledWith(6)
  })
})
