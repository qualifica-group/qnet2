import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import '@/i18n'
import { ArchivioSmartConnectionDialog } from '@/features/document-checklists/archivio-smart-connection-dialog'
import type { ArchivioSmartSettings } from '@/features/document-checklists/types'

const getSettingsMock = vi.fn()
const updateSettingsMock = vi.fn()
const testConnectionMock = vi.fn()
vi.mock('@/features/document-checklists/api', () => ({
  getArchivioSmartSettings: () => getSettingsMock(),
  updateArchivioSmartSettings: (payload: unknown) => updateSettingsMock(payload),
  testArchivioSmartConnection: () => testConnectionMock(),
}))

const SAVED: ArchivioSmartSettings = { base_url: 'https://archivio.example.com/api', has_token: true, updated_at: null }
const EMPTY: ArchivioSmartSettings = { base_url: null, has_token: false, updated_at: null }

function httpError(status: number, data: object): AxiosError {
  return Object.assign(new AxiosError('request failed'), { response: { status, data } })
}

function renderDialog() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ArchivioSmartConnectionDialog open onOpenChange={() => undefined} />
    </QueryClientProvider>,
  )
}

describe('ArchivioSmartConnectionDialog', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('shows the base URL and "Token impostato" without ever showing the token', async () => {
    getSettingsMock.mockResolvedValue(SAVED)
    renderDialog()

    expect(await screen.findByLabelText('URL base')).toHaveValue(SAVED.base_url)
    expect(screen.getByText('Token impostato')).toBeInTheDocument()
    const token = screen.getByLabelText('Token')
    expect(token).toHaveValue('')
    expect(token).toHaveAttribute('type', 'password')
    expect(token).toHaveAttribute('placeholder', 'Lascia vuoto per non modificarlo')
  })

  it('leaves the token empty on save (the api layer omits it)', async () => {
    getSettingsMock.mockResolvedValue(SAVED)
    updateSettingsMock.mockResolvedValue(SAVED)
    renderDialog()

    await screen.findByLabelText('URL base')
    fireEvent.click(screen.getByRole('button', { name: 'Salva connessione' }))

    await waitFor(() => expect(updateSettingsMock).toHaveBeenCalled())
    expect(updateSettingsMock.mock.calls[0][0]).toEqual({ base_url: SAVED.base_url, token: '' })
  })

  it('sends the typed token', async () => {
    getSettingsMock.mockResolvedValue(EMPTY)
    updateSettingsMock.mockResolvedValue(SAVED)
    renderDialog()

    fireEvent.change(await screen.findByLabelText('URL base'), { target: { value: 'https://a.example.com' } })
    fireEvent.change(screen.getByLabelText('Token'), { target: { value: 'secret-token-123' } })
    fireEvent.click(screen.getByRole('button', { name: 'Salva connessione' }))

    await waitFor(() =>
      expect(updateSettingsMock).toHaveBeenCalledWith({ base_url: 'https://a.example.com', token: 'secret-token-123' }),
    )
  })

  it('rejects an invalid URL with the accessible error triad', async () => {
    getSettingsMock.mockResolvedValue(EMPTY)
    renderDialog()

    const input = await screen.findByLabelText('URL base')
    fireEvent.change(input, { target: { value: 'not a url' } })
    fireEvent.click(screen.getByRole('button', { name: 'Salva connessione' }))

    expect(await screen.findByText('Inserisci un URL valido.')).toHaveAttribute('id')
    expect(input).toHaveAttribute('aria-invalid', 'true')
    expect(input).toHaveAttribute('aria-describedby')
    expect(updateSettingsMock).not.toHaveBeenCalled()
  })

  it('maps a server 422 onto the base URL field', async () => {
    getSettingsMock.mockResolvedValue(SAVED)
    updateSettingsMock.mockRejectedValue(httpError(422, { errors: { base_url: ['Schema non consentito dal server'] } }))
    renderDialog()

    await screen.findByLabelText('URL base')
    fireEvent.click(screen.getByRole('button', { name: 'Salva connessione' }))

    await waitFor(() => expect(screen.getByLabelText('URL base')).toHaveAttribute('aria-invalid', 'true'))
  })

  it('disables the test while the connection is not saved', async () => {
    getSettingsMock.mockResolvedValue(EMPTY)
    renderDialog()

    await screen.findByLabelText('URL base')
    expect(screen.getByText('Token non impostato')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Prova connessione' })).toBeDisabled()
  })

  it('shows a successful test outcome in a status region', async () => {
    getSettingsMock.mockResolvedValue(SAVED)
    testConnectionMock.mockResolvedValue({ ok: true, http_status: 200, message: 'Tutto a posto' })
    renderDialog()

    await screen.findByLabelText('URL base')
    fireEvent.click(screen.getByRole('button', { name: 'Prova connessione' }))

    const status = screen.getByRole('status')
    await waitFor(() => expect(status).toHaveTextContent('Connessione riuscita.'))
    expect(status).toHaveTextContent('Codice HTTP 200')
    expect(status).toHaveTextContent('Tutto a posto')
  })

  it('shows a failed test outcome with HTTP code and message', async () => {
    getSettingsMock.mockResolvedValue(SAVED)
    testConnectionMock.mockResolvedValue({ ok: false, http_status: 401, message: 'Token rifiutato' })
    renderDialog()

    await screen.findByLabelText('URL base')
    fireEvent.click(screen.getByRole('button', { name: 'Prova connessione' }))

    const status = screen.getByRole('status')
    await waitFor(() => expect(status).toHaveTextContent('Connessione non riuscita.'))
    expect(status).toHaveTextContent('Codice HTTP 401')
    expect(status).toHaveTextContent('Token rifiutato')
  })
})
