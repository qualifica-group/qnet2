import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { I18nextProvider } from 'react-i18next'
import i18n from '@/i18n'
import { documentLayoutsEditorEn } from '@/features/document-layouts/editor/editor-i18n-fixture'
import { PageSettingsPanel } from '@/features/document-layouts/editor/page/page-settings-panel'
import { createDefaultPage } from '@/features/document-layouts/layout-config-defaults'

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { documentLayouts: documentLayoutsEditorEn }, true, true)
})

function renderPanel(onChange = vi.fn()) {
  const page = createDefaultPage()
  render(
    <I18nextProvider i18n={i18n}>
      <PageSettingsPanel page={page} onChange={onChange} />
    </I18nextProvider>,
  )
  return { onChange, page }
}

/** Spec 0069 AC-124: orientation/margins/default font drive the config; an out-of-range margin never reaches it. */
describe('PageSettingsPanel (AC-124)', () => {
  it('commits a valid margin change', () => {
    const { onChange } = renderPanel()

    fireEvent.change(screen.getByLabelText('Top margin'), { target: { value: '720' } })

    expect(onChange).toHaveBeenCalledWith(expect.objectContaining({ margins: expect.objectContaining({ top: 720 }) }))
  })

  it('shows an accessible error and does not call onChange for a margin over the max', () => {
    const { onChange } = renderPanel()

    fireEvent.change(screen.getByLabelText('Top margin'), { target: { value: '99999' } })

    const input = screen.getByLabelText('Top margin')
    expect(input).toHaveAttribute('aria-invalid', 'true')
    const describedBy = input.getAttribute('aria-describedby')
    expect(describedBy).toBeTruthy()
    const message = screen.getByText('Value must be between 0 and 5670.')
    expect(message).toHaveAttribute('role', 'alert')
    expect(describedBy).toContain(message.id)
    expect(onChange).not.toHaveBeenCalled()
  })

  it('shows an accessible error and does not call onChange for a negative margin', () => {
    const { onChange } = renderPanel()

    fireEvent.change(screen.getByLabelText('Left margin'), { target: { value: '-10' } })

    expect(screen.getByLabelText('Left margin')).toHaveAttribute('aria-invalid', 'true')
    expect(onChange).not.toHaveBeenCalled()
  })

  it('changes orientation', () => {
    const { onChange } = renderPanel()

    fireEvent.click(screen.getByRole('combobox', { name: 'Orientation' }))
    fireEvent.click(screen.getByRole('option', { name: 'Landscape' }))

    expect(onChange).toHaveBeenCalledWith(expect.objectContaining({ orientation: 'landscape' }))
  })

  it('rejects an invalid default font color without emitting it', () => {
    const { onChange } = renderPanel()

    fireEvent.change(screen.getByLabelText('Color'), { target: { value: 'ZZZZZZ' } })

    expect(screen.getByLabelText('Color')).toHaveAttribute('aria-invalid', 'true')
    expect(onChange).not.toHaveBeenCalled()
  })
})
