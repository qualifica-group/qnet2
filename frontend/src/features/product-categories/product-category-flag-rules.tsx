import { useController, type Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { ChartNoAxesColumn, MousePointerClick, Power } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { FormControl, FormDescription } from '@/components/ui/form'
import { Switch } from '@/components/ui/switch'
import { MetaField } from '@/features/authorization/MetaField'
import { ProductCategoryRuleCard } from '@/features/product-categories/product-category-rule-card'
import { reportableOverrideFor } from '@/features/product-categories/reportable-inheritance'
import type { ProductCategoryFormMode } from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'
import { useInactiveAncestor } from '@/features/product-categories/use-inactive-ancestor'
import { useReportableInheritance } from '@/features/product-categories/use-reportable-inheritance'

interface SelectableRuleProps {
  control: Control<ProductCategoryFormValues>
}

/**
 * `is_selectable` as a rule tile. Per-node and never inherited (spec 0074), so
 * it needs none of the root-owned chrome — defined at module level, never
 * inside the section component.
 */
export function SelectableRule({ control }: SelectableRuleProps) {
  const { t } = useTranslation()
  const { field } = useController({ control, name: 'is_selectable' })

  return (
    <ProductCategoryRuleCard icon={MousePointerClick} active={field.value}>
      <MetaField
        control={control}
        name="is_selectable"
        metaKey="is_selectable"
        layout="inline"
        label={t('productCategories.form.isSelectable')}
        hint={t('productCategories.form.isSelectableInfo')}
        hintLabel={t('productCategories.form.isSelectableInfoLabel')}
        description={
          <FormDescription>{t('productCategories.form.isSelectableHint')}</FormDescription>
        }
      >
        {({ field: switchField, disabled }) => (
          <FormControl>
            <Switch
              checked={switchField.value}
              onCheckedChange={switchField.onChange}
              disabled={disabled}
            />
          </FormControl>
        )}
      </MetaField>
    </ProductCategoryRuleCard>
  )
}

/**
 * `is_active` as a rule tile (spec 0208). The switch edits the category's OWN
 * flag; when an ancestor is inactive the whole branch is effectively inactive
 * whatever this flag says, so the tile names the ancestor responsible.
 */
export function ActiveRule({ control }: SelectableRuleProps) {
  const { t } = useTranslation()
  const { field } = useController({ control, name: 'is_active' })
  const inactiveAncestor = useInactiveAncestor(control)

  return (
    <ProductCategoryRuleCard icon={Power} active={field.value}>
      <MetaField
        control={control}
        name="is_active"
        metaKey="is_active"
        layout="inline"
        label={t('productCategories.form.isActive')}
        hint={t('productCategories.form.isActiveInfo')}
        hintLabel={t('productCategories.form.isActiveInfoLabel')}
        description={
          <>
            <FormDescription>{t('productCategories.form.isActiveHint')}</FormDescription>
            {inactiveAncestor !== null ? (
              <p className="text-xs font-medium text-muted-foreground" role="status">
                {t('productCategories.form.isActiveInheritedNotice', {
                  category: inactiveAncestor.name,
                })}
              </p>
            ) : null}
          </>
        }
      >
        {({ field: switchField, disabled }) => (
          <FormControl>
            <Switch
              checked={switchField.value}
              onCheckedChange={switchField.onChange}
              disabled={disabled}
            />
          </FormControl>
        )}
      </MetaField>
    </ProductCategoryRuleCard>
  )
}

interface ReportableRuleProps {
  control: Control<ProductCategoryFormValues>
  mode: ProductCategoryFormMode
}

/**
 * `is_reportable` as a rule tile (user directive 2026-09-18): a child inherits
 * its parent's value by default and the switch forces it. The switch shows the
 * EFFECTIVE value; setting it back to the inherited one stores null again
 * (reportableOverrideFor), so there is no separate reset control.
 */
export function ReportableRule({ control, mode }: ReportableRuleProps) {
  const { t } = useTranslation()
  const { override, inherited, effective } = useReportableInheritance(control, mode)
  const sourceName = inherited?.sourceCategory?.name ?? null
  // Forced = diverging from a value an ancestor sets. With no ancestor setting
  // it, an own value is where the flag STARTS for this branch, not a deviation.
  const forced = override !== null && sourceName !== null

  let description = t('productCategories.form.isReportableHint')
  if (forced) {
    description = t('productCategories.form.isReportableForcedHint', { category: sourceName })
  } else if (override !== null) {
    description = t('productCategories.form.isReportableOriginHint')
  } else if (sourceName !== null) {
    description = t('productCategories.form.isReportableInheritedHint', { category: sourceName })
  } else if (inherited !== null) {
    description = t('productCategories.form.isReportableNoSourceHint')
  }

  return (
    <ProductCategoryRuleCard icon={ChartNoAxesColumn} active={effective}>
      <MetaField
        control={control}
        name="is_reportable"
        metaKey="is_reportable"
        layout="inline"
        label={t('productCategories.form.isReportable')}
        hint={t('productCategories.form.isReportableInfo')}
        hintLabel={t('productCategories.form.isReportableInfoLabel')}
        description={<FormDescription>{description}</FormDescription>}
      >
        {({ field: switchField, disabled }) => (
          <FormControl>
            <Switch
              checked={effective}
              onCheckedChange={(checked) => switchField.onChange(reportableOverrideFor(checked, inherited))}
              disabled={disabled}
            />
          </FormControl>
        )}
      </MetaField>
      {forced ? (
        <Badge variant="outline" className="text-[11px] font-normal">
          {t('productCategories.form.isReportableForcedBadge')}
        </Badge>
      ) : null}
      {!forced && sourceName !== null ? (
        <Badge variant="outline" className="text-[11px] font-normal">
          {t('productCategories.form.inheritedFrom', { category: sourceName })}
        </Badge>
      ) : null}
    </ProductCategoryRuleCard>
  )
}
