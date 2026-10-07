import { describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { AxiosError, type AxiosResponse } from 'axios'
import { DetailError } from '@/components/detail/detail-panel'
import i18n from '@/i18n'

function httpError(status: number): AxiosError {
  return new AxiosError('failed', undefined, undefined, undefined, { status } as AxiosResponse)
}

function renderDetailError(error: unknown, onRetry = vi.fn()) {
  render(<DetailError error={error} message="Could not load the work order." retryLabel="Retry" onRetry={onRetry} />)
  return onRetry
}

describe('DetailError', () => {
  it('renders the controlled "record not found" state on a 404, with no retry', () => {
    renderDetailError(httpError(404))

    expect(screen.getByText(i18n.t('common.recordUnavailable.notFound.title'))).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Retry' })).not.toBeInTheDocument()
    expect(screen.queryByText('Could not load the work order.')).not.toBeInTheDocument()
  })

  it('renders the controlled "access denied" state on a 403, with no retry', () => {
    renderDetailError(httpError(403))

    expect(screen.getByText(i18n.t('common.recordUnavailable.forbidden.title'))).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Retry' })).not.toBeInTheDocument()
  })

  it('keeps the message and the retry for a transient failure', () => {
    const onRetry = renderDetailError(httpError(500))

    expect(screen.getByRole('alert')).toHaveTextContent('Could not load the work order.')
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }))
    expect(onRetry).toHaveBeenCalledOnce()
  })
})
