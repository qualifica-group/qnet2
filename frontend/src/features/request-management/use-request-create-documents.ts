import { useMemo, useRef, useState } from 'react'
import { useWatch, type Control } from 'react-hook-form'
import { uploadAttachment } from '@/features/attachments/api'
import { DOCUMENTS_COLLECTION } from '@/features/attachments/types'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'
import type { ProductLineRow } from '@/features/product-lines/types'
import { QUOTE_ATTACHABLE_ALIAS } from '@/features/quotes/api'
import {
  effectiveRootId,
  targetQuoteId,
  type PendingRequestDocument,
} from '@/features/request-management/request-create-documents'
import type { RequestCreateFormValues } from '@/features/request-management/request-create-schema'
import { rootSplitGroups } from '@/features/request-management/root-split-names'

const EMPTY_TREE: ProductCategoryTreeNode[] = []
const EMPTY_ROWS: ProductLineRow[] = []

/**
 * The "Documenti" block of "Nuova richiesta" (spec 0415, D-4): files wait in
 * the form (an attachment needs a saved owner) and, once the request exists,
 * each one is uploaded onto the offer of the parent category picked for it.
 */
export function useRequestCreateDocuments(control: Control<RequestCreateFormValues>) {
  const rows = useWatch({ control, name: 'product_lines' }) ?? EMPTY_ROWS
  const tree = useProductCategoryTree().data ?? EMPTY_TREE
  const groups = useMemo(() => rootSplitGroups(rows, tree), [rows, tree])
  const [pending, setPending] = useState<PendingRequestDocument[]>([])
  const nextKey = useRef(0)

  // Derived, never stored: a parent category removed from the lines sends its
  // files back to the first one without an effect.
  const documents = pending.map((document) => ({ ...document, rootId: effectiveRootId(document.rootId, groups) }))

  const add = (files: File[]) => {
    const added = files.map((file) => ({ key: nextKey.current++, file, rootId: null }))
    setPending((current) => [...current, ...added])
  }

  const remove = (key: number) => setPending((current) => current.filter((document) => document.key !== key))

  const changeRoot = (key: number, rootId: number) =>
    setPending((current) => current.map((document) => (document.key === key ? { ...document, rootId } : document)))

  /** Uploads every file onto its offer; resolves with the names that failed. */
  const uploadAll = async (createdQuoteIds: number[]): Promise<string[]> => {
    const failed: string[] = []

    for (const document of documents) {
      try {
        await uploadAttachment({
          resource: QUOTE_ATTACHABLE_ALIAS,
          id: targetQuoteId(document.rootId, groups, createdQuoteIds),
          collection: DOCUMENTS_COLLECTION,
          file: document.file,
        })
      } catch {
        failed.push(document.file.name)
      }
    }

    return failed
  }

  return { documents, groups, add, remove, changeRoot, uploadAll }
}

export type RequestCreateDocuments = ReturnType<typeof useRequestCreateDocuments>
