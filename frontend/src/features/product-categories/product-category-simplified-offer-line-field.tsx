import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { ListChecks } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { FormControl, FormDescription } from '@/components/ui/form'
import { Switch } from '@/components/ui/switch'
import { MetaField } from '@/features/authorization/MetaField'
import { ProductCategoryRootFlagField } from '@/features/product-categories/product-category-root-flag-field'
import { ProductCategoryRuleCard } from '@/features/product-categories/product-category-rule-card'
import { simplifiedOfferLineOverrideFor } from '@/features/product-categories/simplified-offer-line-inheritance'
import type { ProductCategoryFormMode } from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'
import { useSimplifiedOfferLineInheritance } from '@/features/product-categories/use-simplified-offer-line-inheritance'

interface ProductCategorySimplifiedOfferLineFieldProps {
  control: Control<ProductCategoryFormValues>
  mode: ProductCategoryFormMode
  /** Current watched `parent_id` form value — the field reacts live to it, not to the saved one. */
  parentId: number | null
}

/**
 * The "Semplificazione riga offerta" rule (spec 0114, revised by 0188): when
 * on, Gestione Richieste stops asking the operator to compile quantity/unit
 * price/VAT rate on the offer row — the system values them from the picked
 * product. A ROOT declares the value itself; a child inherits the nearest
 * declaring ancestor's value and can force its own (override), like
 * `is_reportable`. The backend congeals the values at write time regardless of
 * what the client sends (0114 D-4). The Offerte module never reads this flag.
 */
export function ProductCategorySimplifiedOfferLineField({
  control,
  mode,
  parentId,
}: ProductCategorySimplifiedOfferLineFieldProps) {
  const { t } = useTranslation()

  if (parentId === null) {
    return (
      <ProductCategoryRootFlagField
        control={control}
        name="simplified_offer_line"
        inheritance={null}
        icon={ListChecks}
        label={t('productCategories.form.simplifiedOfferLine')}
        hint={t('productCategories.form.simplifiedOfferLineInfo')}
        hintLabel={t('productCategories.form.simplifiedOfferLineInfoLabel')}
        description={t('productCategories.form.simplifiedOfferLineHint')}
        inheritedDescription=""
        inheritedBadge=""
      />
    )
  }

  return <SimplifiedOfferLineOverrideRule control={control} mode={mode} />
}

interface SimplifiedOfferLineOverrideRuleProps {
  control: Control<ProductCategoryFormValues>
  mode: ProductCategoryFormMode
}

/**
 * The child-category tile: the switch shows the EFFECTIVE value; setting it
 * back to the inherited one stores null again (simplifiedOfferLineOverrideFor).
 */
function SimplifiedOfferLineOverrideRule({ control, mode }: SimplifiedOfferLineOverrideRuleProps) {
  const { t } = useTranslation()
  const { override, inherited, effective } = useSimplifiedOfferLineInheritance(control, mode)
  const sourceName = inherited?.sourceCategory.name ?? ''
  const forced = override !== null

  let description = t('productCategories.form.simplifiedOfferLineHint')
  if (forced) {
    description = t('productCategories.form.simplifiedOfferLineForcedHint', { category: sourceName })
  } else if (inherited !== null) {
    description = t('productCategories.form.simplifiedOfferLineInheritedHint', { category: sourceName })
  }

  return (
    <ProductCategoryRuleCard icon={ListChecks} active={effective}>
      <MetaField
        control={control}
        name="simplified_offer_line_override"
        metaKey="simplified_offer_line_override"
        layout="inline"
        label={t('productCategories.form.simplifiedOfferLine')}
        hint={t('productCategories.form.simplifiedOfferLineInfo')}
        hintLabel={t('productCategories.form.simplifiedOfferLineInfoLabel')}
        description={<FormDescription>{description}</FormDescription>}
      >
        {({ field, disabled }) => (
          <FormControl>
            <Switch
              checked={effective}
              onCheckedChange={(checked) =>
                inherited !== null && field.onChange(simplifiedOfferLineOverrideFor(checked, inherited))
              }
              disabled={disabled || inherited === null}
            />
          </FormControl>
        )}
      </MetaField>
      {forced ? (
        <Badge variant="outline" className="text-[11px] font-normal">
          {t('productCategories.form.simplifiedOfferLineForcedBadge')}
        </Badge>
      ) : null}
      {!forced && inherited !== null ? (
        <Badge variant="outline" className="text-[11px] font-normal">
          {t('productCategories.form.inheritedFrom', { category: sourceName })}
        </Badge>
      ) : null}
    </ProductCategoryRuleCard>
  )
}
