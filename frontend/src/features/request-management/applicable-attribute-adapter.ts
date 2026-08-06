import type {
  CustomFieldConfig,
  CustomFieldRelationTarget,
  CustomFieldType,
} from '@/features/custom-fields/types'
import type { AttributeContext, EffectiveAttribute } from '@/features/product-categories/types'
import type { ApplicableAttribute, ApplicableAttributeOption } from '@/features/request-management/types'

/**
 * Adapts `ApplicableAttribute` (spec 0049, the multi-category-merged shape
 * `ProductResource.applicable_attributes` and `QuoteResource.
 * applicable_attributes` both send) onto `EffectiveAttribute` (spec 0061),
 * the shape `AttributeLayoutRenderer` consumes (spec 0062). Shared by the
 * Offer form's dynamic fields (spec 0084) and the Product detail's read-only
 * attribute values section — both read the SAME `ApplicableAttribute` DTO,
 * so the mapping lives once here rather than twice. `inherited` is always
 * `false`: the renderer only reads it for the configurator's own palette,
 * never at runtime, so a fixed value is safe for both call sites.
 */

function toOption(option: ApplicableAttributeOption, index: number): EffectiveAttribute['options'][number] {
  return { value: option.value, label: option.label, color: option.color, icon: null, sort_order: index, is_default: false }
}

/**
 * `relation_target` only guarantees a loose JSON object (spec 0049
 * `data_contract`). Resolves it defensively — an attribute missing (or
 * malformed) `for_select_resource` renders no relation control rather than
 * crashing; `entity_type` is unused downstream (`toCustomFieldDescriptor`
 * only reads `for_select_resource`/`cardinality`) so it is left blank.
 */
function toRelationTarget(relationTarget: Record<string, unknown> | null): CustomFieldRelationTarget | null {
  const forSelectResource = relationTarget?.for_select_resource
  if (typeof forSelectResource !== 'string' || forSelectResource === '') {
    return null
  }
  return {
    entity_type: '',
    for_select_resource: forSelectResource,
    cardinality: relationTarget?.cardinality === 'many' ? 'many' : 'one',
  }
}

export function toEffectiveAttribute(
  attribute: ApplicableAttribute,
  context: AttributeContext = 'opportunity',
): EffectiveAttribute {
  return {
    id: attribute.id,
    code: attribute.code,
    name: attribute.name,
    type: attribute.type as CustomFieldType,
    description: attribute.description,
    help_text: attribute.help_text,
    placeholder: attribute.placeholder,
    icon: attribute.icon,
    config: attribute.config as CustomFieldConfig | null,
    relation_target: toRelationTarget(attribute.relation_target),
    is_required: attribute.is_required,
    sort_order: attribute.sort_order,
    inherited: false,
    context,
    options: attribute.options.map(toOption),
  }
}
