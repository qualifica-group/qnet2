import { beforeAll, describe, expect, it } from 'vitest'
import { act, renderHook } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import i18n from '@/i18n'
import { useWorkOrderForm } from '@/features/work-orders/use-work-order-form'

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('useWorkOrderForm — offer/lines coherence (AC-072)', () => {
  it('drops every selected product line when the linked offer changes', () => {
    const { result } = renderHook(
      () => useWorkOrderForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.form.setValue('quote_line_ids', [1, 2, 3])
    })
    expect(result.current.form.getValues('quote_line_ids')).toEqual([1, 2, 3])

    act(() => {
      result.current.handleQuoteChange()
    })

    expect(result.current.form.getValues('quote_line_ids')).toEqual([])
  })
})

describe('useWorkOrderForm — force close reason reset (D-4)', () => {
  it('clears the reason the moment "Chiusura forzata" turns off', () => {
    const { result } = renderHook(
      () => useWorkOrderForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.handleForceClosedChange(true)
      result.current.form.setValue('force_close_reason', 'Cliente insolvente')
    })
    expect(result.current.form.getValues('force_close_reason')).toBe('Cliente insolvente')

    act(() => {
      result.current.handleForceClosedChange(false)
    })

    expect(result.current.form.getValues('is_force_closed')).toBe(false)
    expect(result.current.form.getValues('force_close_reason')).toBeNull()
  })
})
