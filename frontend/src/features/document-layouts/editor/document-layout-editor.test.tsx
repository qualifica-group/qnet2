import { useState } from 'react'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { I18nextProvider } from 'react-i18next'
import i18n from '@/i18n'
import { documentLayoutsEditorEn } from '@/features/document-layouts/editor/editor-i18n-fixture'
import { DocumentLayoutEditor } from '@/features/document-layouts/editor/document-layout-editor'
import type { ConfigValidationError } from '@/features/document-layouts/editor/config-validation-errors'
import { createEmptyDocumentLayoutConfig } from '@/features/document-layouts/layout-config-defaults'
import type { DocumentLayoutConfig } from '@/features/document-layouts/layout-config'

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { documentLayouts: documentLayoutsEditorEn }, true, true)
})

vi.mock('@/features/document-layouts/variables-api', () => ({
  useDocumentLayoutVariables: () => ({ data: { module: 'quotes', categories: [] }, isLoading: false }),
}))

vi.mock('@/features/document-layouts/editor/images/document-layout-images-api', () => ({
  useDocumentLayoutImages: () => ({ data: [] }),
  useUploadDocumentLayoutImage: () => ({ mutate: vi.fn(), isPending: false }),
  useDeleteDocumentLayoutImage: () => ({ mutate: vi.fn(), isPending: false }),
}))

function Harness({
  onConfigChange,
  configErrors = [],
}: {
  onConfigChange: (config: DocumentLayoutConfig) => void
  configErrors?: ConfigValidationError[]
}) {
  const [config, setConfig] = useState<DocumentLayoutConfig>(() => createEmptyDocumentLayoutConfig())
  function handleChange(next: DocumentLayoutConfig) {
    setConfig(next)
    onConfigChange(next)
  }
  return (
    <DocumentLayoutEditor
      config={config}
      onChange={handleChange}
      module="quotes"
      layoutId={7}
      configErrors={configErrors}
    />
  )
}

function renderEditor(configErrors: ConfigValidationError[] = []) {
  const onConfigChange = vi.fn()
  const view = render(
    <I18nextProvider i18n={i18n}>
      <Harness onConfigChange={onConfigChange} configErrors={configErrors} />
    </I18nextProvider>,
  )
  return { onConfigChange, ...view }
}

describe('DocumentLayoutEditor', () => {
  it('adds a block to the body zone only (AC-120)', () => {
    const { onConfigChange } = renderEditor()

    const addButtons = screen.getAllByRole('button', { name: 'Add block' })
    fireEvent.pointerDown(addButtons[1], { button: 0, ctrlKey: false }) // header, body, footer order
    fireEvent.click(screen.getByRole('menuitem', { name: 'Text' }))

    const lastConfig = onConfigChange.mock.calls.at(-1)?.[0] as DocumentLayoutConfig
    expect(lastConfig.body.blocks).toHaveLength(1)
    expect(lastConfig.header.blocks).toHaveLength(0)
    expect(lastConfig.footer.blocks).toHaveLength(0)
  })

  it('removes a block from only the zone it belongs to (AC-120)', () => {
    const { onConfigChange } = renderEditor()

    fireEvent.pointerDown(screen.getAllByRole('button', { name: 'Add block' })[1], { button: 0, ctrlKey: false })
    fireEvent.click(screen.getByRole('menuitem', { name: 'Spacer' }))

    fireEvent.click(screen.getByRole('button', { name: 'Remove block' }))

    const lastConfig = onConfigChange.mock.calls.at(-1)?.[0] as DocumentLayoutConfig
    expect(lastConfig.body.blocks).toHaveLength(0)
  })

  it('selecting a block shows its type-specific inspector', () => {
    renderEditor()

    fireEvent.pointerDown(screen.getAllByRole('button', { name: 'Add block' })[1], { button: 0, ctrlKey: false })
    fireEvent.click(screen.getByRole('menuitem', { name: 'Divider' }))
    fireEvent.click(screen.getByRole('button', { name: 'Divider' }))

    expect(screen.getByText('Width (%)')).toBeInTheDocument()
  })

  it('surfaces a config validation error on the exact offending block (AC-129)', () => {
    renderEditor([
      { zone: 'body', blockIndex: 0, path: ['height'], rawPath: 'config.body.blocks.0.height', message: 'Height must be at least 1.' },
    ])

    fireEvent.pointerDown(screen.getAllByRole('button', { name: 'Add block' })[1], { button: 0, ctrlKey: false })
    fireEvent.click(screen.getByRole('menuitem', { name: 'Spacer' }))

    // The row for the (only) body block carries the error ring; selecting it surfaces the message.
    fireEvent.click(screen.getByText('Spacer (12pt)'))
    expect(screen.getByText('Height must be at least 1.')).toBeInTheDocument()
  })

  it('stacks canvas/inspector/variables in one column by default (md:grid-cols is the responsive split, AC-140)', () => {
    const { container } = renderEditor()

    const root = container.firstElementChild as HTMLElement
    expect(root.className).toContain('grid-cols-1')
    expect(root.className).toMatch(/md:grid-cols-/)
  })
})
