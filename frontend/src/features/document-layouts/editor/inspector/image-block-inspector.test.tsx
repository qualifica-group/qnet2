import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { I18nextProvider } from 'react-i18next'
import i18n from '@/i18n'
import { documentLayoutsEditorEn } from '@/features/document-layouts/editor/editor-i18n-fixture'
import { ImageBlockInspector } from '@/features/document-layouts/editor/inspector/image-block-inspector'
import { createDefaultImageBlock } from '@/features/document-layouts/layout-config-defaults'
import type { DocumentLayoutImage } from '@/features/document-layouts/editor/images/document-layout-images-api'

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { documentLayouts: documentLayoutsEditorEn }, true, true)
})

/**
 * The images backend does not exist yet at the time of writing (spec 0069
 * wave 2 report): mocked at the query-hook boundary, same as every other
 * TanStack Query consumer test in this repo.
 */
let imagesFixture: DocumentLayoutImage[] = []
const uploadMutate = vi.fn()
const deleteMutate = vi.fn()

vi.mock('@/features/document-layouts/editor/images/document-layout-images-api', () => ({
  useDocumentLayoutImages: () => ({ data: imagesFixture }),
  useUploadDocumentLayoutImage: () => ({ mutate: uploadMutate, isPending: false }),
  useDeleteDocumentLayoutImage: () => ({ mutate: deleteMutate, isPending: false }),
}))

function renderInspector(overrides: Partial<Parameters<typeof ImageBlockInspector>[0]> = {}) {
  const onChange = vi.fn()
  const block = createDefaultImageBlock('img1', imagesFixture[0]?.attachment_id ?? 0)
  render(
    <I18nextProvider i18n={i18n}>
      <ImageBlockInspector block={block} zone="header" layoutId={7} onChange={onChange} disabled={false} {...overrides} />
    </I18nextProvider>,
  )
  return { onChange, block }
}

describe('ImageBlockInspector (AC-126)', () => {
  it('shows a "save layout first" message when the layout has no id yet', () => {
    imagesFixture = []
    const onChange = vi.fn()
    render(
      <I18nextProvider i18n={i18n}>
        <ImageBlockInspector
          block={createDefaultImageBlock('img1', 1)}
          zone="header"
          layoutId={null}
          onChange={onChange}
          disabled={false}
        />
      </I18nextProvider>,
    )

    expect(screen.getByText('Save the document layout first to upload images.')).toBeInTheDocument()
  })

  it('shows the empty state with an upload action when the layout has zero images', () => {
    imagesFixture = []
    renderInspector()

    expect(screen.getByText('No images uploaded yet.')).toBeInTheDocument()
    expect(screen.getByLabelText('Upload image')).toBeInTheDocument()
  })

  it('lists uploaded images and lets the user pick one, setting width/align/wrap', () => {
    imagesFixture = [
      { attachment_id: 1, filename: 'logo.png', mime_type: 'image/png', size: 100, data_uri: 'data:image/png;base64,AAA' },
      { attachment_id: 2, filename: 'header.png', mime_type: 'image/png', size: 200, data_uri: 'data:image/png;base64,BBB' },
    ]
    const { onChange } = renderInspector()

    fireEvent.click(screen.getByRole('button', { name: /header\.png/ }))
    expect(onChange).toHaveBeenLastCalledWith(expect.objectContaining({ attachment_id: 2 }))

    fireEvent.change(screen.getByLabelText('Width'), { target: { value: '300' } })
    expect(onChange).toHaveBeenLastCalledWith(expect.objectContaining({ width: 300 }))
  })

  it('offers "behind page" wrap only in the header zone', () => {
    imagesFixture = [{ attachment_id: 1, filename: 'logo.png', mime_type: 'image/png', size: 100, data_uri: 'data:image/png;base64,AAA' }]
    renderInspector({ zone: 'header' })

    fireEvent.click(screen.getByRole('combobox', { name: 'Wrap' }))
    expect(screen.getByRole('option', { name: 'Behind page (full-page background)' })).toBeInTheDocument()
  })

  it('does not offer "behind page" wrap in the body zone', () => {
    imagesFixture = [{ attachment_id: 1, filename: 'logo.png', mime_type: 'image/png', size: 100, data_uri: 'data:image/png;base64,AAA' }]
    renderInspector({ zone: 'body' })

    fireEvent.click(screen.getByRole('combobox', { name: 'Wrap' }))
    expect(screen.queryByRole('option', { name: 'Behind page (full-page background)' })).not.toBeInTheDocument()
  })

  it('uploads a new file through the mocked API client', () => {
    imagesFixture = []
    renderInspector()

    const file = new File(['binary'], 'new-logo.png', { type: 'image/png' })
    fireEvent.change(screen.getByLabelText('Upload image'), { target: { files: [file] } })

    expect(uploadMutate).toHaveBeenCalledWith(file)
  })
})
