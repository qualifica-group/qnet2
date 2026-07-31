import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { LockOpen, Lock } from 'lucide-react'
import { AsyncPaginatedMultiSelect } from '@/components/ui/async-paginated-multi-select'
import { Button } from '@/components/ui/button'
import { useConfirm } from '@/components/confirm-dialog-context'
import type { ForSelectItem } from '@/features/for-select/types'
import { PRODUCTS_FOR_SELECT_RESOURCE } from '@/features/products/for-select-api'

interface ProductsOfInterestFieldProps {
  /** Selected product ids (controlled). */
  value: number[]
  onChange: (next: number[]) => void
  /**
   * Product-category ids of the opportunity's product lines: the picker's
   * DEFAULT scope. Empty means the opportunity classifies nothing yet, so
   * there is nothing to scope to (see the locked-empty branch below).
   */
  categoryIds: number[]
  /** `{id, label}` of the already-selected products, so a badge never falls back to `#id`. */
  selectedItems?: ForSelectItem[]
  /**
   * Overrides the unlock dialog's consequence line. The default states the
   * opportunities rule (the missing product line is added server-side); the
   * request-management module, which REFUSES an incoherent pick instead (user
   * directive 2026-07-31), passes its own.
   */
  unlockDescription?: string
  disabled?: boolean
  /** Forwarded by `FormControl` for the accessible-error triad (frontend.md §10). */
  id?: string
  'aria-describedby'?: string
  'aria-invalid'?: boolean
}

/** Stable empty default: an inline `[]` would be a new reference on every render. */
const EMPTY_ITEMS: ForSelectItem[] = []

/**
 * The "prodotti di interesse" picker (user directive 2026-07-22), shared
 * VERBATIM by the opportunity form and the request-management work panel so
 * the two never drift.
 *
 * By default the options are scoped to the products of the opportunity's own
 * product-line categories. The operator may unlock the whole catalogue, but
 * only through an explicit confirmation: picking a product from another
 * business function / product category ADDS that pair to the opportunity's
 * product lines (server-side, OpportunityProductInterestWriter), and the
 * dialog is where that consequence is stated before it happens — which is why
 * the consequence line is overridable (`unlockDescription`): in
 * request-management the same pick is REFUSED instead of auto-covered.
 */
export function ProductsOfInterestField({
  value,
  onChange,
  categoryIds,
  selectedItems = EMPTY_ITEMS,
  unlockDescription,
  disabled = false,
  id,
  'aria-describedby': ariaDescribedBy,
  'aria-invalid': ariaInvalid,
}: ProductsOfInterestFieldProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const [unlocked, setUnlocked] = useState(false)

  // Referentially stable, else every render would restart the paginated query.
  const params = useMemo(
    () => (unlocked ? undefined : { category_ids: categoryIds }),
    [unlocked, categoryIds],
  )

  // Locked with nothing to scope to: filtering by an empty set would silently
  // show the WHOLE catalogue, i.e. the opposite of what the lock promises.
  const lockedWithoutScope = !unlocked && categoryIds.length === 0

  const requestUnlock = async () => {
    const confirmed = await confirm({
      tone: 'warning',
      title: t('products.ofInterest.unlockDialog.title'),
      description: unlockDescription ?? t('products.ofInterest.unlockDialog.description'),
      confirmLabel: t('products.ofInterest.unlockDialog.confirm'),
      cancelLabel: t('common.cancel'),
    })

    if (confirmed) {
      setUnlocked(true)
    }
  }

  return (
    <div className="flex flex-col gap-2">
      <AsyncPaginatedMultiSelect
        resource={PRODUCTS_FOR_SELECT_RESOURCE}
        value={value}
        onChange={onChange}
        selectedItems={selectedItems}
        params={params}
        disabled={disabled || lockedWithoutScope}
        id={id}
        aria-describedby={ariaDescribedBy}
        aria-invalid={ariaInvalid}
        labels={{
          placeholder: t('products.ofInterest.placeholder'),
          searchPlaceholder: t('products.ofInterest.searchPlaceholder'),
          empty: t('products.ofInterest.empty'),
          error: t('opportunities.form.selectError'),
          removeLabel: t('products.ofInterest.remove'),
          triggerLabel: t('products.ofInterest.fieldLabel'),
          retry: t('common.retry'),
        }}
      />

      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="text-xs text-muted-foreground">
          {unlocked
            ? t('products.ofInterest.hintUnlocked')
            : lockedWithoutScope
              ? t('products.ofInterest.hintNoCategories')
              : t('products.ofInterest.hintScoped')}
        </p>

        <Button
          type="button"
          variant="outline"
          size="sm"
          disabled={disabled}
          onClick={unlocked ? () => setUnlocked(false) : requestUnlock}
        >
          {unlocked ? (
            <>
              <Lock aria-hidden="true" className="size-3.5" />
              {t('products.ofInterest.relock')}
            </>
          ) : (
            <>
              <LockOpen aria-hidden="true" className="size-3.5" />
              {t('products.ofInterest.unlock')}
            </>
          )}
        </Button>
      </div>
    </div>
  )
}
