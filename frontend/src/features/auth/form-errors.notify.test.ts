// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { act, renderHook } from '@testing-library/react'
import { AxiosError, type AxiosResponse } from 'axios'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { applyServerValidationErrors, notifyFormErrors } from '@/features/auth/form-errors'
import { useFormSubmitError } from '@/features/auth/use-form-submit-error'

/** User directive 2026-10-30: a refused save is always raised in the foreground, not only on its field. */

vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }))

const toastError = vi.mocked(toast.error)

function failure(status: number, data: Record<string, unknown>): AxiosError {
  const error = new AxiosError(String(status))
  error.response = { status, data } as AxiosResponse
  return error
}

function toastedDescription(): string {
  return (toastError.mock.calls.at(-1)?.[1] as { description: string }).description
}

beforeEach(async () => {
  toastError.mockClear()
  await i18n.changeLanguage('it')
})

describe('applyServerValidationErrors foreground toast', () => {
  it('toasts every 422 reason, including keys no form field owns', () => {
    const setError = vi.fn()
    const handled = applyServerValidationErrors(
      failure(422, { errors: { name: ['Nome obbligatorio'], lead_id: ['Lead già convertito'] } }),
      setError,
      ['name'] as never,
    )

    expect(handled).toBe(true)
    expect(setError).toHaveBeenCalledWith('name', { message: 'Nome obbligatorio' })
    expect(toastError).toHaveBeenCalledTimes(1)
    expect(toastError.mock.calls[0][0]).toBe('Impossibile salvare')
    expect(toastedDescription()).toBe('Nome obbligatorio\nLead già convertito')
  })

  it('falls back to the response message when the 422 carries no field errors', () => {
    applyServerValidationErrors(failure(422, { message: 'Operazione rifiutata' }), vi.fn(), [])

    expect(toastedDescription()).toBe('Operazione rifiutata')
  })

  it('stays silent on a non-422 failure: the caller owns that message', () => {
    expect(applyServerValidationErrors(failure(500, {}), vi.fn(), [])).toBe(false)
    expect(toastError).not.toHaveBeenCalled()
  })
})

describe('notifyFormErrors', () => {
  it('dedupes the reasons and collapses the overflow into one line', () => {
    notifyFormErrors(['a', 'a', 'b', 'c', 'd', 'e', 'f', ' '])

    expect(toastedDescription()).toBe('a\nb\nc\nd\n…e altri 2 errori')
  })

  it('raises nothing without a reason', () => {
    notifyFormErrors(['', '  '])

    expect(toastError).not.toHaveBeenCalled()
  })
})

describe('useFormSubmitError', () => {
  it('feeds the banner and toasts the message unless told it is already notified', () => {
    const { result } = renderHook(() => useFormSubmitError())

    act(() => result.current[1]('Errore generico'))
    expect(result.current[0]).toBe('Errore generico')
    expect(toastedDescription()).toBe('Errore generico')

    act(() => result.current[1]('Solo banner', { notify: false }))
    expect(result.current[0]).toBe('Solo banner')
    expect(toastError).toHaveBeenCalledTimes(1)

    act(() => result.current[1](null))
    expect(result.current[0]).toBeNull()
    expect(toastError).toHaveBeenCalledTimes(1)
  })
})
