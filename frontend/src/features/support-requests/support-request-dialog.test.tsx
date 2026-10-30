import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, type AxiosResponse } from 'axios'
import '@/i18n'
import { SupportRequestButton } from '@/features/support-requests/support-request-button'
import type { SupportRequestResult, SupportRequestTopic } from '@/features/support-requests/types'

/** Spec 0334 AC-014..AC-017: the header button, the two-step dialog, the confirmation and the 422 path. */

const fetchTopicsMock = vi.fn<() => Promise<SupportRequestTopic[]>>()
const submitMock = vi.fn<(body: FormData) => Promise<SupportRequestResult>>()
vi.mock('@/features/support-requests/api', () => ({
  fetchSupportRequestTopics: () => fetchTopicsMock(),
  submitSupportRequest: (body: FormData) => submitMock(body),
}))

const notifyFormErrorsMock = vi.fn()
vi.mock('sonner', () => ({
  toast: { error: (...args: unknown[]) => notifyFormErrorsMock(...args), success: vi.fn() },
}))

const TOPICS: SupportRequestTopic[] = [
  { id: 1, name: 'Stampante', description: 'La stampa non parte', icon: 'printer' },
  { id: 2, name: 'Password', description: null, icon: null },
]

function renderButton() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <SupportRequestButton />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

async function openDialog() {
  renderButton()
  fireEvent.click(screen.getByRole('button', { name: 'Assistenza tecnica' }))
  return screen.findByRole('dialog', { name: 'Assistenza tecnica' })
}

function fillValidRequest() {
  fireEvent.click(screen.getByRole('radio', { name: /Stampante/ }))
  fireEvent.change(screen.getByRole('textbox', { name: /Descrivi il problema/ }), {
    target: { value: 'Non stampa' },
  })
}

function unprocessable(errors: Record<string, string[]>) {
  return new AxiosError('Unprocessable', '422', undefined, undefined, {
    status: 422,
    data: { success: false, message: 'Dati non validi', errors },
  } as AxiosResponse)
}

beforeEach(() => {
  fetchTopicsMock.mockReset()
  submitMock.mockReset()
  notifyFormErrorsMock.mockReset()
  fetchTopicsMock.mockResolvedValue(TOPICS)
})

describe('SupportRequestDialog', () => {
  it('does not fetch the topics until the dialog is opened (AC-014)', async () => {
    renderButton()
    expect(fetchTopicsMock).not.toHaveBeenCalled()

    fireEvent.click(screen.getByRole('button', { name: 'Assistenza tecnica' }))

    await screen.findByRole('radio', { name: /Stampante/ })
    expect(fetchTopicsMock).toHaveBeenCalledTimes(1)
  })

  it('shows the topics as selectable options and flags a missing topic and description accessibly (AC-015)', async () => {
    await openDialog()
    await screen.findByRole('radio', { name: /Stampante/ })

    fireEvent.click(screen.getByRole('button', { name: 'Invia richiesta' }))

    const description = await screen.findByRole('textbox', { name: /Descrivi il problema/ })
    await waitFor(() => expect(description).toHaveAttribute('aria-invalid', 'true'))
    expect(description).toHaveAccessibleDescription('Descrivi il problema.')
    expect(screen.getAllByRole('alert').map((alert) => alert.textContent)).toContain('Scegli il tipo di problema.')
    expect(submitMock).not.toHaveBeenCalled()
  })

  it('sends a multipart request and confirms with the task number and the link when allowed (AC-016)', async () => {
    submitMock.mockResolvedValue({ task_id: 42, can_view_task: true })
    await openDialog()
    await screen.findByRole('radio', { name: /Stampante/ })
    fillValidRequest()
    fireEvent.click(screen.getByRole('switch', { name: /urgente/ }))

    fireEvent.click(screen.getByRole('button', { name: 'Invia richiesta' }))

    expect(await screen.findByText('Richiesta n. 42 inviata')).toBeInTheDocument()
    const body = submitMock.mock.calls[0][0]
    expect(body.get('support_topic_id')).toBe('1')
    expect(body.get('description')).toBe('Non stampa')
    expect(body.get('is_urgent')).toBe('1')
    expect(body.get('page_url')).toBe(`${window.location.pathname}${window.location.search}`)
    expect(body.get('user_agent')).toBe(navigator.userAgent)
    expect(screen.getByRole('link', { name: 'Apri il task' })).toHaveAttribute('href', '/tasks/42')
  })

  it('omits the task link when the actor cannot view tasks (AC-016)', async () => {
    submitMock.mockResolvedValue({ task_id: 7, can_view_task: false })
    await openDialog()
    await screen.findByRole('radio', { name: /Stampante/ })
    fillValidRequest()

    fireEvent.click(screen.getByRole('button', { name: 'Invia richiesta' }))

    expect(await screen.findByText('Richiesta n. 7 inviata')).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Apri il task' })).not.toBeInTheDocument()
  })

  it('keeps the dialog open with the data and raises the shared toast on a 422 (AC-016)', async () => {
    submitMock.mockRejectedValue(unprocessable({ description: ['Descrizione troppo lunga.'] }))
    await openDialog()
    await screen.findByRole('radio', { name: /Stampante/ })
    fillValidRequest()

    fireEvent.click(screen.getByRole('button', { name: 'Invia richiesta' }))

    await waitFor(() => expect(notifyFormErrorsMock).toHaveBeenCalled())
    expect(screen.getByRole('dialog', { name: 'Assistenza tecnica' })).toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: /Descrivi il problema/ })).toHaveValue('Non stampa')
    expect(screen.getByRole('radio', { name: /Stampante/ })).toBeChecked()
  })

  it('lists attached files, lets the user remove them and caps them at five', async () => {
    await openDialog()
    await screen.findByRole('radio', { name: /Stampante/ })
    const input = screen.getByLabelText('Schermate o file (facoltativo)')
    const files = Array.from({ length: 6 }, (_, index) => new File(['x'], `shot-${index}.png`, { type: 'image/png' }))

    fireEvent.change(input, { target: { files } })

    expect(await screen.findAllByRole('listitem')).toHaveLength(5)
    expect(screen.getByText('Puoi allegare al massimo 5 file.')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Rimuovi shot-0.png' }))
    expect(screen.getAllByRole('listitem')).toHaveLength(4)
  })

  it('explains that support is not configured and offers no submit when there is no active topic (AC-017)', async () => {
    fetchTopicsMock.mockResolvedValue([])
    await openDialog()

    expect(await screen.findByText(/Assistenza non ancora configurata/)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Invia richiesta' })).not.toBeInTheDocument()
  })
})
