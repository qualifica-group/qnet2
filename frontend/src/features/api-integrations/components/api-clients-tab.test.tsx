import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import i18n from '@/i18n'
import { ApiClientsTab } from '@/features/api-integrations/components/api-clients-tab'
import { buildClient, createWrapper } from '@/features/api-integrations/test-support'

const api = vi.hoisted(() => ({
  fetchApiClient: vi.fn(),
  createApiClient: vi.fn(),
  updateApiClient: vi.fn(),
  rotateApiClientKey: vi.fn(),
  deleteApiClient: vi.fn(),
}))
const permissions = vi.hoisted(() => ({ granted: new Set<string>() }))
const confirmMock = vi.hoisted(() => vi.fn())
const refreshMock = vi.hoisted(() => vi.fn())

vi.mock('@/features/api-integrations/api', () => api)
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))
vi.mock('@/components/confirm-dialog-context', () => ({ useConfirm: () => confirmMock }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => permissions.granted.has(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))
vi.mock('@/features/table/table-view', async () => {
  const React = await import('react')
  return {
    TableView: React.forwardRef(function TableViewStub(
      props: { onAction: (action: { key: string }, row: { id: number; name: string }) => void },
      ref: React.Ref<{ refresh: () => void }>,
    ) {
      React.useImperativeHandle(ref, () => ({ refresh: refreshMock }))
      return (
        <div>
          <button onClick={() => props.onAction({ key: 'view' }, { id: 5, name: 'ERP' })}>row-view</button>
          <button onClick={() => props.onAction({ key: 'rotate-key' }, { id: 5, name: 'ERP' })}>row-rotate</button>
          <button onClick={() => props.onAction({ key: 'delete' }, { id: 5, name: 'ERP' })}>row-delete</button>
        </div>
      )
    }),
  }
})

const PLAIN_KEY = 'qk_live_super_secret_key_123'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  Object.values(api).forEach((mock) => mock.mockReset())
  confirmMock.mockReset()
  refreshMock.mockReset()
  permissions.granted = new Set(['api-clients.view', 'api-clients.create', 'api-clients.update', 'api-clients.delete'])
  api.fetchApiClient.mockResolvedValue(buildClient())
})

function renderTab() {
  const harness = createWrapper()
  render(<ApiClientsTab />, { wrapper: harness.Wrapper })
  return harness
}

describe('ApiClientsTab — create flow (AC-021)', () => {
  it('hides "New client" without api-clients.create', () => {
    permissions.granted = new Set(['api-clients.view'])
    renderTab()
    expect(screen.queryByRole('button', { name: 'New client' })).not.toBeInTheDocument()
  })

  it('blocks an empty submit with the accessible error triad', async () => {
    renderTab()
    fireEvent.click(screen.getByRole('button', { name: 'New client' }))
    await screen.findByLabelText(/^Name/)

    fireEvent.click(screen.getByRole('button', { name: 'Create client' }))

    const nameInput = await screen.findByLabelText(/^Name/)
    await waitFor(() => expect(nameInput).toHaveAttribute('aria-invalid', 'true'))
    const errorId = nameInput.getAttribute('aria-describedby')?.split(' ').pop() as string
    expect(document.getElementById(errorId)).toHaveAttribute('role', 'alert')
    expect(api.createApiClient).not.toHaveBeenCalled()
  })

  it('sends the contract payload, shows the key once and drops it on close', async () => {
    api.createApiClient.mockResolvedValue({ client: buildClient(), plain_text_key: PLAIN_KEY })
    const { client } = renderTab()

    fireEvent.click(screen.getByRole('button', { name: 'New client' }))
    fireEvent.change(await screen.findByLabelText(/^Name/), { target: { value: 'ERP' } })
    fireEvent.change(screen.getByLabelText(/Rate limit/), { target: { value: '120' } })
    fireEvent.click(screen.getByRole('button', { name: 'Create client' }))

    expect(await screen.findByText(PLAIN_KEY)).toBeInTheDocument()
    expect(api.createApiClient).toHaveBeenCalledWith({
      name: 'ERP',
      description: null,
      rate_limit_per_minute: 120,
      expires_at: null,
    })
    expect(screen.getByText(/will not be shown again/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Copy' })).toBeInTheDocument()
    expect(refreshMock).toHaveBeenCalled()

    fireEvent.click(screen.getByRole('button', { name: 'I have copied the key' }))

    await waitFor(() => expect(screen.queryByText(PLAIN_KEY)).not.toBeInTheDocument())
    await waitFor(() => expect(client.getMutationCache().getAll()).toHaveLength(0))
    expect(JSON.stringify(client.getQueryCache().getAll().map((query) => query.state.data))).not.toContain(PLAIN_KEY)
  })
})

describe('ApiClientsTab — rotate and revoke (AC-022)', () => {
  it('asks for confirmation and does nothing when declined', async () => {
    confirmMock.mockResolvedValue(false)
    renderTab()
    fireEvent.click(screen.getByRole('button', { name: 'row-rotate' }))
    await waitFor(() => expect(confirmMock).toHaveBeenCalled())
    expect(api.rotateApiClientKey).not.toHaveBeenCalled()
  })

  it('shows the new key in the same dialog after a confirmed rotation', async () => {
    confirmMock.mockResolvedValue(true)
    api.rotateApiClientKey.mockResolvedValue({ client: buildClient(), plain_text_key: PLAIN_KEY })
    renderTab()
    fireEvent.click(screen.getByRole('button', { name: 'row-rotate' }))

    const dialog = await screen.findByRole('dialog', { name: /New API key for "ERP"/ })
    expect(within(dialog).getByText(PLAIN_KEY)).toBeInTheDocument()
    expect(api.rotateApiClientKey).toHaveBeenCalledWith(5)
  })

  it('revokes after confirmation and refreshes the grid', async () => {
    confirmMock.mockResolvedValue(true)
    api.deleteApiClient.mockResolvedValue(undefined)
    renderTab()
    fireEvent.click(screen.getByRole('button', { name: 'row-delete' }))
    await waitFor(() => expect(api.deleteApiClient).toHaveBeenCalledWith(5))
    await waitFor(() => expect(refreshMock).toHaveBeenCalled())
  })

  it('shows rotate and revoke in the edit dialog only with update/delete permission', async () => {
    renderTab()
    fireEvent.click(screen.getByRole('button', { name: 'row-view' }))
    expect(await screen.findByRole('button', { name: 'Rotate key' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Revoke client' })).toBeInTheDocument()
  })

  it('hides rotate and revoke without those permissions', async () => {
    permissions.granted = new Set(['api-clients.view'])
    renderTab()
    fireEvent.click(screen.getByRole('button', { name: 'row-view' }))
    await screen.findByLabelText(/^Name/)
    expect(screen.queryByRole('button', { name: 'Rotate key' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Revoke client' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Save' })).not.toBeInTheDocument()
  })

  it('shows the technical user read-only in the client detail (AC-013)', async () => {
    renderTab()
    fireEvent.click(screen.getByRole('button', { name: 'row-view' }))
    expect(await screen.findByText('Technical user')).toBeInTheDocument()
    expect(screen.getByText('API · ERP')).toBeInTheDocument()
    expect(screen.getByText('With the key alone you act as this user, with super-admin permissions.')).toBeInTheDocument()
  })

  it('has no scope control in the create form (AC-013)', async () => {
    renderTab()
    fireEvent.click(screen.getByRole('button', { name: 'New client' }))
    await screen.findByLabelText(/^Name/)
    expect(screen.queryByRole('checkbox')).not.toBeInTheDocument()
    expect(screen.queryByText(/scope/i)).not.toBeInTheDocument()
  })
})
