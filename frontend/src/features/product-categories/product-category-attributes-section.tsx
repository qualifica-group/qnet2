import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import { ListChecks } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { FormControl, FormDescription } from '@/components/ui/form'
import { Switch } from '@/components/ui/switch'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { AttributeAssignmentEditor } from '@/features/product-categories/attribute-assignment-editor'
import { useEffectiveAttributes } from '@/features/product-categories/use-effective-attributes'
import type { AttributeCatalogEntry } from '@/features/attributes/use-attribute-catalog'
import type {
  AttributeContext,
  EffectiveAttribute,
  ProductCategoryFormMode,
  ProductCategoryInheritedAttribute,
} from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

/** Hoisted so an opted-out context feeds a stable reference to `toInheritedAttributes`. */
const EMPTY_ATTRIBUTES: EffectiveAttribute[] = []

/** Hoisted for the same reason, on the create path (no category loaded yet). */
const EMPTY_KNOWN_ATTRIBUTES: AttributeCatalogEntry[] = []

/** The `inherits_*_attributes` field names, one per usage context — RHF path and authorization metadata key alike. */
const INHERITANCE_FIELD = {
  product: 'inherits_product_attributes',
  quote: 'inherits_quote_attributes',
  work_order: 'inherits_work_order_attributes',
} as const

/** Tags each effective-attributes result with the context it was fetched for, into the flat shape `AttributeAssignmentEditor` splits (spec 0061, extended by spec 0098). */
function toInheritedAttributes(
  product: EffectiveAttribute[] | undefined,
  quote: EffectiveAttribute[] | undefined,
  workOrder: EffectiveAttribute[] | undefined,
): ProductCategoryInheritedAttribute[] {
  const tag = (attributes: EffectiveAttribute[] | undefined, context: AttributeContext) =>
    (attributes ?? []).map((attribute) => ({
      attribute_id: attribute.id,
      code: attribute.code,
      name: attribute.name,
      type: attribute.type,
      is_required: attribute.is_required,
      context,
    }))
  return [...tag(product, 'product'), ...tag(quote, 'quote'), ...tag(workOrder, 'work_order')]
}

/**
 * The name/type the loaded category's own assignments already carry: the
 * editor labels its rows from here, so an attribute sitting outside the
 * picker's search window still shows its name instead of a bare `#id`.
 */
function toKnownAttributes(mode: ProductCategoryFormMode): AttributeCatalogEntry[] {
  if (mode.type !== 'edit') {
    return EMPTY_KNOWN_ATTRIBUTES
  }

  return mode.category.attributes.map((assignment) => ({
    id: assignment.attribute_id,
    code: assignment.code,
    name: assignment.name,
    type: assignment.type,
  }))
}

interface InheritanceToggleProps {
  control: Control<ProductCategoryFormValues>
  context: AttributeContext
}

/**
 * The per-context "inherit from parent" switch, rendered INSIDE the section it
 * governs (spec 0061 follow-up): each usage context carries its own barrier,
 * so the Product list can ignore the ancestry while the Offerta one keeps
 * inheriting. Defined at module level — never inside the form component.
 */
function InheritanceToggle({ control, context }: InheritanceToggleProps) {
  const { t } = useTranslation()

  return (
    <MetaField
      control={control}
      name={INHERITANCE_FIELD[context]}
      metaKey={INHERITANCE_FIELD[context]}
      label={t('productCategories.form.inheritsAttributes')}
      description={<FormDescription>{t('productCategories.form.inheritsAttributesHint')}</FormDescription>}
    >
      {({ field, disabled }) => (
        <FormControl>
          <Switch checked={field.value} onCheckedChange={field.onChange} disabled={disabled} />
        </FormControl>
      )}
    </MetaField>
  )
}

interface ProductCategoryAttributesSectionProps {
  control: Control<ProductCategoryFormValues>
  mode: ProductCategoryFormMode
  /** Current watched `parent_id`: what the category inherits comes from the SELECTED parent, not the saved one. */
  parentId: number | null
}

/**
 * The category's attribute assignments (spec 0061/0098): its own rows plus the
 * read-only list of what each usage context inherits from the ancestry.
 *
 * The inherited preview reacts to the per-context barrier immediately, not
 * after the save: opting a context out means it inherits nothing, and the list
 * must say so while the switch is still under the finger — only for the
 * context whose switch moved, the other two are untouched.
 */
export function ProductCategoryAttributesSection({
  control,
  mode,
  parentId,
}: ProductCategoryAttributesSectionProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()

  const inheritsProduct = useWatch({ control, name: 'inherits_product_attributes' })
  const inheritsQuote = useWatch({ control, name: 'inherits_quote_attributes' })
  const inheritsWorkOrder = useWatch({ control, name: 'inherits_work_order_attributes' })
  const knownAttributes = useMemo(() => toKnownAttributes(mode), [mode])
  const inheritedProductQuery = useEffectiveAttributes(parentId, 'product')
  const inheritedQuoteQuery = useEffectiveAttributes(parentId, 'quote')
  const inheritedWorkOrderQuery = useEffectiveAttributes(parentId, 'work_order')

  // Flat, all three contexts — `AttributeAssignmentEditor` splits it.
  const inherited: ProductCategoryInheritedAttribute[] = useMemo(
    () =>
      toInheritedAttributes(
        inheritsProduct ? inheritedProductQuery.data : EMPTY_ATTRIBUTES,
        inheritsQuote ? inheritedQuoteQuery.data : EMPTY_ATTRIBUTES,
        inheritsWorkOrder ? inheritedWorkOrderQuery.data : EMPTY_ATTRIBUTES,
      ),
    [
      inheritedProductQuery.data,
      inheritedQuoteQuery.data,
      inheritedWorkOrderQuery.data,
      inheritsProduct,
      inheritsQuote,
      inheritsWorkOrder,
    ],
  )

  if (!fieldPermission('attributes').visible) {
    return null
  }

  return (
    <FormSection
      icon={ListChecks}
      title={t('productCategories.form.sections.attributes.title')}
      description={t('productCategories.form.sections.attributes.description')}
    >
      <MetaField
        control={control}
        name="attributes"
        metaKey="attributes"
        label={t('productCategories.form.attributes')}
      >
        {({ field, disabled }) => (
          <AttributeAssignmentEditor
            value={field.value}
            onChange={field.onChange}
            known={knownAttributes}
            inherited={inherited}
            disabled={disabled}
            // A root category has no ancestry to inherit from: no switch to show.
            productInheritToggle={
              parentId !== null ? <InheritanceToggle control={control} context="product" /> : null
            }
            quoteInheritToggle={
              parentId !== null ? <InheritanceToggle control={control} context="quote" /> : null
            }
            workOrderInheritToggle={
              parentId !== null ? <InheritanceToggle control={control} context="work_order" /> : null
            }
          />
        )}
      </MetaField>
    </FormSection>
  )
}
