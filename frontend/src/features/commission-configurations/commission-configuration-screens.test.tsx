import { fireEvent, render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import i18n from '@/i18n'
import {
  CommissionConfigurationDetailScreen,
  CommissionConfigurationFormScreen,
  moduleScreen,
} from './commission-configuration-screens'

const useDetail = vi.hoisted(() => vi.fn())
const formProps = vi.hoisted(() => ({ current: null as null | Record<string, unknown> }))
vi.mock('@/hooks/use-entity-detail', () => ({
  useEntityDetail: (...args: unknown[]) => useDetail(...args),
}))
vi.mock('./commission-configuration-form', () => ({
  CommissionConfigurationForm: (props: Record<string, unknown>) => {
    formProps.current = props
    return <div>configuration form</div>
  },
}))
vi.mock('./commission-configuration-detail', () => ({
  CommissionConfigurationDetailView: () => <div>configuration detail</div>,
}))

function wrapper({ children }: { children: ReactNode }) {
  return <QueryClientProvider client={new QueryClient()}>{children}</QueryClientProvider>
}

const detail = {
  id: 1,
  name: 'Rule',
  permissions: { resource: {}, fields: {}, actions: {} },
}

describe('commission configuration screens', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('en')
  })

  beforeEach(() => {
    vi.clearAllMocks()
    formProps.current = null
  })

  it('covers detail loading, error retry and ready states', () => {
    const refetch = vi.fn()
    useDetail.mockReturnValue({ data: undefined, isLoading: true, isError: false, refetch })
    const { rerender } = render(<CommissionConfigurationDetailScreen id={1} />)
    expect(document.querySelector('[data-slot="skeleton"]')).toBeInTheDocument()
    useDetail.mockReturnValue({ data: undefined, isLoading: false, isError: true, refetch })
    rerender(<CommissionConfigurationDetailScreen id={1} />)
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }))
    expect(refetch).toHaveBeenCalled()
    useDetail.mockReturnValue({ data: detail, isLoading: false, isError: false, refetch })
    rerender(<CommissionConfigurationDetailScreen id={1} />)
    expect(screen.getByText('configuration detail')).toBeInTheDocument()
  })

  it('renders create and forwards saved ids', () => {
    const onSuccess = vi.fn()
    render(<CommissionConfigurationFormScreen mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper })
    expect(screen.getByText('configuration form')).toBeInTheDocument()
    ;(formProps.current?.onSuccess as (saved: typeof detail) => void)(detail)
    expect(onSuccess).toHaveBeenCalledWith(1)
  })

  it('covers edit loading, error retry and ready states', () => {
    const refetch = vi.fn()
    useDetail.mockReturnValue({ data: undefined, isLoading: true, isError: false, refetch })
    const props = { mode: { type: 'edit' as const, id: 1 }, onSuccess: vi.fn(), onCancel: vi.fn() }
    const { rerender } = render(<CommissionConfigurationFormScreen {...props} />, { wrapper })
    expect(document.querySelectorAll('[data-slot="skeleton"]')).toHaveLength(8)
    useDetail.mockReturnValue({ data: undefined, isLoading: false, isError: true, refetch })
    rerender(<CommissionConfigurationFormScreen {...props} />)
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }))
    expect(refetch).toHaveBeenCalled()
    useDetail.mockReturnValue({ data: detail, isLoading: false, isError: false, refetch })
    rerender(<CommissionConfigurationFormScreen {...props} />)
    expect(screen.getByText('configuration form')).toBeInTheDocument()
  })

  it('exports the expected module registry descriptor', () => {
    expect(moduleScreen).toMatchObject({
      domain: 'commission-configurations',
      basePath: '/commission-configurations',
    })
  })
})
