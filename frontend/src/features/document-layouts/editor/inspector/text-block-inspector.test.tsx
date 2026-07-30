import { useState } from 'react'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { I18nextProvider } from 'react-i18next'
import i18n from '@/i18n'
import { documentLayoutsEditorEn } from '@/features/document-layouts/editor/editor-i18n-fixture'
import { TextBlockInspector } from '@/features/document-layouts/editor/inspector/text-block-inspector'
import { VariablePicker } from '@/features/document-layouts/editor/variables/variable-picker'
import { createDefaultTextBlock } from '@/features/document-layouts/layout-config-defaults'
import type { TextBlock } from '@/features/document-layouts/layout-config'
import type { DocumentLayoutVariablesCatalog } from '@/features/document-layouts/variables-api'

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { documentLayouts: documentLayoutsEditorEn }, true, true)
})

const catalog: DocumentLayoutVariablesCatalog = {
  module: 'quotes',
  categories: [
    {
      key: 'quote',
      label: 'Quote',
      variables: [{ variable: '{quote.code}', label: 'Quote code', type: 'string', example: 'QT-001' }],
    },
    {
      key: 'client',
      label: 'Client',
      variables: [{ variable: '{client.name}', label: 'Client name', type: 'string', example: 'Acme' }],
    },
  ],
}

/**
 * Wires `TextBlockInspector` and `VariablePicker` the same way
 * `DocumentLayoutEditor` does: the last-focused run registers its
 * `insertAtCaret` as the picker's active target.
 */
function Harness({ onBlockChange }: { onBlockChange: (block: TextBlock) => void }) {
  const [block, setBlock] = useState<TextBlock>(() => createDefaultTextBlock('b1'))
  const [activeInsert, setActiveInsert] = useState<((token: string) => void) | null>(null)

  function handleChange(next: TextBlock) {
    setBlock(next)
    onBlockChange(next)
  }

  function handleActivate(insert: (token: string) => void) {
    setActiveInsert(() => insert)
  }

  return (
    <div>
      <TextBlockInspector block={block} onChange={handleChange} onActivate={handleActivate} disabled={false} />
      <VariablePicker catalog={catalog} isLoading={false} disabled={!activeInsert} onInsert={(v) => activeInsert?.(v)} />
    </div>
  )
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

describe('TextBlockInspector (AC-121)', () => {
  it('edits run text, alignment and bold/italic/underline/font/size', () => {
    const { onBlockChange } = renderHarness()

    fireEvent.click(screen.getByRole('button', { name: 'Add run' }))
    const runText = screen.getByLabelText('Run text')
    fireEvent.change(runText, { target: { value: 'Hello' } })
    expect(onBlockChange).toHaveBeenLastCalledWith(
      expect.objectContaining({ runs: [expect.objectContaining({ text: 'Hello' })] }),
    )

    fireEvent.click(screen.getByRole('button', { name: 'Bold' }))
    fireEvent.click(screen.getByRole('button', { name: 'Italic' }))
    fireEvent.click(screen.getByRole('button', { name: 'Underline' }))
    expect(onBlockChange).toHaveBeenLastCalledWith(
      expect.objectContaining({
        runs: [expect.objectContaining({ bold: true, italic: true, underline: true })],
      }),
    )

    fireEvent.change(screen.getByLabelText('Font'), { target: { value: 'Calibri' } })
    expect(onBlockChange).toHaveBeenLastCalledWith(
      expect.objectContaining({ runs: [expect.objectContaining({ font: 'Calibri' })] }),
    )

    fireEvent.click(screen.getByRole('button', { name: 'Align center' }))
    expect(onBlockChange).toHaveBeenLastCalledWith(expect.objectContaining({ align: 'center' }))
  })

  it('inserts a page-number run and a total-pages run (AC-125)', () => {
    const { onBlockChange } = renderHarness()

    fireEvent.click(screen.getByRole('button', { name: 'Insert page number' }))
    fireEvent.click(screen.getByRole('button', { name: 'Insert total pages' }))

    expect(onBlockChange).toHaveBeenLastCalledWith(
      expect.objectContaining({
        runs: [expect.objectContaining({ field: 'page' }), expect.objectContaining({ field: 'total_pages' })],
      }),
    )
  })
})

describe('VariablePicker inside the text block editor (AC-122)', () => {
  it('is disabled until a run is focused, then inserts the token at the tracked caret', () => {
    const { onBlockChange } = renderHarness()

    expect(screen.getByText('Select a text run to insert a variable.')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Add run' }))
    const runText = screen.getByLabelText('Run text') as HTMLTextAreaElement
    fireEvent.change(runText, { target: { value: 'Dear client, regards.' } })
    fireEvent.focus(runText)
    runText.setSelectionRange(11, 11) // right after "Dear client" (before the comma)
    fireEvent.select(runText)

    fireEvent.click(screen.getByRole('button', { name: /Client name/ }))

    expect(onBlockChange).toHaveBeenLastCalledWith(
      expect.objectContaining({ runs: [expect.objectContaining({ text: 'Dear client{client.name}, regards.' })] }),
    )
  })

  it('filters variables by token and by label, grouped by category', () => {
    renderHarness()

    fireEvent.change(screen.getByLabelText('Search variables'), { target: { value: 'client' } })

    expect(screen.getByText('Client')).toBeInTheDocument()
    expect(screen.getByText('{client.name}')).toBeInTheDocument()
    expect(screen.queryByText('{quote.code}')).not.toBeInTheDocument()
  })
})
