import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AttributeValueDisplay, type DisplayableAttribute } from '@/features/attributes/attribute-value-display'

const fetchForSelectMock = vi.fn()

vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>('@/features/for-select/api')
  return {
    ...actual,
    fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params),
  }
})

const CLASS_SELECT: DisplayableAttribute = {
  id: 1,
  code: 'gol_corso',
  name: 'Nome Corso / Ed.',
  type: 'relation',
  config: null,
  sort_order: 0,
  options: [],
  relation_target: { entity_type: 'formalab-classes', cardinality: 'one', for_select_resource: 'integrations/formalab/classes' },
}

function renderValue(attribute: DisplayableAttribute, value: unknown) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <AttributeValueDisplay attribute={attribute} value={value} />
    </QueryClientProvider>,
  )
}

describe('AttributeValueDisplay', () => {
  beforeEach(() => {
    fetchForSelectMock.mockReset()
  })

  it('shows a relation value by the label its for-select resolves, not the bare id', async () => {
    fetchForSelectMock.mockResolvedValue({
      items: [{ id: 2477, label: 'GOL-12 - Saldatura' }],
      pagination: { offset: 0, limit: 1, total: 1, total_pages: 1 },
      export_link: null,
    })

    renderValue(CLASS_SELECT, 2477)

    expect(await screen.findByText('GOL-12 - Saldatura')).toBeInTheDocument()
    expect(screen.queryByText('2477')).not.toBeInTheDocument()
    expect(fetchForSelectMock).toHaveBeenCalledWith('integrations/formalab/classes', expect.objectContaining({ ids: [2477] }))
  })

  it('keeps the plain value for a relation without a for-select resource', () => {
    renderValue({ ...CLASS_SELECT, relation_target: null }, 7)

    expect(screen.getByText('7')).toBeInTheDocument()
    expect(fetchForSelectMock).not.toHaveBeenCalled()
  })
})
