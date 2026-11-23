import { beforeEach, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { HealthCheckCard } from '@/features/system-health/health-check-card'
import type { HealthCheck } from '@/features/system-health/types'

function check(key: string, detailKey: string): HealthCheck {
  return {
    key,
    status: 'ok',
    latency_ms: null,
    message: null,
    details: [{ key: detailKey, status: 'ok', value: null, message: null }],
  }
}

describe('HealthCheckCard labels', () => {
  beforeEach(() => i18n.changeLanguage('it'))

  it('labels a core check from the systemHealth namespace', () => {
    render(<HealthCheckCard check={check('database', 'connection')} />)

    expect(screen.getByRole('heading', { name: i18n.t('subsystems.database', { ns: 'systemHealth' }) })).toBeInTheDocument()
  })

  it('labels an unknown check and its details from the check\'s own namespace', () => {
    render(<HealthCheckCard check={check('formalab', 'last_sync')} />)

    expect(screen.getByRole('heading', { name: 'Formalab' })).toBeInTheDocument()
    expect(screen.getByText('Ultima sincronizzazione')).toBeInTheDocument()
  })

  it('falls back to the raw keys when nothing labels the check', () => {
    render(<HealthCheckCard check={check('mystery', 'probe')} />)

    expect(screen.getByRole('heading', { name: 'mystery' })).toBeInTheDocument()
    expect(screen.getByText('probe')).toBeInTheDocument()
  })
})
