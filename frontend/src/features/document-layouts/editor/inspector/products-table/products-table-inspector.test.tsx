import { useState } from 'react'
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { I18nextProvider } from 'react-i18next'
import i18n from '@/i18n'
import { documentLayoutsEditorEn } from '@/features/document-layouts/editor/editor-i18n-fixture'
import { ProductsTableInspector } from '@/features/document-layouts/editor/inspector/products-table/products-table-inspector'
import { createDefaultProductsTableBlock } from '@/features/document-layouts/layout-config-defaults'
import type { ProductsTableBlock } from '@/features/document-layouts/layout-config'

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { documentLayouts: documentLayoutsEditorEn }, true, true)
})

const ROW_HEIGHT = 32

/** Same jsdom rect stub as `sortable-list.test.tsx`: keyboard reordering needs a non-zero layout to pick a direction. */
function mockRowRects() {
  vi.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockImplementation(function (this: HTMLElement) {
    const rows = Array.from(document.querySelectorAll('li'))
    const index = rows.indexOf(this as HTMLLIElement)
    const top = index === -1 ? 0 : index * ROW_HEIGHT
    return { width: 280, height: ROW_HEIGHT, top, bottom: top + ROW_HEIGHT, left: 0, right: 280, x: 0, y: top, toJSON: () => ({}) } as DOMRect
  })
}

async function flushSensorAttach() {
  await new Promise((resolve) => setTimeout(resolve, 0))
}

function Harness({ onBlockChange }: { onBlockChange: (block: ProductsTableBlock) => void }) {
  const [block, setBlock] = useState<ProductsTableBlock>(() => createDefaultProductsTableBlock('pt1'))
  function handleChange(next: ProductsTableBlock) {
    setBlock(next)
    onBlockChange(next)
  }
  return <ProductsTableInspector block={block} onChange={handleChange} disabled={false} />
}

function renderHarness() {
  const onBlockChange = vi.fn()
  render(
    <I18nextProvider i18n={i18n}>
      <Harness onBlockChange={onBlockChange} />
    </I18nextProvider>,
  )
  return { onBlockChange }
}

/** Spec 0069 AC-123: choose/reorder/rename columns from the allow-list; no discount column ever offered (D-4). */
describe('ProductsTableInspector (AC-123)', () => {
  beforeEach(() => mockRowRects())
  afterEach(() => vi.restoreAllMocks())

  it('offers every allow-listed column key and never a discount key', () => {
    renderHarness()
    fireEvent.click(screen.getByRole('button', { name: 'Add column' }))

    expect(screen.getByRole('checkbox', { name: 'Code' })).toBeInTheDocument()
    expect(screen.getByRole('checkbox', { name: 'Unit price' })).toBeInTheDocument()
    expect(screen.getByRole('checkbox', { name: 'Total amount' })).toBeInTheDocument()
    expect(screen.queryByText(/discount/i)).not.toBeInTheDocument()
  })

  it('adds a column, lets the user pick keys and rename the label', () => {
    const { onBlockChange } = renderHarness()

    fireEvent.click(screen.getByRole('button', { name: 'Add column' }))
    fireEvent.change(screen.getByPlaceholderText('Column label'), { target: { value: 'Product' } })
    fireEvent.click(screen.getByRole('checkbox', { name: 'Code' }))
    fireEvent.click(screen.getByRole('checkbox', { name: 'Name' }))

    const lastCall = onBlockChange.mock.calls.at(-1)?.[0] as ProductsTableBlock
    expect(lastCall.columns).toHaveLength(1)
    expect(lastCall.columns[0].label).toBe('Product')
    expect(lastCall.columns[0].lines[0].keys).toEqual(['code', 'name'])
  })

  it('removes a column', () => {
    const { onBlockChange } = renderHarness()

    fireEvent.click(screen.getByRole('button', { name: 'Add column' }))
    fireEvent.click(screen.getByRole('button', { name: 'Remove column' }))

    const lastCall = onBlockChange.mock.calls.at(-1)?.[0] as ProductsTableBlock
    expect(lastCall.columns).toHaveLength(0)
  })

  it('reorders two columns via the drag handle keyboard interaction', async () => {
    const { onBlockChange } = renderHarness()

    fireEvent.click(screen.getByRole('button', { name: 'Add column' }))
    fireEvent.change(screen.getByPlaceholderText('Column label'), { target: { value: 'First' } })
    fireEvent.click(screen.getByRole('button', { name: 'Add column' }))
    const labels = screen.getAllByPlaceholderText('Column label')
    fireEvent.change(labels[1], { target: { value: 'Second' } })

    const [firstHandle] = screen.getAllByRole('button', { name: 'Reorder block' })
    firstHandle.focus()
    fireEvent.keyDown(firstHandle, { code: 'Space' })
    await flushSensorAttach()
    fireEvent.keyDown(document, { code: 'ArrowDown' })
    fireEvent.keyDown(document, { code: 'Space' })

    const lastCall = onBlockChange.mock.calls.at(-1)?.[0] as ProductsTableBlock
    expect(lastCall.columns.map((column) => column.label)).toEqual(['Second', 'First'])
  })

  it('changes the source', () => {
    const { onBlockChange } = renderHarness()

    fireEvent.click(screen.getByRole('combobox', { name: 'Source' }))
    fireEvent.click(screen.getByRole('option', { name: 'Cost lines' }))

    expect(onBlockChange).toHaveBeenLastCalledWith(expect.objectContaining({ source: 'cost_lines' }))
  })
})
