import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { TimeEntryDayNote } from '@/features/time-entries/days/time-entry-day-note'

const saveTimeEntryDayNoteMock = vi.fn()

vi.mock('@/features/time-entries/api', () => ({
  saveTimeEntryDayNote: (...args: unknown[]) => saveTimeEntryDayNoteMock(...args),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  saveTimeEntryDayNoteMock.mockReset()
  saveTimeEntryDayNoteMock.mockResolvedValue({ date: '2026-09-14', note: 'Updated' })
})

describe('TimeEntryDayNote (AC-036)', () => {
  it('renders nothing when read-only and empty', () => {
    const Wrapper = wrapper()
    const { container } = render(
      <Wrapper>
        <TimeEntryDayNote canWrite={false} date="2026-09-14" note={null} />
      </Wrapper>,
    )

    expect(container).toBeEmptyDOMElement()
  })

  it('shows the note read-only, with no way to edit it, when canWrite is false', () => {
    const Wrapper = wrapper()
    render(
      <Wrapper>
        <TimeEntryDayNote canWrite={false} date="2026-09-14" note="Existing note" />
      </Wrapper>,
    )

    expect(screen.getByText('Existing note')).toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
  })

  it('saves on blur only when the trimmed value changed, forwarding the selected user id', async () => {
    const Wrapper = wrapper()
    render(
      <Wrapper>
        <TimeEntryDayNote canWrite date="2026-09-14" note="Existing note" selectedUserId={7} />
      </Wrapper>,
    )

    fireEvent.click(screen.getByText('Existing note'))
    const textarea = screen.getByRole('textbox')
    fireEvent.change(textarea, { target: { value: 'Existing note, edited' } })
    fireEvent.blur(textarea)

    await waitFor(() =>
      expect(saveTimeEntryDayNoteMock).toHaveBeenCalledWith({
        user_id: 7,
        date: '2026-09-14',
        note: 'Existing note, edited',
      }),
    )
  })

  it('does not call the API when the blurred value is unchanged (trimmed)', () => {
    const Wrapper = wrapper()
    render(
      <Wrapper>
        <TimeEntryDayNote canWrite date="2026-09-14" note="Existing note" />
      </Wrapper>,
    )

    fireEvent.click(screen.getByText('Existing note'))
    const textarea = screen.getByRole('textbox')
    fireEvent.change(textarea, { target: { value: '  Existing note  ' } })
    fireEvent.blur(textarea)

    expect(saveTimeEntryDayNoteMock).not.toHaveBeenCalled()
  })

  it('shows the click-to-add placeholder when writable and empty', () => {
    const Wrapper = wrapper()
    render(
      <Wrapper>
        <TimeEntryDayNote canWrite date="2026-09-14" note={null} />
      </Wrapper>,
    )

    expect(screen.getByText('Add a note for this day')).toBeInTheDocument()
  })
})
