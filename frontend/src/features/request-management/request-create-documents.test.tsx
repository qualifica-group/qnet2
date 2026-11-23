import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { act, render, renderHook, screen } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import i18n from '@/i18n'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'
import type { ProductLineRow } from '@/features/product-lines/types'
import { effectiveRootId, targetQuoteId } from '@/features/request-management/request-create-documents'
import { RequestCreateDocumentsSection } from '@/features/request-management/request-create-documents-section'
import type { RequestCreateFormValues } from '@/features/request-management/request-create-schema'
import { useRequestCreateDocuments } from '@/features/request-management/use-request-create-documents'

/**
 * Spec 0415 D-4: files queued in "Nuova richiesta" go, after the save, onto
 * the offer of the parent category picked for each; with one parent category
 * there is no choice.
 */

const TREE = [
  { id: 1, name: 'ISO', parent_id: null, children: [{ id: 11, name: 'ISO 9001', parent_id: 1, children: [] }] },
  { id: 2, name: 'APL', parent_id: null, children: [{ id: 21, name: 'Tirocinio', parent_id: 2, children: [] }] },
] as unknown as ProductCategoryTreeNode[]

vi.mock('@/features/product-categories/use-product-category-tree', () => ({
  useProductCategoryTree: () => ({ data: TREE }),
}))

const uploadAttachmentMock = vi.fn()
vi.mock('@/features/attachments/api', () => ({
  uploadAttachment: (payload: unknown) => uploadAttachmentMock(payload),
}))

const ISO_LINE: ProductLineRow = { root_category_id: null, product_category_id: 11 }
const APL_LINE: ProductLineRow = { root_category_id: null, product_category_id: 21 }

function useHarness(rows: ProductLineRow[]) {
  const form = useForm<RequestCreateFormValues>({
    defaultValues: { product_lines: rows } as unknown as RequestCreateFormValues,
  })
  return { form, documents: useRequestCreateDocuments(form.control) }
}

function file(name: string): File {
  return new File(['x'], name, { type: 'application/pdf' })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  uploadAttachmentMock.mockReset()
  uploadAttachmentMock.mockResolvedValue({})
})

describe('request create documents helpers', () => {
  const groups = [
    { id: 1, name: 'ISO' },
    { id: 2, name: 'APL' },
  ]

  it('keeps a picked parent category still among the lines, otherwise the first', () => {
    expect(effectiveRootId(2, groups)).toBe(2)
    expect(effectiveRootId(null, groups)).toBe(1)
    expect(effectiveRootId(9, groups)).toBe(1)
    expect(effectiveRootId(null, [])).toBeNull()
  })

  it('maps a parent category onto the created offer of the same position', () => {
    expect(targetQuoteId(1, groups, [100, 200])).toBe(100)
    expect(targetQuoteId(2, groups, [100, 200])).toBe(200)
    expect(targetQuoteId(null, [], [100])).toBe(100)
  })
})

describe('useRequestCreateDocuments', () => {
  it('uploads each file onto the offer of its parent category (AC-006)', async () => {
    const { result } = renderHook(() => useHarness([ISO_LINE, APL_LINE]))

    act(() => result.current.documents.add([file('iso.pdf'), file('apl.pdf')]))
    const aplKey = result.current.documents.documents[1].key
    act(() => result.current.documents.changeRoot(aplKey, 2))

    let failed: string[] = []
    await act(async () => {
      failed = await result.current.documents.uploadAll([100, 200])
    })

    expect(failed).toEqual([])
    expect(uploadAttachmentMock).toHaveBeenCalledWith(
      expect.objectContaining({ resource: 'quote', id: 100, collection: 'documents', file: expect.objectContaining({ name: 'iso.pdf' }) }),
    )
    expect(uploadAttachmentMock).toHaveBeenCalledWith(
      expect.objectContaining({ resource: 'quote', id: 200, file: expect.objectContaining({ name: 'apl.pdf' }) }),
    )
  })

  it('sends every file to the single offer with one parent category (AC-007)', async () => {
    const { result } = renderHook(() => useHarness([ISO_LINE]))

    act(() => result.current.documents.add([file('a.pdf'), file('b.pdf')]))
    await act(async () => {
      await result.current.documents.uploadAll([100])
    })

    expect(uploadAttachmentMock).toHaveBeenCalledTimes(2)
    expect(uploadAttachmentMock.mock.calls.every(([payload]) => (payload as { id: number }).id === 100)).toBe(true)
  })

  it('names the files whose upload failed', async () => {
    uploadAttachmentMock.mockRejectedValueOnce(new Error('413'))
    const { result } = renderHook(() => useHarness([ISO_LINE]))

    act(() => result.current.documents.add([file('big.pdf'), file('ok.pdf')]))
    let failed: string[] = []
    await act(async () => {
      failed = await result.current.documents.uploadAll([100])
    })

    expect(failed).toEqual(['big.pdf'])
  })

  it('removes a queued file', () => {
    const { result } = renderHook(() => useHarness([ISO_LINE]))

    act(() => result.current.documents.add([file('a.pdf')]))
    act(() => result.current.documents.remove(result.current.documents.documents[0].key))

    expect(result.current.documents.documents).toEqual([])
  })
})

function SectionHarness({ rows }: { rows: ProductLineRow[] }) {
  const { documents } = useHarness(rows)
  return (
    <>
      <button type="button" onClick={() => documents.add([file('scan.pdf')])}>
        queue
      </button>
      <RequestCreateDocumentsSection documents={documents} />
    </>
  )
}

describe('RequestCreateDocumentsSection', () => {
  it('offers the request choice per file with two parent categories (AC-006)', () => {
    render(<SectionHarness rows={[ISO_LINE, APL_LINE]} />)
    act(() => screen.getByRole('button', { name: 'queue' }).click())

    expect(screen.getByRole('combobox', { name: 'Request to attach scan.pdf to' })).toHaveTextContent('ISO request')
    expect(screen.getByRole('button', { name: 'Remove scan.pdf' })).toBeInTheDocument()
  })

  it('offers no choice with one parent category (AC-007)', () => {
    render(<SectionHarness rows={[ISO_LINE]} />)
    act(() => screen.getByRole('button', { name: 'queue' }).click())

    expect(screen.getByText('scan.pdf')).toBeInTheDocument()
    expect(screen.queryByRole('combobox')).toBeNull()
  })
})
