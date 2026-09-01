import { render } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { quoteWorkflowColumnRenderers } from '@/features/quote-workflows/column-renderers'

/**
 * Spec 0047 amendment 2026-07-27, AC-037/AC-038: `criteria_fields` is a MIXED
 * array (native i18n keys + literal custom-field labels). `CriteriaFieldsCell`
 * must translate only the entries carrying the
 * `quoteWorkflows.criterionFields.` prefix; everything else — including
 * a custom label that happens to collide with some unrelated real i18n key —
 * renders as-is.
 */

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function renderCriteriaFields(value: unknown) {
  const params = { value } as unknown as ICellRendererParams
  const Cell = quoteWorkflowColumnRenderers.criteria_fields
  return render(<>{Cell(params)}</>)
}

describe('CriteriaFieldsCell', () => {
  it('translates a native entry and renders a custom entry literally', () => {
    const { getByLabelText } = renderCriteriaFields([
      'quoteWorkflows.criterionFields.source_id',
      'Preferred supplier',
    ])
    expect(getByLabelText('Source, Preferred supplier')).toBeInTheDocument()
  })

  it('does not translate a custom label that coincides with an existing, unrelated i18n key', () => {
    // Real key that exists in the bundle but carries none of the native
    // criterion-field prefix — proves the check is the prefix, not "is this a
    // resolvable key" (which would wrongly translate this coincidence).
    const { getByLabelText } = renderCriteriaFields(['quoteWorkflows.detail.title'])
    expect(getByLabelText('quoteWorkflows.detail.title')).toBeInTheDocument()
  })

  it('renders an em dash for an empty array', () => {
    const { getByText } = renderCriteriaFields([])
    expect(getByText('—')).toBeInTheDocument()
  })
})
