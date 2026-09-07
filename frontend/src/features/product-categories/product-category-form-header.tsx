import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import { EyeOff, FolderTree, Loader2, TriangleAlert } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { RECORD_HEADER_CLASS } from '@/components/record-form/layout'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

/** The parent picker's option shape, reduced to what the pill needs to resolve a name. */
export interface ParentOptionRef {
  id: number
  name: string
}

interface ProductCategoryFormHeaderProps {
  control: Control<ProductCategoryFormValues>
  /** Drives the heading pair (create/edit); the badges are live in both modes. */
  isEdit: boolean
  /** The parent picker's own option list: the only place a parent id has a NAME here. */
  parentOptions: readonly ParentOptionRef[]
  /** id of the RHF `<form>` the save action attaches to via the HTML `form=` attribute. */
  formId: string
  isSubmitting: boolean
  submitError: string | null
  onCancel: () => void
}

/**
 * Identity bar of the category form — the same bar the Opportunita',
 * Gestione Richieste and Prodotti screens carry (`RECORD_HEADER_CLASS`):
 * heading and live pills on the left, save/cancel on the right, a refused
 * submit reported right under the button that was pressed.
 *
 * This bar is the form's ONE heading: the module is registered
 * `formOwnsHeader`, so the dedicated page drops its own title/subtitle block
 * and the Sheet keeps its `SheetHeader` `sr-only`.
 *
 * The two pills answer the questions a category is read by: WHERE it sits
 * (its parent, or "root" — reparenting is the single edit that changes what
 * the whole subtree inherits) and whether it can be picked at all, since an
 * unselectable one behaves like a pure container and that is not otherwise
 * visible without scrolling to the rules.
 */
export function ProductCategoryFormHeader({
  control,
  isEdit,
  parentOptions,
  formId,
  isSubmitting,
  submitError,
  onCancel,
}: ProductCategoryFormHeaderProps) {
  const { t } = useTranslation()
  const parentId = useWatch({ control, name: 'parent_id' })
  const isSelectable = useWatch({ control, name: 'is_selectable' })

  const parentName =
    parentId === null ? null : (parentOptions.find((option) => option.id === parentId)?.name ?? null)

  return (
    <header className={RECORD_HEADER_CLASS}>
      <div className="flex min-w-0 flex-1 flex-wrap items-center gap-x-2 gap-y-1">
        <div className="flex min-w-0 flex-col">
          <h1 className="min-w-0 truncate text-base font-semibold">
            {t(isEdit ? 'productCategories.form.editTitle' : 'productCategories.form.createTitle')}
          </h1>
          <p className="min-w-0 truncate text-sm text-muted-foreground">
            {t(
              isEdit
                ? 'productCategories.form.editSubtitle'
                : 'productCategories.form.createSubtitle',
            )}
          </p>
        </div>

        <Badge variant="outline" className="h-5 min-h-5 max-w-full gap-1.5">
          <FolderTree className="size-3" aria-hidden="true" />
          <span className="truncate">{parentName ?? t('productCategories.badges.root')}</span>
        </Badge>
        {!isSelectable ? (
          <Badge variant="outline" className="h-5 min-h-5 max-w-full gap-1.5">
            <EyeOff className="size-3" aria-hidden="true" />
            <span className="truncate">{t('productCategories.badges.notSelectable')}</span>
          </Badge>
        ) : null}
      </div>

      <div className="ml-auto flex shrink-0 items-center gap-2">
        <Button type="button" variant="secondary" onClick={onCancel} disabled={isSubmitting}>
          {t('productCategories.form.cancel')}
        </Button>
        <Button type="submit" form={formId} disabled={isSubmitting}>
          {isSubmitting && <Loader2 className="size-4 animate-spin" aria-hidden="true" />}
          {isSubmitting
            ? t('productCategories.form.saving')
            : t('productCategories.form.save')}
        </Button>
      </div>

      {submitError && (
        <div
          role="alert"
          className="flex w-full items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm font-medium text-destructive"
        >
          <TriangleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
          {submitError}
        </div>
      )}
    </header>
  )
}
