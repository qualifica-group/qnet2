import { vi } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, type AxiosResponse } from 'axios'
import { WorkOrderContractDataSection } from '@/features/work-order-contract-data/work-order-contract-data-section'

export const STATUS_ITEMS = [
  { id: 3, label: 'Pagato', name: 'Pagato', color: 'green', allows_delivery: true },
  { id: 4, label: 'Da pagare', name: 'Da pagare', color: 'red', allows_delivery: false },
]

export function renderSection(canManage: boolean, showRegistry = false) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <WorkOrderContractDataSection workOrderId={4} canManage={canManage} showRegistry={showRegistry} />
    </QueryClientProvider>,
  )
}

export function unprocessable(errors: Record<string, string[]>): AxiosError {
  return new AxiosError('Unprocessable', '422', undefined, undefined, {
    status: 422,
    data: { message: 'invalid', errors },
  } as AxiosResponse)
}

/** The block of fields of a line (spec 0403), found through its product code. */
export function lineGroup(code: string): HTMLElement {
  return screen.getByRole('group', { name: new RegExp(code) })
}

/** The fields of a block in order: label -> displayed text. */
export function lineFields(group: HTMLElement): Record<string, string> {
  return Object.fromEntries(
    within(group).getAllByRole('term').map((term) => [term.textContent ?? '', term.nextElementSibling?.textContent ?? '']),
  )
}

/** jsdom has no `:focus-visible`: report it as matching, as a browser does after a keyboard focus. */
export function forceFocusVisible(): void {
  const matches = Element.prototype.matches
  vi.spyOn(Element.prototype, 'matches').mockImplementation(function (this: Element, selector: string) {
    return selector === ':focus-visible' || matches.call(this, selector)
  })
}
