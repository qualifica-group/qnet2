import { fireEvent, render, screen } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { AppVersionBanner } from '@/features/app-version/app-version-banner'

const useAppVersionMock = vi.fn()

vi.mock('@/features/app-version/use-app-version', () => ({
  useAppVersion: (enabled: boolean) => useAppVersionMock(enabled),
}))

vi.mock('@/config/env', () => ({
  env: { isProductionBuild: true },
}))

describe('AppVersionBanner', () => {
  beforeEach(() => {
    useAppVersionMock.mockReset()
  })

  it('renders nothing while the client is on the deployed build', () => {
    useAppVersionMock.mockReturnValue({ isOutdated: false })

    const { container } = render(<AppVersionBanner />)

    expect(container).toBeEmptyDOMElement()
  })

  it('announces the new version with a reload action', () => {
    useAppVersionMock.mockReturnValue({ isOutdated: true })

    render(<AppVersionBanner />)

    expect(screen.getByRole('status')).toHaveTextContent('appVersion.available')
    expect(screen.getByRole('button', { name: 'appVersion.reload' })).toBeInTheDocument()
  })

  it('reloads onto the new build when the action is used', () => {
    useAppVersionMock.mockReturnValue({ isOutdated: true })
    const reload = vi.fn()
    Object.defineProperty(window, 'location', {
      configurable: true,
      value: { ...window.location, reload },
    })

    render(<AppVersionBanner />)
    fireEvent.click(screen.getByRole('button', { name: 'appVersion.reload' }))

    expect(reload).toHaveBeenCalledTimes(1)
  })

  it('runs the check only in a production bundle', () => {
    useAppVersionMock.mockReturnValue({ isOutdated: false })

    render(<AppVersionBanner />)

    expect(useAppVersionMock).toHaveBeenCalledWith(true)
  })
})
