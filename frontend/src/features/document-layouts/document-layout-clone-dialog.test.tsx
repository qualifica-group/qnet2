import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { AxiosError, AxiosHeaders } from 'axios'
import i18n from '@/i18n'
import { DocumentLayoutCloneDialog } from '@/features/document-layouts/document-layout-clone-dialog'
import type { DocumentLayoutCloneSource } from '@/features/document-layouts/types'

/** Spec 0339 AC-007: the clone dialog pre-fills name/code, posts, and opens the new layout. */

const cloneDocumentLayoutMock = vi.fn()

vi.mock('@/features/document-layouts/api', () => ({
  cloneDocumentLayout: (...args: unknown[]) => cloneDocumentLayoutMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const SOURCE: DocumentLayoutCloneSource = { id: 7, name: 'Standard quote layout', code: 'standard' }

function renderDialog(source: DocumentLayoutCloneSource | null = SOURCE, onClose = vi.fn()) {
  render(
    <MemoryRouter initialEntries={['/document-layouts/7']}>
      <Routes>
        <Route path="/document-layouts/7" element={<DocumentLayoutCloneDialog source={source} onClose={onClose} />} />
        <Route path="/document-layouts/:id" element={<p>new layout page</p>} />
      </Routes>
    </MemoryRouter>,
  )
  return { onClose }
}

function validationError(errors: Record<string, string[]>) {
  return new AxiosError('Unprocessable', 'ERR_BAD_REQUEST', undefined, undefined, {
    status: 422,
    statusText: 'Unprocessable',
    headers: {},
    config: { headers: new AxiosHeaders() },
    data: { success: false, message: Object.values(errors)[0][0], errors },
  })
}

const nameInput = () => screen.getByRole('textbox', { name: new RegExp(i18n.t('documentLayouts.form.name')) })
const codeInput = () => screen.getByRole('textbox', { name: new RegExp(i18n.t('documentLayouts.form.code')) })
const submit = () => fireEvent.click(screen.getByRole('button', { name: i18n.t('documentLayouts.clone.submit') }))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  cloneDocumentLayoutMock.mockReset()
})

describe('DocumentLayoutCloneDialog', () => {
  it('stays closed without a source', () => {
    renderDialog(null)

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('pre-fills the copy name and code from the source', () => {
    renderDialog()

    expect(screen.getByRole('dialog', { name: i18n.t('documentLayouts.clone.title') })).toBeInTheDocument()
    expect(nameInput()).toHaveValue('Standard quote layout (copy)')
    expect(codeInput()).toHaveValue('standard_copy')
  })

  it('posts the edited name/code and opens the new layout page', async () => {
    cloneDocumentLayoutMock.mockResolvedValue({ id: 42 })
    renderDialog()

    fireEvent.change(nameInput(), { target: { value: 'Quote layout 2027' } })
    fireEvent.change(codeInput(), { target: { value: 'quote_2027' } })
    submit()

    expect(await screen.findByText('new layout page')).toBeInTheDocument()
    expect(cloneDocumentLayoutMock).toHaveBeenCalledWith(7, { name: 'Quote layout 2027', code: 'quote_2027' })
  })

  it('blocks an invalid code client-side without calling the API', async () => {
    renderDialog()

    fireEvent.change(codeInput(), { target: { value: 'Not Valid' } })
    submit()

    expect(await screen.findByText(i18n.t('documentLayouts.form.codeInvalid'))).toBeInTheDocument()
    expect(cloneDocumentLayoutMock).not.toHaveBeenCalled()
  })

  it('shows a server 422 on its field and stays open', async () => {
    cloneDocumentLayoutMock.mockRejectedValue(validationError({ code: ['The code has already been taken.'] }))
    renderDialog()

    submit()

    expect(await screen.findByText('The code has already been taken.')).toBeInTheDocument()
    expect(screen.getByRole('dialog')).toBeInTheDocument()
  })

  it('calls onClose on Cancel', () => {
    const { onClose } = renderDialog()

    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.cancel') }))

    expect(onClose).toHaveBeenCalled()
  })
})
