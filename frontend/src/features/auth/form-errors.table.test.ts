import { describe, expect, it, vi } from 'vitest'
import { AxiosError, type AxiosResponse } from 'axios'
import { applyServerValidationErrors } from '@/features/auth/form-errors'

/** Spec 0180 AC-019: nested table 422 keys reach the cell through the shared mapper (work-orders and every other form). */

function unprocessable(errors: Record<string, string[]>): AxiosError {
  const error = new AxiosError('422')
  error.response = { status: 422, data: { errors } } as AxiosResponse
  return error
}

describe('applyServerValidationErrors with table values', () => {
  it('maps attribute_values and custom_fields nested keys next to the listed paths', () => {
    const setError = vi.fn()
    const handled = applyServerValidationErrors(
      unprocessable({
        'title': ['bad title'],
        'attribute_values.audits.rows.1.audit_date': ['bad date'],
        'attribute_values.audits.rows': ['too many'],
        'custom_fields.audits.rows.0.inspector': ['bad inspector'],
      }),
      setError,
      ['title'] as never,
    )

    expect(handled).toBe(true)
    const calls = setError.mock.calls.map(([path, error]) => [path, error.message])
    expect(calls).toEqual(
      expect.arrayContaining([
        ['attribute_values.audits.rows.1.audit_date', 'bad date'],
        ['attribute_values.audits.rows', 'too many'],
        ['custom_fields.audits.rows.0.inspector', 'bad inspector'],
        ['title', 'bad title'],
      ]),
    )
    expect(calls).toHaveLength(4)
  })
})
