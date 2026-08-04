import { beforeAll, describe, expect, it } from 'vitest'
import type { FieldErrors } from 'react-hook-form'
import i18n from '@/i18n'
import { describeInvalidFields } from '@/features/request-management/request-work-invalid-fields'
import type { RequestWorkFormValues } from '@/features/request-management/request-work-schema'

/**
 * Spec 0080: the "Operatore" entry of the invalid-fields summary mirrors
 * `RequestAttributionSection`'s own label resolution — the request's resolved
 * G.A. level-2 label when present, otherwise today's string (AC-032).
 */

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const OPERATOR_ERROR: FieldErrors<RequestWorkFormValues> = {
  operator_id: { type: 'custom', message: '' },
}

describe('describeInvalidFields — Operatore label (spec 0080)', () => {
  it('AC-032: names the field "Operator" with no resolved manager_labels', () => {
    const fields = describeInvalidFields(OPERATOR_ERROR, [], undefined, i18n.t.bind(i18n))

    expect(fields).toEqual([i18n.t('requestManagement.workPanel.attribution.operator')])
  })

  it('AC-045: names the field with the resolved level-2 label instead', () => {
    const fields = describeInvalidFields(OPERATOR_ERROR, [], { '2': 'Consultant' }, i18n.t.bind(i18n))

    expect(fields).toEqual(['Consultant'])
  })
})
