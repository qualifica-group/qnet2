import { useTranslation } from 'react-i18next'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { Badge } from '@/components/ui/badge'
import { FIELD_TYPE_ICONS } from '@/features/custom-fields/field-type-icons'
import type {
  AttributeContext,
  ProductCategoryAttributeAssignment,
  ProductCategoryDetail,
  ProductCategoryInheritedAttribute,
} from '@/features/product-categories/types'

interface AttributeTypeBadgeProps {
  attribute: ProductCategoryAttributeAssignment | ProductCategoryInheritedAttribute
}

/** The assigned attribute's type glyph + label (shared with the custom fields catalogue). */
function AttributeTypeBadge({ attribute }: AttributeTypeBadgeProps) {
  const { t } = useTranslation()
  const Icon = FIELD_TYPE_ICONS[attribute.type]
  return (
    <Badge variant="outline" className="gap-1 text-xs">
      <Icon className="size-3.5" aria-hidden="true" />
      {t(`customFields.types.${attribute.type}`)}
    </Badge>
  )
}

interface ContextAttributesProps {
  title: string
  /** Already filtered to this context. */
  own: ProductCategoryAttributeAssignment[]
  /** Already filtered to this context. */
  inherited: ProductCategoryInheritedAttribute[]
}

/**
 * One usage-context's read-only attribute list (spec 0061): the category's own
 * assignments for that context, then what it inherits — the same own/inherited
 * distinction the editor keeps. Renders nothing when the context has neither.
 */
function ContextAttributes({ title, own, inherited }: ContextAttributesProps) {
  const { t } = useTranslation()

  if (own.length === 0 && inherited.length === 0) {
    return null
  }

  return (
    <div className="flex flex-col gap-1.5">
      <p className="text-xs font-semibold text-muted-foreground">{title}</p>

      {own.length > 0 && (
        <ul className="flex flex-col gap-1.5">
          {own.map((attribute) => (
            <li key={attribute.attribute_id} className="flex items-center gap-2 text-sm">
              <AttributeTypeBadge attribute={attribute} />
              <span className="text-foreground">{attribute.name}</span>
              {attribute.is_required && (
                <Badge variant="outline" className="text-xs">
                  {t('productCategories.form.isRequired')}
                </Badge>
              )}
            </li>
          ))}
        </ul>
      )}

      {inherited.length > 0 && (
        <div className={own.length > 0 ? 'flex flex-col gap-1.5 border-t pt-3' : 'flex flex-col gap-1.5'}>
          <p className="text-xs font-medium text-muted-foreground">
            {t('productCategories.form.inheritedAttributes')}
          </p>
          <ul className="flex flex-col gap-1.5">
            {inherited.map((attribute) => (
              <li key={attribute.attribute_id} className="flex items-center gap-2 text-sm text-muted-foreground">
                <AttributeTypeBadge attribute={attribute} />
                <span>{attribute.name}</span>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  )
}

/** The three context-scoped lists (Prodotto, Offerta, Commessa), never merged: the persisted value of the attributes block row. */
export function ProductCategoryAttributesOverview({ category }: { category: ProductCategoryDetail }) {
  const { t } = useTranslation()
  const contexts: { context: AttributeContext; title: string }[] = [
    { context: 'product', title: t('productCategories.form.sections.productAttributes.title') },
    { context: 'quote', title: t('productCategories.form.sections.quoteAttributes.title') },
    { context: 'work_order', title: t('productCategories.form.sections.workOrderAttributes.title') },
  ]

  if (category.attributes.length === 0 && category.inherited_attributes.length === 0) {
    return <DetailEmpty />
  }

  return (
    <div className="flex flex-col gap-4">
      {contexts.map(({ context, title }) => (
        <ContextAttributes
          key={context}
          title={title}
          own={category.attributes.filter((attribute) => attribute.context === context)}
          inherited={category.inherited_attributes.filter((attribute) => attribute.context === context)}
        />
      ))}
    </div>
  )
}
