import { useTranslation } from 'react-i18next'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import type { BusinessFunctionResetCandidate } from '@/features/product-categories/business-function-inheritance'

interface BusinessFunctionResetDialogProps {
  /** The descendants about to lose their own business function; null keeps the dialog closed. */
  categories: BusinessFunctionResetCandidate[] | null
  isConfirming: boolean
  onConfirm: () => void
  onCancel: () => void
}

/**
 * Warns before a save that assigns a business function to a category whose
 * descendants own one: the backend clears every one of them (spec 0023), and
 * the operator has to see WHICH ones before it happens.
 */
export function BusinessFunctionResetDialog({
  categories,
  isConfirming,
  onConfirm,
  onCancel,
}: BusinessFunctionResetDialogProps) {
  const { t } = useTranslation()

  return (
    <AlertDialog
      open={categories !== null}
      onOpenChange={(open) => !open && !isConfirming && onCancel()}
    >
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{t('productCategories.form.businessFunctionResetTitle')}</AlertDialogTitle>
          <AlertDialogDescription>
            {t('productCategories.form.businessFunctionResetDescription', {
              count: categories?.length ?? 0,
            })}
          </AlertDialogDescription>
        </AlertDialogHeader>

        <ul className="max-h-48 list-disc overflow-y-auto rounded-lg border border-border bg-muted/40 py-2 pr-3 pl-7 text-sm">
          {(categories ?? []).map((category) => (
            <li key={category.id}>{category.name}</li>
          ))}
        </ul>

        <AlertDialogFooter>
          <AlertDialogCancel disabled={isConfirming}>
            {t('productCategories.form.cancel')}
          </AlertDialogCancel>
          {/* The dialog must stay up while the save runs (Radix closes on
              action click by default), so the operator keeps a pending state
              instead of a form that silently reverts to idle. */}
          <AlertDialogAction
            disabled={isConfirming}
            onClick={(event) => {
              event.preventDefault()
              onConfirm()
            }}
          >
            {isConfirming
              ? t('productCategories.form.saving')
              : t('productCategories.form.businessFunctionResetConfirm')}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
