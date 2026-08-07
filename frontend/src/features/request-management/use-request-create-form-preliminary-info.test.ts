import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { act, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import {
  COMPLETE_ROW,
  TEST_SOURCE_ID,
  renderCreateForm,
} from '@/features/request-management/request-create-form-harness'

/**
 * User directive 2026-08-07: the create form collects the "Informazioni
 * aggiuntive" of the Offerta it is about to open, resolved live from the
 * product-line categories picked in the form itself
 * (`POST /request-management/form-context`).
 */

const createRequestMock = vi.fn()
const fetchRequestFormContextMock = vi.fn()
vi.mock('@/features/request-management/api', () => ({
  createRequest: (...args: unknown[]) => createRequestMock(...args),
  fetchRequestFormContext: (...args: unknown[]) => fetchRequestFormContextMock(...args),
}))

const TEXT_ATTRIBUTE = {
  id: 1,
  code: 'preferred_slot',
  name: 'Fascia oraria preferita',
  type: 'text',
  description: null,
  help_text: null,
  placeholder: null,
  icon: null,
  config: null,
  relation_target: null,
  is_required: false,
  sort_order: 0,
  options: [],
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createRequestMock.mockReset()
  fetchRequestFormContextMock.mockReset()
  fetchRequestFormContextMock.mockResolvedValue({
    applicable_attributes: [TEXT_ATTRIBUTE],
    attribute_layout: null,
  })
})

describe('useRequestCreateForm — Informazioni aggiuntive', () => {
  it('resolves the applicable set from the COMPLETE product lines only', async () => {
    const { result } = renderCreateForm(vi.fn())

    act(() => {
      result.current.form.setValue('product_lines', [{ business_function_id: 1, product_category_id: null }])
    })
    expect(fetchRequestFormContextMock).not.toHaveBeenCalled()

    act(() => {
      result.current.form.setValue('product_lines', [COMPLETE_ROW])
    })

    await waitFor(() => expect(fetchRequestFormContextMock).toHaveBeenCalledWith([COMPLETE_ROW]))
    await waitFor(() => expect(result.current.context.applicable_attributes).toHaveLength(1))
  })

  it('omits attribute_values entirely while the block is untouched', async () => {
    createRequestMock.mockResolvedValue({ id: 60 })
    const { result } = renderCreateForm(vi.fn())

    act(() => {
      result.current.form.setValue('registry_id', 10)
      result.current.form.setValue('product_lines', [COMPLETE_ROW])
      result.current.form.setValue('source_id', TEST_SOURCE_ID)
    })
    await waitFor(() => expect(result.current.context.applicable_attributes).toHaveLength(1))

    await act(async () => {
      await result.current.onSubmit()
    })

    expect(createRequestMock.mock.calls[0][0]).not.toHaveProperty('attribute_values')
  })

  it('sends the map narrowed to the applicable codes once one is filled in', async () => {
    createRequestMock.mockResolvedValue({ id: 61 })
    const { result } = renderCreateForm(vi.fn())

    act(() => {
      result.current.form.setValue('registry_id', 10)
      result.current.form.setValue('product_lines', [COMPLETE_ROW])
      result.current.form.setValue('source_id', TEST_SOURCE_ID)
    })
    await waitFor(() => expect(result.current.context.applicable_attributes).toHaveLength(1))

    act(() => {
      // `stale_code` belongs to a category the operator has since removed: RHF
      // never prunes the key, the payload must.
      result.current.form.setValue('attribute_values', { preferred_slot: 'mattina', stale_code: 'x' })
    })
    await act(async () => {
      await result.current.onSubmit()
    })

    expect(createRequestMock.mock.calls[0][0].attribute_values).toEqual({ preferred_slot: 'mattina' })
  })
})
