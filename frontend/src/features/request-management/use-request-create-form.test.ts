import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { act } from '@testing-library/react'
import i18n from '@/i18n'
import {
  COMPLETE_ROW,
  TEST_SOURCE_ID,
  completeIdentity,
  renderCreateForm,
} from '@/features/request-management/request-create-form-harness'

/**
 * Spec 0057 D-2/D-3/AC-016: the create form's non-render logic — the two
 * mutually-exclusive anagrafica branches (existing registry vs a brand-new
 * client), the mandatory product lines, and 422 mapping onto `registry_id`
 * (RHF) plus the `client_*`/`product_lines` blocks (banner, since those
 * sections are outside RHF — see the hook's own doc comment).
 */

const createRequestMock = vi.fn()
vi.mock('@/features/request-management/api', () => ({
  // The create form resolves its "Informazioni aggiuntive" from the picked
  // categories (user directive 2026-08-07): stubbed empty, this suite is not
  // about that block.
  fetchRequestFormContext: () => Promise.resolve({ applicable_attributes: [], attribute_layout: null }),
  createRequest: (...args: unknown[]) => createRequestMock(...args),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createRequestMock.mockReset()
})

describe('useRequestCreateForm', () => {
  it('blocks submit and surfaces a client banner when neither a registry nor a complete identity is provided', async () => {
    const onSuccess = vi.fn()
    const { result } = renderCreateForm(onSuccess)

    act(() => {
      result.current.form.setValue('product_lines', [COMPLETE_ROW])
      result.current.form.setValue('source_id', TEST_SOURCE_ID)
    })
    await act(async () => {
      await result.current.onSubmit()
    })

    expect(createRequestMock).not.toHaveBeenCalled()
    expect(result.current.clientBlockError).toMatch(/^Complete the client identity fields: /)
    expect(onSuccess).not.toHaveBeenCalled()
  })

  /**
   * User directive 2026-07-29: the form opens on ONE empty product-line row
   * (at least one is mandatory anyway), so the operator fills it in instead of
   * having to press "Add" first. An untouched row still blocks the submit —
   * it is incomplete, not absent.
   */
  it('opens on one empty product-line row', () => {
    const { result } = renderCreateForm(vi.fn())

    expect(result.current.form.getValues('product_lines')).toEqual([
      { business_function_id: null, product_category_id: null },
    ])
  })

  /** User directive 2026-07-29: the Fonte is mandatory, mirroring StoreRequestRequest's `required`. */
  it('blocks submit when no source is chosen', async () => {
    const onSuccess = vi.fn()
    const { result } = renderCreateForm(onSuccess)

    act(() => {
      result.current.form.setValue('registry_id', 10)
      result.current.form.setValue('product_lines', [COMPLETE_ROW])
    })

    await act(async () => {
      await result.current.onSubmit()
    })

    expect(createRequestMock).not.toHaveBeenCalled()
    expect(result.current.form.formState.errors.source_id).toBeTruthy()
  })

  /**
   * The team (spec 0097 D-1, replacing the single GA2 "Operatore" of the user
   * directive 2026-07-29) travels only once a slot is actually filled: an
   * actor without `request-management.assignOperator` never renders the block,
   * the endpoint rejects the key from them outright, and an untouched set of
   * empty cards is the server's own default to apply.
   */
  it('sends manager_slots once a slot is filled, and omits the key entirely while none is', async () => {
    createRequestMock.mockResolvedValue({ id: 45 })
    const { result } = renderCreateForm(vi.fn())

    act(() => {
      result.current.form.setValue('registry_id', 10)
      result.current.form.setValue('product_lines', [COMPLETE_ROW])
      result.current.form.setValue('source_id', TEST_SOURCE_ID)
    })
    await act(async () => {
      await result.current.onSubmit()
    })

    expect(createRequestMock.mock.calls[0][0]).not.toHaveProperty('manager_slots')

    act(() => {
      result.current.form.setValue('manager_slots', [null, 55, null, null])
    })
    await act(async () => {
      await result.current.onSubmit()
    })

    expect(createRequestMock.mock.calls[1][0]).toMatchObject({ manager_slots: [null, 55, null, null] })
    expect(createRequestMock.mock.calls[1][0]).not.toHaveProperty('operator_id')
  })

  /**
   * Sede operativa (user directive 2026-07-31): the same field the work panel
   * edits. Like `manager_slots` it travels only when picked — on create there is
   * no persisted value a null could clear.
   */
  it('sends operational_site_id when set, and omits the key entirely when it is not', async () => {
    createRequestMock.mockResolvedValue({ id: 46 })
    const { result } = renderCreateForm(vi.fn())

    act(() => {
      result.current.form.setValue('registry_id', 10)
      result.current.form.setValue('product_lines', [COMPLETE_ROW])
      result.current.form.setValue('source_id', TEST_SOURCE_ID)
    })
    await act(async () => {
      await result.current.onSubmit()
    })

    expect(createRequestMock.mock.calls[0][0]).not.toHaveProperty('operational_site_id')

    act(() => {
      result.current.form.setValue('operational_site_id', 12)
    })
    await act(async () => {
      await result.current.onSubmit()
    })

    expect(createRequestMock.mock.calls[1][0]).toMatchObject({ operational_site_id: 12 })
  })

  /**
   * Spec 0097 rev-2 D-9/AC-013: the Supervisore is written from this channel
   * again. Sent only when picked, like the Sede above: with no `permissions`
   * envelope to gate the field against, an untouched null would earn a 422
   * from any actor who may not write it. AC-014: it travels ALONE — the team
   * slots are untouched by it.
   */
  it('sends supervisor_id when picked, and omits the key entirely when it is not', async () => {
    createRequestMock.mockResolvedValue({ id: 47 })
    const { result } = renderCreateForm(vi.fn())

    act(() => {
      result.current.form.setValue('registry_id', 10)
      result.current.form.setValue('product_lines', [COMPLETE_ROW])
      result.current.form.setValue('source_id', TEST_SOURCE_ID)
    })
    await act(async () => {
      await result.current.onSubmit()
    })

    expect(createRequestMock.mock.calls[0][0]).not.toHaveProperty('supervisor_id')

    act(() => {
      result.current.form.setValue('supervisor_id', 33)
    })
    await act(async () => {
      await result.current.onSubmit()
    })

    expect(createRequestMock.mock.calls[1][0]).toMatchObject({ supervisor_id: 33 })
    expect(createRequestMock.mock.calls[1][0]).not.toHaveProperty('manager_slots')
  })

  it('blocks submit when product_lines is empty (D-3)', async () => {
    const onSuccess = vi.fn()
    const { result } = renderCreateForm(onSuccess)

    await act(async () => {
      await result.current.onSubmit()
    })

    expect(createRequestMock).not.toHaveBeenCalled()
    expect(result.current.form.formState.errors.product_lines).toBeTruthy()
  })

  it('submits the registry branch, dropping the client buffer entirely (D-2)', async () => {
    createRequestMock.mockResolvedValue({ id: 42 })
    const onSuccess = vi.fn()
    const { result } = renderCreateForm(onSuccess)

    act(() => {
      result.current.form.setValue('registry_id', 10)
      result.current.form.setValue('product_lines', [COMPLETE_ROW])
      result.current.form.setValue('source_id', TEST_SOURCE_ID)
      // Even if something was typed before picking the registry, the registry
      // branch must win: the buffer is never sent alongside `registry_id`.
      result.current.setIdentityDraft(completeIdentity())
    })

    await act(async () => {
      await result.current.onSubmit()
    })

    expect(createRequestMock).toHaveBeenCalledWith({
      registry_id: 10,
      product_lines: [COMPLETE_ROW],
      // Attribution slots ride along with either branch; the Fonte is
      // mandatory (user directive 2026-07-29), the Segnalatore empty by
      // default, and `rewards`/`manager_slots` omitted entirely until set.
      source_id: TEST_SOURCE_ID,
      reporter_id: null,
    })
    expect(onSuccess).toHaveBeenCalledWith(42)
  })

  it('sends the initial attribution (source/reporter/rewards) when set (user directive 2026-07-24)', async () => {
    createRequestMock.mockResolvedValue({ id: 44 })
    const onSuccess = vi.fn()
    const { result } = renderCreateForm(onSuccess)

    act(() => {
      result.current.form.setValue('registry_id', 10)
      result.current.form.setValue('product_lines', [COMPLETE_ROW])
      result.current.form.setValue('source_id', TEST_SOURCE_ID)
      result.current.form.setValue('reporter_id', 3)
      result.current.form.setValue('rewards', [{ reward_type_id: 5 }])
    })

    await act(async () => {
      await result.current.onSubmit()
    })

    expect(createRequestMock).toHaveBeenCalledWith({
      registry_id: 10,
      product_lines: [COMPLETE_ROW],
      source_id: TEST_SOURCE_ID,
      reporter_id: 3,
      rewards: [{ reward_type_id: 5 }],
    })
    expect(onSuccess).toHaveBeenCalledWith(44)
  })

  /**
   * "Prodotti di interesse" at creation (user directive 2026-07-31): optional,
   * so the key travels ONLY when at least one is picked — an empty array is a
   * no-op the endpoint need not process.
   */
  it('sends the products of interest only once at least one is picked', async () => {
    createRequestMock.mockResolvedValue({ id: 45 })
    const { result } = renderCreateForm(vi.fn())

    act(() => {
      result.current.form.setValue('registry_id', 10)
      result.current.form.setValue('product_lines', [COMPLETE_ROW])
      result.current.form.setValue('source_id', TEST_SOURCE_ID)
    })
    await act(async () => {
      await result.current.onSubmit()
    })

    expect(createRequestMock.mock.calls[0][0]).not.toHaveProperty('products_of_interest')

    act(() => {
      result.current.form.setValue('products_of_interest', [700, 701])
    })
    await act(async () => {
      await result.current.onSubmit()
    })

    expect(createRequestMock.mock.calls[1][0]).toMatchObject({ products_of_interest: [700, 701] })
  })

  /**
   * "Linee dell'offerta" at creation (user directive 2026-08-07): same
   * "only when filled in" rule, and the wire row is the Offerte contract's own
   * `QuoteLineInput` — never the form's nullable-per-field shape, and never a
   * `commissions` block (prohibited on this channel).
   */
  it('sends the offer lines only once a row is filled in, in the contract shape', async () => {
    createRequestMock.mockResolvedValue({ id: 46 })
    const { result } = renderCreateForm(vi.fn())

    act(() => {
      result.current.form.setValue('registry_id', 10)
      result.current.form.setValue('product_lines', [COMPLETE_ROW])
      result.current.form.setValue('source_id', TEST_SOURCE_ID)
    })
    await act(async () => {
      await result.current.onSubmit()
    })

    expect(createRequestMock.mock.calls[0][0]).not.toHaveProperty('offer_lines')

    act(() => {
      result.current.form.setValue('offer_lines', [
        { product_id: 900, quantity: 2, unit_price: 50, vat_rate_id: null },
      ])
    })
    await act(async () => {
      await result.current.onSubmit()
    })

    expect(createRequestMock.mock.calls[1][0]).toMatchObject({
      offer_lines: [{ product_id: 900, quantity: 2, unit_price: 50, vat_rate_id: null, sort_order: 0 }],
    })
    expect(createRequestMock.mock.calls[1][0].offer_lines?.[0]).not.toHaveProperty('commissions')
  })

  /** An incomplete row blocks the submit, exactly as it does in the Offerte form. */
  it('refuses to submit an offer row without a product', async () => {
    createRequestMock.mockResolvedValue({ id: 47 })
    const { result } = renderCreateForm(vi.fn())

    act(() => {
      result.current.form.setValue('registry_id', 10)
      result.current.form.setValue('product_lines', [COMPLETE_ROW])
      result.current.form.setValue('source_id', TEST_SOURCE_ID)
      result.current.form.setValue('offer_lines', [
        { product_id: null, quantity: 1, unit_price: 10, vat_rate_id: null },
      ])
    })
    await act(async () => {
      await result.current.onSubmit()
    })

    expect(createRequestMock).not.toHaveBeenCalled()
  })

  /** The coherence 422 lands on the picker itself, not on the generic banner. */
  it('maps the product-category coherence 422 onto the products_of_interest field', async () => {
    createRequestMock.mockRejectedValue({
      isAxiosError: true,
      response: {
        status: 422,
        data: {
          errors: {
            products_of_interest: ['These products of interest belong to a product category the request does not carry: "Fibra" (Connettivita).'],
          },
        },
      },
    })
    const { result } = renderCreateForm(vi.fn())

    act(() => {
      result.current.form.setValue('registry_id', 10)
      result.current.form.setValue('product_lines', [COMPLETE_ROW])
      result.current.form.setValue('source_id', TEST_SOURCE_ID)
      result.current.form.setValue('products_of_interest', [700])
    })
    await act(async () => {
      await result.current.onSubmit()
    })

    expect(result.current.form.formState.errors.products_of_interest?.message).toContain(
      'does not carry',
    )
    expect(result.current.serverError).toBeNull()
  })

  it('collects a rewards D-3 422 (reward without a reporter) into the rewards banner', async () => {
    createRequestMock.mockRejectedValue({
      isAxiosError: true,
      response: {
        status: 422,
        data: { errors: { rewards: ['Rewards require the opportunity to have a reporter (Segnalatore).'] } },
      },
    })
    const onSuccess = vi.fn()
    const { result } = renderCreateForm(onSuccess)

    act(() => {
      result.current.form.setValue('registry_id', 10)
      result.current.form.setValue('product_lines', [COMPLETE_ROW])
      result.current.form.setValue('source_id', TEST_SOURCE_ID)
      result.current.form.setValue('rewards', [{ reward_type_id: 5 }])
    })

    await act(async () => {
      await result.current.onSubmit()
    })

    expect(result.current.rewardsError).toBe('Rewards require the opportunity to have a reporter (Segnalatore).')
    expect(onSuccess).not.toHaveBeenCalled()
  })

  it('submits the new-client branch with the buffered identity/contacts/address', async () => {
    createRequestMock.mockResolvedValue({ id: 43 })
    const onSuccess = vi.fn()
    const { result } = renderCreateForm(onSuccess)

    act(() => {
      result.current.setIdentityDraft(completeIdentity())
      result.current.form.setValue('product_lines', [COMPLETE_ROW])
      result.current.form.setValue('source_id', TEST_SOURCE_ID)
    })

    await act(async () => {
      await result.current.onSubmit()
    })

    expect(createRequestMock).toHaveBeenCalledWith(
      expect.objectContaining({
        client_identity: expect.objectContaining({ type: 'individual', first_name: 'Mario', last_name: 'Rossi' }),
        client_contacts: [],
        product_lines: [COMPLETE_ROW],
      }),
    )
    expect(onSuccess).toHaveBeenCalledWith(43)
  })

  it('maps a 422 onto registry_id (RHF) and collects client_identity.* into the client banner (AC-016)', async () => {
    createRequestMock.mockRejectedValue({
      isAxiosError: true,
      response: {
        status: 422,
        data: {
          errors: {
            registry_id: ['The selected registry id is invalid.'],
            'client_identity.first_name': ['The client identity first name field is required.'],
          },
        },
      },
    })
    const onSuccess = vi.fn()
    const { result } = renderCreateForm(onSuccess)

    act(() => {
      result.current.form.setValue('registry_id', 999)
      result.current.form.setValue('product_lines', [COMPLETE_ROW])
      result.current.form.setValue('source_id', TEST_SOURCE_ID)
    })

    await act(async () => {
      await result.current.onSubmit()
    })

    expect(result.current.form.formState.errors.registry_id?.message).toBe('The selected registry id is invalid.')
    expect(result.current.clientBlockError).toBe('The client identity first name field is required.')
    expect(onSuccess).not.toHaveBeenCalled()
  })

  it('maps a 422 on product_lines.* into the product-lines banner', async () => {
    createRequestMock.mockRejectedValue({
      isAxiosError: true,
      response: {
        status: 422,
        data: { errors: { 'product_lines.0.product_category_id': ['That category does not belong to the chosen function.'] } },
      },
    })
    const onSuccess = vi.fn()
    const { result } = renderCreateForm(onSuccess)

    act(() => {
      result.current.form.setValue('registry_id', 10)
      result.current.form.setValue('product_lines', [COMPLETE_ROW])
      result.current.form.setValue('source_id', TEST_SOURCE_ID)
    })

    await act(async () => {
      await result.current.onSubmit()
    })

    expect(result.current.productLinesError).toBe('That category does not belong to the chosen function.')
    expect(onSuccess).not.toHaveBeenCalled()
  })
})
