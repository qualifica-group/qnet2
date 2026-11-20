import { useTranslation } from 'react-i18next'
import { Info } from 'lucide-react'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'
import type { ProductLineRow } from '@/features/product-lines/types'
import { rootSplitNames } from '@/features/request-management/root-split-names'

/** Stable empty tree while the shared query is still loading. */
const EMPTY_TREE: ProductCategoryTreeNode[] = []

interface RequestCreateRootSplitNoticeProps {
  rows: ProductLineRow[]
}

/**
 * Spec 0411 (user request 2026-11-18): warns, before saving, that product
 * lines of several parent categories create one request per parent category,
 * so whoever fills the form knows it up front. Nothing for a single root.
 */
export function RequestCreateRootSplitNotice({ rows }: RequestCreateRootSplitNoticeProps) {
  const { t } = useTranslation()
  const tree = useProductCategoryTree().data ?? EMPTY_TREE
  const names = rootSplitNames(rows, tree)

  if (names.length < 2) {
    return null
  }

  return (
    <div
      role="status"
      className="flex items-start gap-2 rounded-lg border border-primary/30 bg-primary/5 px-3 py-2.5 text-sm text-foreground"
    >
      <Info className="mt-0.5 size-4 shrink-0 text-primary" aria-hidden="true" />
      <span>{t('requestManagement.form.create.rootSplitNotice', { count: names.length, roots: names.join(', ') })}</span>
    </div>
  )
}
