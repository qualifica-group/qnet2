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
   * Spec 0075, D-4: the scope is not a default the operator may lift.
   * Request-management REFUSES a product outside the request's own categories
   * (`RequestProductCategoryCoherence`), so there the whole-catalogue escape
   * is not offered at all — the picker only ever shows what the server would
   * accept. The opportunities form leaves it false: there the cross-category
   * pick legitimately adds the missing product line.
   *
   * The other half of that guarantee — dropping a selection orphaned by a
   * category removal — belongs to the caller that owns both fields
   * (`useProductsOfInterestCoherence`), not to this picker.
   */
  lockScope?: boolean
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
 * product-line categories. Where the cross-category pick is MEANINGFUL — the
 * opportunities form, whose server adds the missing product line — the
 * operator may unlock the whole catalogue through an explicit confirmation
 * stating that consequence. Where it is REFUSED instead (request-management,
 * `lockScope`), no unlock is offered at all: the picker only ever shows what
 * the server would accept (spec 0075, D-4).
 */
export function ProductsOfInterestField({
  value,
  onChange,
  categoryIds,
  selectedItems = EMPTY_ITEMS,
  lockScope = false,
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
      description: t('products.ofInterest.unlockDialog.description'),
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
              : lockScope
                ? t('products.ofInterest.hintLocked')
                : t('products.ofInterest.hintScoped')}
        </p>

        {lockScope ? null : (
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
        )}
      </div>
    </div>
  )
}
