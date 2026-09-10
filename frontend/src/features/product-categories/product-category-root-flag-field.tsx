import { useEffect } from 'react'
import type { LucideIcon } from 'lucide-react'
import { useController, type Control } from 'react-hook-form'
import { FormControl, FormDescription } from '@/components/ui/form'
import { Switch } from '@/components/ui/switch'
import { MetaField } from '@/features/authorization/MetaField'
import { ProductCategoryRuleCard } from '@/features/product-categories/product-category-rule-card'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

/** The value a branch ROOT imposes on a category, and which root it comes from. */
export interface RootFlagInheritance {
  value: boolean
  sourceCategory: { id: number; name: string }
}

/** The form paths carrying a ROOT-OWNED boolean rule. */
export type RootFlagName =
  | 'requires_quote'
  | 'single_quote_per_opportunity'
  | 'generates_contract'
  | 'simplified_offer_line'

interface ProductCategoryRootFlagFieldProps {
  control: Control<ProductCategoryFormValues>
  name: RootFlagName
  /** Resolved by the caller off the cached tree: null means THIS category owns the flag. */
  inheritance: RootFlagInheritance | null
  icon: LucideIcon
  label: string
  /** Explanatory text behind the (i) glyph next to the label. */
  hint: string
  hintLabel: string
  /** Shown under the label while the category owns the flag. */
  description: string
  /** Shown instead, naming the root, while the flag is inherited. */
  inheritedDescription: string
  /** Text of the "inherited from X" chip. */
  inheritedBadge: string
}

/**
 * A ROOT-OWNED boolean rule of a product category, rendered as one tile of
 * the "Regole di gestione" section.
 *
 * The value belongs to the branch ROOT: as soon as a parent is SELECTED (not
 * necessarily the saved one) the switch turns read-only and mirrors the
 * root's value, naming the root it comes from. Shared by every rule of that
 * shape (`requires_quote`, `single_quote_per_opportunity`) — the callers only
 * differ in which resolver feeds `inheritance` and in their copy.
 */
export function ProductCategoryRootFlagField({
  control,
  name,
  inheritance,
  icon,
  label,
  hint,
  hintLabel,
  description,
  inheritedDescription,
  inheritedBadge,
}: ProductCategoryRootFlagFieldProps) {
  const { field } = useController({ control, name })
  const inherited = inheritance !== null
  const value = inheritance ? inheritance.value : field.value

  // Keep the RHF value on what will actually be persisted once a parent makes
  // the flag inherited: the payload builders drop it for a non-root category,
  // but the control must not keep showing a value the save would discard.
  useEffect(() => {
    if (inheritance !== null && field.value !== inheritance.value) {
      field.onChange(inheritance.value)
    }
  }, [inheritance, field])

  return (
    <ProductCategoryRuleCard
      icon={icon}
      active={value}
      inheritedFrom={inheritance?.sourceCategory.name ?? null}
      inheritedLabel={inheritedBadge}
    >
      <MetaField
        control={control}
        name={name}
        metaKey={name}
        layout="inline"
        label={label}
        hint={hint}
        hintLabel={hintLabel}
        description={
          <FormDescription>{inherited ? inheritedDescription : description}</FormDescription>
        }
      >
        {({ disabled }) => (
          <FormControl>
            <Switch
              checked={value}
              onCheckedChange={field.onChange}
              disabled={disabled || inherited}
            />
          </FormControl>
        )}
      </MetaField>
    </ProductCategoryRuleCard>
  )
}
