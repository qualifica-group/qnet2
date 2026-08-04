import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { act, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import {
  COMPLETE_ROW,
  EMPTY_FORM_CONTEXT,
  TEST_SOURCE_ID,
  anApplicableAttribute,
  renderCreateForm,
} from '@/features/request-management/request-create-form-harness'
import type { UseFormReturn } from 'react-hook-form'
import type { RequestCreateFormValues } from '@/features/request-management/request-create-schema'
import type { RequestFormContext, RequestFormContextPayload } from '@/features/request-management/types'

/**
 * User directive 2026-07-31 ("la scheda di creazione il piu' simile possibile a
 * quella di gestione"): the five operative fields the work panel edits are now
 * collected at creation too. Each travels ONLY when it carries something — on
 * create there is no persisted value an empty key could clear — and the two
 * that depend on the chosen criteria (working status, dynamic fields) are
 * resolved server-side by `POST /request-management/form-context`.
 *
 * Split from `use-request-create-form.test.ts` when it crossed the 500-line
 * hard limit (engineering.md §6); the shared fixtures live in
 * `request-create-form-harness.ts`.
 */

const createRequestMock = vi.fn()
const fetchRequestFormContextMock = vi.fn<(payload: RequestFormContextPayload) => Promise<RequestFormContext>>(
  async () => EMPTY_FORM_CONTEXT,
)
vi.mock('@/features/request-management/api', () => ({
  createRequest: (...args: unknown[]) => createRequestMock(...args),
  fetchRequestFormContext: (payload: RequestFormContextPayload) => fetchRequestFormContextMock(payload),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createRequestMock.mockReset()
  fetchRequestFormContextMock.mockReset()
  fetchRequestFormContextMock.mockResolvedValue(EMPTY_FORM_CONTEXT)
})

/** Fills the anagrafica/classification minimum every submitting case needs. */
function fillMandatory(form: UseFormReturn<RequestCreateFormValues>) {
  form.setValue('registry_id', 10)
  form.setValue('product_lines', [COMPLETE_ROW])
  form.setValue('source_id', TEST_SOURCE_ID)
}

describe('useRequestCreateForm — operative fields', () => {
  it('omits every operative key when none of them was filled in', async () => {
    createRequestMock.mockResolvedValue({ id: 50 })
    const { result } = renderCreateForm(vi.fn())

    act(() => fillMandatory(result.current.form))
    await act(async () => {
      await result.current.onSubmit()
    })

    const payload = createRequestMock.mock.calls[0][0]
    expect(payload).not.toHaveProperty('opportunity_workflow_status_id')
    expect(payload).not.toHaveProperty('note')
    expect(payload).not.toHaveProperty('next_callback_at')
    expect(payload).not.toHaveProperty('general_notes')
    expect(payload).not.toHaveProperty('attribute_values')
  })

  it('sends next_callback_at and the trimmed general_notes when filled', async () => {
    createRequestMock.mockResolvedValue({ id: 51 })
    const { result } = renderCreateForm(vi.fn())

    act(() => {
      fillMandatory(result.current.form)
      result.current.form.setValue('next_callback_at', '2026-09-01T10:30')
      result.current.form.setValue('general_notes', '  Richiama a settembre.  ')
    })
    await act(async () => {
      await result.current.onSubmit()
    })

    expect(createRequestMock.mock.calls[0][0]).toMatchObject({
      next_callback_at: '2026-09-01T10:30',
      general_notes: 'Richiama a settembre.',
    })
  })

  it('resolves the working statuses from the chosen criteria and sends the picked one', async () => {
    fetchRequestFormContextMock.mockResolvedValue({
      ...EMPTY_FORM_CONTEXT,
      workflow_statuses: [
        { id: 3, name: 'Validata', description: null, color: null, system_key: null, requires_note: false },
      ],
    })
    createRequestMock.mockResolvedValue({ id: 52 })
    const { result } = renderCreateForm(vi.fn())

    act(() => fillMandatory(result.current.form))
    await waitFor(() => expect(result.current.context.workflow_statuses).toHaveLength(1))

    act(() => {
      result.current.form.setValue('opportunity_workflow_status_id', 3)
    })
    await act(async () => {
      await result.current.onSubmit()
    })

    expect(fetchRequestFormContextMock).toHaveBeenCalledWith({
      source_id: TEST_SOURCE_ID,
      product_lines: [COMPLETE_ROW],
    })
    expect(createRequestMock.mock.calls[0][0]).toMatchObject({ opportunity_workflow_status_id: 3 })
  })

  /**
   * User directive 2026-08-04: the select must never sit empty once the
   * criteria enable it — the set's first row (ordered by `sort_order`
   * server-side) is picked for the operator.
   */
  it('preseleziona il primo stato risolto e lo invia senza che venga scelto', async () => {
    fetchRequestFormContextMock.mockResolvedValue({
      ...EMPTY_FORM_CONTEXT,
      workflow_statuses: [
        { id: 6, name: 'Aperta', description: null, color: null, system_key: 'open', requires_note: false },
        { id: 7, name: 'Validata', description: null, color: null, system_key: null, requires_note: false },
      ],
    })
    createRequestMock.mockResolvedValue({ id: 55 })
    const { result } = renderCreateForm(vi.fn())

    act(() => fillMandatory(result.current.form))
    await waitFor(() =>
      expect(result.current.form.getValues('opportunity_workflow_status_id')).toBe(6),
    )

    await act(async () => {
      await result.current.onSubmit()
    })

    expect(createRequestMock.mock.calls[0][0]).toMatchObject({ opportunity_workflow_status_id: 6 })
  })

  /** A pick that left the set falls back to the NEW set's default, not to empty. */
  it('riporta al primo stato del nuovo set una scelta che ne e uscita', async () => {
    fetchRequestFormContextMock.mockResolvedValue({
      ...EMPTY_FORM_CONTEXT,
      workflow_statuses: [
        { id: 3, name: 'Validata', description: null, color: null, system_key: null, requires_note: false },
      ],
    })
    const { result } = renderCreateForm(vi.fn())

    act(() => fillMandatory(result.current.form))
    await waitFor(() => expect(result.current.form.getValues('opportunity_workflow_status_id')).toBe(3))

    fetchRequestFormContextMock.mockResolvedValue({
      ...EMPTY_FORM_CONTEXT,
      workflow_statuses: [
        { id: 8, name: 'Bozza', description: null, color: null, system_key: 'open', requires_note: false },
      ],
    })
    act(() => {
      result.current.form.setValue('product_lines', [{ business_function_id: 9, product_category_id: 9 }])
    })

    await waitFor(() => expect(result.current.form.getValues('opportunity_workflow_status_id')).toBe(8))
  })

  /** Spec 0054 D-5, mirrored client-side so the submit never leaves for a 422 it can predict. */
  it('blocks the submit when the picked status requires a note, and goes through once written', async () => {
    fetchRequestFormContextMock.mockResolvedValue({
      ...EMPTY_FORM_CONTEXT,
      workflow_statuses: [
        { id: 4, name: 'Sospesa', description: null, color: null, system_key: null, requires_note: true },
      ],
    })
    const { result } = renderCreateForm(vi.fn())

    act(() => fillMandatory(result.current.form))
    await waitFor(() => expect(result.current.context.workflow_statuses).toHaveLength(1))

    act(() => {
      result.current.form.setValue('opportunity_workflow_status_id', 4)
    })
    await act(async () => {
      await result.current.onSubmit()
    })

    expect(createRequestMock).not.toHaveBeenCalled()
    expect(result.current.form.formState.errors.note).toBeTruthy()

    createRequestMock.mockResolvedValue({ id: 53 })
    act(() => {
      result.current.form.setValue('note', 'Il cliente ha chiesto di richiamare.')
    })
    await act(async () => {
      await result.current.onSubmit()
    })

    expect(createRequestMock.mock.calls[0][0]).toMatchObject({
      opportunity_workflow_status_id: 4,
      note: 'Il cliente ha chiesto di richiamare.',
    })
  })

  /**
   * The same gate the server applies: `is_required` is checked on SUBMITTED
   * codes only, so a request whose category declares dynamic fields stays
   * creatable while the operator still knows nothing about them.
   */
  it('sends attribute_values only once at least one applicable code carries a value', async () => {
    fetchRequestFormContextMock.mockResolvedValue({
      ...EMPTY_FORM_CONTEXT,
      applicable_attributes: [anApplicableAttribute('material')],
    })
    createRequestMock.mockResolvedValue({ id: 54 })
    const { result } = renderCreateForm(vi.fn())

    act(() => fillMandatory(result.current.form))
    await waitFor(() => expect(result.current.context.applicable_attributes).toHaveLength(1))

    await act(async () => {
      await result.current.onSubmit()
    })
    expect(createRequestMock.mock.calls[0][0]).not.toHaveProperty('attribute_values')

    act(() => {
      result.current.form.setValue('attribute_values', { material: 'steel' })
    })
    await act(async () => {
      await result.current.onSubmit()
    })

    expect(createRequestMock.mock.calls[1][0]).toMatchObject({ attribute_values: { material: 'steel' } })
  })

  /** No complete product line means no criteria: there is nothing to resolve and no round trip to make. */
  it('does not query the form context until a product line is complete', async () => {
    const { result } = renderCreateForm(vi.fn())

    await waitFor(() => expect(result.current.context.workflow_statuses).toHaveLength(0))
    expect(fetchRequestFormContextMock).not.toHaveBeenCalled()

    act(() => fillMandatory(result.current.form))
    await waitFor(() => expect(fetchRequestFormContextMock).toHaveBeenCalledTimes(1))
  })

  /** A status the new criteria no longer resolve would be 422'd server-side. */
  it('clears a picked status that left the resolved set', async () => {
    fetchRequestFormContextMock.mockResolvedValue({
      ...EMPTY_FORM_CONTEXT,
      workflow_statuses: [
        { id: 3, name: 'Validata', description: null, color: null, system_key: null, requires_note: false },
      ],
    })
    const { result } = renderCreateForm(vi.fn())

    act(() => fillMandatory(result.current.form))
    await waitFor(() => expect(result.current.context.workflow_statuses).toHaveLength(1))

    act(() => {
      result.current.form.setValue('opportunity_workflow_status_id', 3)
    })

    fetchRequestFormContextMock.mockResolvedValue(EMPTY_FORM_CONTEXT)
    act(() => {
      result.current.form.setValue('product_lines', [{ business_function_id: 9, product_category_id: 9 }])
    })

    await waitFor(() => expect(result.current.form.getValues('opportunity_workflow_status_id')).toBeNull())
  })
})
