import { useTranslation } from 'react-i18next'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { ProductCategoryAttributeLayoutEditor } from '@/features/product-categories/product-category-attribute-layout-editor'

interface ProductCategoryAttributeLayoutSheetProps {
  categoryId: number | null
  categoryName: string | null
  onOpenChange: (open: boolean) => void
}

/**
 * Row-action Sheet opened from the Product Categories table (spec 0062
 * revision, entry point moved off the category form): hosts the
 * (context × form_mode) layout editor for one category at a time. `open` is
 * derived from `categoryId` (mirrors `ResourceActivityDialog`), and the
 * editor is only mounted while a category id is set, so no layout is fetched
 * while the sheet is closed.
 */
export function ProductCategoryAttributeLayoutSheet({
  categoryId,
  categoryName,
  onOpenChange,
}: ProductCategoryAttributeLayoutSheetProps) {
  const { t } = useTranslation('attributeLayout')

  return (
    <Sheet open={categoryId !== null} onOpenChange={onOpenChange}>
      <SheetContent storageKey="sheet-width:product-category-attribute-layout" defaultWidth={960}>
        <SheetHeader>
          <SheetTitle>{categoryName ?? t('section.title')}</SheetTitle>
          <SheetDescription>{t('section.description')}</SheetDescription>
        </SheetHeader>

        {categoryId !== null ? (
          <ProductCategoryAttributeLayoutEditor categoryId={categoryId} onCancel={() => onOpenChange(false)} />
        ) : null}
      </SheetContent>
    </Sheet>
  )
}
