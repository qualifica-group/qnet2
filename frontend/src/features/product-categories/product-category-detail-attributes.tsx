import { useTranslation } from 'react-i18next'
import { DetailSection } from '@/components/detail/detail-panel'
import { Badge } from '@/components/ui/badge'
import { FIELD_TYPE_ICONS } from '@/features/custom-fields/field-type-icons'
import type {
  ProductCategoryAttributeAssignment,
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

interface CategoryAttributesContextSectionProps {
  title: string
  description: string
  /** Already filtered to this section's context. */
  own: ProductCategoryAttributeAssignment[]
  /** Already filtered to this section's context. */
  inherited: ProductCategoryInheritedAttribute[]
}

/**
 * One usage-context's read-only attribute list (spec 0061): the category's
 * own assignments for that context, then what it inherits — same own/inherited
 * distinction the editor keeps, split by `context` instead of merged into one
 * flat list. Renders nothing when the context has neither (zero-code for a
 * category with no product, or no quote, attributes assigned).
 */
export function CategoryAttributesContextSection({
  title,
  description,
  own,
  inherited,
}: CategoryAttributesContextSectionProps) {
  const { t } = useTranslation()

  if (own.length === 0 && inherited.length === 0) {
    return null
  }

  return (
    <DetailSection title={title}>
      <p className="mb-3 text-xs text-muted-foreground">{description}</p>

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
        <div className={own.length > 0 ? 'mt-3 flex flex-col gap-1.5 border-t pt-3' : 'flex flex-col gap-1.5'}>
          <p className="text-xs font-medium text-muted-foreground">
            {t('productCategories.form.inheritedAttributes')}
          </p>
          <ul className="flex flex-col gap-1.5">
            {inherited.map((attribute) => (
              <li
                key={attribute.attribute_id}
                className="flex items-center gap-2 text-sm text-muted-foreground"
              >
                <AttributeTypeBadge attribute={attribute} />
                <span>{attribute.name}</span>
              </li>
            ))}
          </ul>
        </div>
      )}
    </DetailSection>
  )
}
