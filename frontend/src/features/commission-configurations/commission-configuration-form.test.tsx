import { fireEvent, render, screen } from '@testing-library/react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import i18n from '@/i18n'
import { CommissionConfigurationForm } from './commission-configuration-form'
import type { CommissionConfigurationDetailWithPermissions } from './types'

const useMeta = vi.hoisted(() => vi.fn())
vi.mock('@/features/authorization/use-resource-meta', () => ({
  useResourceMeta: (...args: unknown[]) => useMeta(...args),
}))
vi.mock('./commission-configuration-form-body', () => ({
  CommissionConfigurationFormBody: () => <div>form body</div>,
}))

const permissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: false },
  fields: {},
  actions: {},
}

describe('CommissionConfigurationForm metadata states', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('en')
  })

  beforeEach(() => vi.clearAllMocks())

  it('renders shape-matched skeletons while create metadata loads', () => {
    useMeta.mockReturnValue({ data: undefined, isError: false, refetch: vi.fn() })
    const { container } = render(<CommissionConfigurationForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />)
    expect(container.querySelectorAll('[data-slot="skeleton"]')).toHaveLength(8)
  })

  it('renders retry on metadata error', () => {
    const refetch = vi.fn()
    useMeta.mockReturnValue({ data: undefined, isError: true, refetch })
    render(<CommissionConfigurationForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />)
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }))
    expect(refetch).toHaveBeenCalled()
  })

  it('renders create and edit bodies with resolved permissions', () => {
    useMeta.mockReturnValue({ data: { permissions }, isError: false, refetch: vi.fn() })
    const { rerender } = render(<CommissionConfigurationForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />)
    expect(screen.getByText('form body')).toBeInTheDocument()
    const configuration = {
      id: 1,
      name: 'Rule',
      permissions,
    } as CommissionConfigurationDetailWithPermissions
    rerender(<CommissionConfigurationForm mode={{ type: 'edit', configuration }} onSuccess={vi.fn()} onCancel={vi.fn()} />)
    expect(screen.getByText('form body')).toBeInTheDocument()
  })
})
