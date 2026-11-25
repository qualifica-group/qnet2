import { useTranslation } from 'react-i18next'
import { SUPPLIER_COMMISSION_DIRECTIONS } from './types'

/**
 * What Ricevuta / Pagata mean for the line's revenue and margin, always visible
 * on the form (a tooltip was not enough): the chosen direction when there is
 * one, both while the user is still choosing.
 */
export function SupplierDirectionExplanation({ value }: { value?: string | null }) {
  const { t } = useTranslation()
  const directions = SUPPLIER_COMMISSION_DIRECTIONS.filter((direction) => !value || direction === value)

  return (
    <span className="mt-1 flex flex-col gap-1 text-xs text-muted-foreground">
      {directions.map((direction) => (
        <span key={direction}>{t(`commissionConfigurations.form.directionExplanations.${direction}`)}</span>
      ))}
    </span>
  )
}
