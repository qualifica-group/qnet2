import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
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
    // Keep the real flatten helper; only the hooks are controlled.
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

function queryState(
  overrides: Partial<ReturnType<typeof baseState>> = {},
): ReturnType<typeof baseState> {
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

describe('AsyncPaginatedSelect', () => {
  it('shows the placeholder when nothing is selected', () => {
    renderSelect()
    expect(screen.getByText('Select a manager…')).toBeInTheDocument()
  })

  it('renders a skeleton (not a spinner) while the first page loads', () => {
    useForSelectMock.mockReturnValue(queryState({ isPending: true }))
    renderSelect()
    open()
    expect(screen.getByTestId('async-select-skeleton')).toBeInTheDocument()
  })

  it('renders the loaded options with subtitles', () => {
    useForSelectMock.mockReturnValue(
      queryState({
        data: pagesOf([{ id: 1, label: 'Jane Doe', subtitle: 'jane@acme.test' }]),
      }),
    )
    renderSelect()
    open()
    expect(screen.getByRole('option', { name: /Jane Doe/ })).toBeInTheDocument()
    expect(screen.getByText('jane@acme.test')).toBeInTheDocument()
  })

  it('renders avatars (initials fallback) in options when showAvatar is set', () => {
    useForSelectMock.mockReturnValue(
      queryState({
        data: pagesOf([
          { id: 1, label: 'Jane Doe', subtitle: 'jane@acme.test', avatar_url: null },
        ]),
      }),
    )
    renderSelect({ showAvatar: true })
    open()
    expect(screen.getByText('JD')).toBeInTheDocument()
  })

  it('does not render avatars by default', () => {
    useForSelectMock.mockReturnValue(
      queryState({ data: pagesOf([{ id: 1, label: 'Jane Doe' }]) }),
    )
    renderSelect()
    open()
    expect(screen.queryByText('JD')).not.toBeInTheDocument()
  })

  it('selecting an option sets it as the value and closes the popup', () => {
    useForSelectMock.mockReturnValue(
      queryState({ data: pagesOf([{ id: 5, label: 'Bob' }]) }),
    )
    const { onChange } = renderSelect({ value: null })
    open()
    fireEvent.click(screen.getByRole('option', { name: /Bob/ }))
    expect(onChange).toHaveBeenCalledWith(5)
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument()
  })

  it('selecting a new option replaces the previous single selection', () => {
    useForSelectMock.mockReturnValue(
      queryState({
        data: pagesOf([
          { id: 5, label: 'Bob' },
          { id: 6, label: 'Alice' },
        ]),
      }),
    )
    const { onChange } = renderSelect({
      value: 5,
      selectedItem: { id: 5, label: 'Bob' },
    })
    open()
    fireEvent.click(screen.getByRole('option', { name: /Alice/ }))
    expect(onChange).toHaveBeenCalledTimes(1)
    expect(onChange).toHaveBeenCalledWith(6)
  })

  it('renders the current value in the trigger with its avatar', () => {
    renderSelect({
      showAvatar: true,
      value: 42,
      selectedItem: { id: 42, label: 'Hydrated Manager' },
    })
    expect(screen.getByText('Hydrated Manager')).toBeInTheDocument()
    expect(screen.getByText('HM')).toBeInTheDocument()
  })

  it('clears the selection via the clear affordance', () => {
    const { onChange } = renderSelect({
      value: 42,
      selectedItem: { id: 42, label: 'Hydrated Manager' },
    })
    fireEvent.click(
      screen.getByRole('button', { name: 'Clear manager Hydrated Manager' }),
    )
    expect(onChange).toHaveBeenCalledWith(null)
  })

  it('falls back to #id for a selected value with no known label', () => {
    renderSelect({ value: 7 })
    expect(screen.getByText('#7')).toBeInTheDocument()
  })

  it('hydrates the selected label on mount via the ids-keyed label query', () => {
    renderSelect({ value: 7 })
    expect(useForSelectLabelsMock).toHaveBeenCalledWith(
      expect.objectContaining({ ids: [7], enabled: true }),
    )
  })

  it('resolves the trigger label from the ids-keyed label query', () => {
    useForSelectLabelsMock.mockReturnValue(
      new Map([[7, { id: 7, label: 'Label-Query User' }]]),
    )
    renderSelect({ value: 7 })
    expect(screen.getByText('Label-Query User')).toBeInTheDocument()
  })

  it('does not eagerly hydrate when the selected item is already known', () => {
    renderSelect({ value: 7, selectedItem: { id: 7, label: 'Known User' } })
    expect(useForSelectLabelsMock).toHaveBeenCalledWith(
      expect.objectContaining({ ids: [], enabled: false }),
    )
  })

  it('drives server search through the hook (debounced)', async () => {
    useForSelectMock.mockReturnValue(queryState({ data: pagesOf([]) }))
    renderSelect()
    open()

    fireEvent.change(screen.getByLabelText('Search users…'), {
      target: { value: 'jane' },
    })

    await waitFor(() =>
      expect(useForSelectMock).toHaveBeenCalledWith(
        expect.objectContaining({ search: 'jane' }),
      ),
    )
  })

  it('shows the empty state when the query returns no options', () => {
    useForSelectMock.mockReturnValue(queryState({ data: pagesOf([]) }))
    renderSelect()
    open()
    expect(screen.getByText('No users found.')).toBeInTheDocument()
  })

  it('shows the error state with a retry that refetches', () => {
    useForSelectMock.mockReturnValue(queryState({ isError: true }))
    renderSelect()
    open()
    expect(screen.getByText('Unable to load users.')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }))
    expect(refetch).toHaveBeenCalled()
  })

  it('loads the next page when the sentinel intersects (infinite scroll)', async () => {
    useForSelectMock.mockReturnValue(
      queryState({ data: pagesOf([{ id: 1, label: 'A' }]), hasNextPage: true }),
    )

    type ObserverCallback = (entries: { isIntersecting: boolean }[]) => void
    let trigger: ObserverCallback | null = null
    const observe = vi.fn()
    vi.stubGlobal(
      'IntersectionObserver',
      class {
        constructor(cb: ObserverCallback) {
          trigger = cb
        }
        observe = observe
        unobserve() {}
        disconnect() {}
        takeRecords() {
          return []
        }
      },
    )

    renderSelect()
    open()

    await screen.findByRole('option', { name: /A/ })
    await waitFor(() => expect(observe).toHaveBeenCalled())
    const fire = trigger as ObserverCallback | null
    fire?.([{ isIntersecting: true }])
    expect(fetchNextPage).toHaveBeenCalled()

    vi.unstubAllGlobals()
  })

  it('selects an option via the keyboard (Enter)', () => {
    useForSelectMock.mockReturnValue(
      queryState({ data: pagesOf([{ id: 5, label: 'Bob' }]) }),
    )
    const { onChange } = renderSelect({ value: null })
    open()
    fireEvent.keyDown(screen.getByRole('option', { name: /Bob/ }), {
      key: 'Enter',
    })
    expect(onChange).toHaveBeenCalledWith(5)
  })

  it('forwards id/aria-describedby/aria-invalid to the trigger, mirroring FormControl (Radix Slot)', () => {
    renderSelect({
      id: 'manager-field',
      'aria-describedby': 'manager-error',
      'aria-invalid': true,
    })
    const trigger = screen.getByRole('combobox', { name: 'Manager' })
    expect(trigger).toHaveAttribute('id', 'manager-field')
    expect(trigger).toHaveAttribute('aria-describedby', 'manager-error')
    expect(trigger).toHaveAttribute('aria-invalid', 'true')
  })

  it('activates the trigger when its associated <label> is clicked (native id/for wiring)', () => {
    const onChange = vi.fn()
    render(
      <>
        <label htmlFor="manager-field">Manager</label>
        <AsyncPaginatedSelect
          id="manager-field"
          resource="users"
          value={null}
          onChange={onChange}
          labels={labels}
        />
      </>,
    )
    fireEvent.click(screen.getByText('Manager'))
    expect(screen.getByRole('listbox')).toBeInTheDocument()
  })

  it('renders no extra wrapper around the trigger when action is omitted', () => {
    const { container } = render(
      <AsyncPaginatedSelect
        resource="users"
        value={null}
        onChange={vi.fn()}
        labels={labels}
      />,
    )
    expect(container.firstElementChild).toBe(
      screen.getByRole('combobox', { name: 'Manager' }),
    )
  })

  it('renders the action slot next to the trigger, which stays accessible', () => {
    renderSelect({ action: <button type="button">Add manager</button> })
    expect(
      screen.getByRole('combobox', { name: 'Manager' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Add manager' }),
    ).toBeInTheDocument()
  })

  it('mounts closed by default, unchanged for existing callers', () => {
    renderSelect()
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument()
  })

})

/**
 * `pinnedItem` (spec 0101): an endpoint narrowed by a visibility scope
 * legitimately omits rows the actor may not browse — including, on an edit
 * form, the value already persisted on the record. Without the pin the user
 * could change the selection and never pick the original back.
 */
describe('AsyncPaginatedSelect — pinnedItem', () => {
  const PINNED: ForSelectItem = { id: 99, label: 'COM-0001 — Rifacimento impianto' }

  it('offers the pinned option even when the server returns none', () => {
    useForSelectMock.mockReturnValue(queryState({ data: pagesOf([]) }))
    renderSelect({ value: 99, pinnedItem: PINNED })
    open()

    expect(screen.getByRole('option', { name: /COM-0001/ })).toBeInTheDocument()
    // The empty state belongs to a genuinely empty list, not to a pinned one.
    expect(screen.queryByText('No users found.')).not.toBeInTheDocument()
  })

  it('lets the user pick the pinned option back after changing the selection', () => {
    useForSelectMock.mockReturnValue(
      queryState({ data: pagesOf([{ id: 1, label: 'COM-0002 — Altro' }]) }),
    )
    const { onChange } = renderSelect({ value: 1, pinnedItem: PINNED })
    open()

    fireEvent.click(screen.getByRole('option', { name: /COM-0001/ }))

    expect(onChange).toHaveBeenCalledWith(99)
  })

  it('leads the list, before the server rows', () => {
    useForSelectMock.mockReturnValue(
      queryState({ data: pagesOf([{ id: 1, label: 'AAA first alphabetically' }]) }),
    )
    renderSelect({ value: 99, pinnedItem: PINNED })
    open()

    const rendered = screen.getAllByRole('option').map((node) => node.textContent ?? '')
    expect(rendered[0]).toContain('COM-0001')
  })

  it('does NOT duplicate a pin the server also returns', () => {
    useForSelectMock.mockReturnValue(queryState({ data: pagesOf([PINNED]) }))
    renderSelect({ value: 99, pinnedItem: PINNED })
    open()

    expect(screen.getAllByRole('option', { name: /COM-0001/ })).toHaveLength(1)
  })

  it("prefers the server's row over the pin, so a stale pinned label never wins", () => {
    useForSelectMock.mockReturnValue(
      queryState({ data: pagesOf([{ id: 99, label: 'COM-0001 — Renamed upstream' }]) }),
    )
    renderSelect({ value: 99, pinnedItem: PINNED })
    open()

    expect(screen.getByRole('option', { name: /Renamed upstream/ })).toBeInTheDocument()
    expect(screen.queryByRole('option', { name: /Rifacimento impianto/ })).not.toBeInTheDocument()
  })

  it('changes nothing when omitted: the list is exactly the server rows', () => {
    useForSelectMock.mockReturnValue(
      queryState({ data: pagesOf([{ id: 1, label: 'Only row' }]) }),
    )
    renderSelect({ value: 1 })
    open()

    expect(screen.getAllByRole('option')).toHaveLength(1)
    expect(screen.getByRole('option', { name: 'Only row' })).toBeInTheDocument()
  })

  it('still shows the empty state when there is neither a pin nor a row', () => {
    useForSelectMock.mockReturnValue(queryState({ data: pagesOf([]) }))
    renderSelect()
    open()

    expect(screen.getByText('No users found.')).toBeInTheDocument()
  })
})
