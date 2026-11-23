import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { createWrapper } from '@/integrations/formalab/test-support'
import { useActiveIntegrations, useIsIntegrationActive } from '@/integrations/use-active-integrations'

const fetchActiveIntegrationsMock = vi.fn()
vi.mock('@/integrations/api', () => ({ fetchActiveIntegrations: () => fetchActiveIntegrationsMock() }))

function Probe() {
  const { active, isLoading } = useActiveIntegrations()
  const { isActive } = useIsIntegrationActive('formalab')
  return <output>{`${isLoading ? 'loading' : 'ready'}|${active.join(',')}|${isActive}`}</output>
}

describe('useActiveIntegrations', () => {
  beforeEach(() => {
    fetchActiveIntegrationsMock.mockReset()
  })

  it('exposes the keys the backend lists', async () => {
    fetchActiveIntegrationsMock.mockResolvedValue({ active: ['formalab'] })
    const { Wrapper } = createWrapper()

    render(<Probe />, { wrapper: Wrapper })

    expect(screen.getByRole('status')).toHaveTextContent('loading||false')
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('ready|formalab|true'))
  })

  it('stays empty when the call fails', async () => {
    fetchActiveIntegrationsMock.mockRejectedValue(new Error('boom'))
    const { Wrapper } = createWrapper()

    render(<Probe />, { wrapper: Wrapper })

    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('ready||false'))
  })
})
