import { lazy } from 'react'
import type { QuickCreateEntry, QuickCreateFormProps } from '@/features/quick-create/types'
import { SECTORS_FOR_SELECT_RESOURCE } from '@/features/sectors/for-select-api'
import { PRODUCT_CATEGORIES_FOR_SELECT_RESOURCE } from '@/features/product-categories/for-select-api'

/**
 * Quick-create entries for the two tree-shaped modules: their create mode
 * additionally takes `parentId` (spec 0028 context). Quick-create always
 * creates a root-level record (`parentId: null`) — the module's own tree UI
 * remains the way to create a scoped child.
 */

const sectors: QuickCreateEntry = {
  titleKey: 'sectors.form.createTitle',
  descriptionKey: 'sectors.form.createSubtitle',
  permission: 'sectors.create',
  form: lazy(async () => {
    const { SectorForm } = await import('@/features/sectors/sector-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <SectorForm
          onSuccess={(sector) => onSuccess({ id: sector.id, name: sector.name })}
          onCancel={onCancel}
        />
      ),
    }
  }),
}

const productCategories: QuickCreateEntry = {
  titleKey: 'productCategories.form.createTitle',
  descriptionKey: 'productCategories.form.createSubtitle',
  permission: 'product-categories.create',
  form: lazy(async () => {
    const { ProductCategoryForm } = await import('@/features/product-categories/product-category-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <ProductCategoryForm
          mode={{ type: 'create', parentId: null }}
          onSuccess={(category) => onSuccess({ id: category.id, name: category.name })}
          onCancel={onCancel}
        />
      ),
    }
  }),
}

/** resource -> entry, for the tree-shaped modules covered by this file. */
export const hierarchicalEntries: Record<string, QuickCreateEntry> = {
  [SECTORS_FOR_SELECT_RESOURCE]: sectors,
  [PRODUCT_CATEGORIES_FOR_SELECT_RESOURCE]: productCategories,
}
