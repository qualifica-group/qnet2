import { render } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { workOrderWorkflowColumnRenderers } from '@/features/work-order-workflows/column-renderers'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function renderCriteriaFields(value: unknown) {
  const params = { value } as unknown as ICellRendererParams
  const Cell = workOrderWorkflowColumnRenderers.criteria_fields
  return render(<>{Cell(params)}</>)
}

describe('CriteriaFieldsCell', () => {
  it('translates native entries to their label', () => {
    const { getByLabelText } = renderCriteriaFields([
      'workOrderWorkflows.criterionFields.business_function_id',
      'workOrderWorkflows.criterionFields.product_category_id',
    ])
    expect(getByLabelText('Business function, Product category')).toBeInTheDocument()
  })

  it('does not translate an entry outside the native prefix', () => {
    const { getByLabelText } = renderCriteriaFields(['workOrderWorkflows.detail.title'])
    expect(getByLabelText('workOrderWorkflows.detail.title')).toBeInTheDocument()
  })

  it('renders an em dash for an empty array', () => {
    const { getByText } = renderCriteriaFields([])
    expect(getByText('—')).toBeInTheDocument()
  })
})
