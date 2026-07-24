import type {
  CustomFieldConfig,
  CustomFieldDescriptor,
  CustomFieldOption,
  CustomFieldRelation,
  CustomFieldRelationTarget,
} from '@/features/custom-fields/types'
import type { EffectiveAttribute } from '@/features/product-categories/types'

/**
 * Bridges a category's `EffectiveAttribute` (spec 0061, the product form's
 * dynamic-fields source) onto the `CUSTOM_FIELD_COMPONENT_REGISTRY`
 * type→component contract — the SAME bridge idea as
 * `features/request-management/request-attribute-adapter.ts`'s
 * `toCustomFieldDescriptor`, sourced from a different (but equally frozen)
 * backend shape so it stays independent of the Opportunity path (which must
 * not be touched, CLAUDE.md hard-invariant). Pure read adapter: never writes
 * back to the custom-fields feature.
 */

function toOption(option: EffectiveAttribute['options'][number]): CustomFieldOption {
  return { value: option.value, label: option.label, color: option.color, icon: option.icon }
}

function toRelation(relationTarget: CustomFieldRelationTarget | null): CustomFieldRelation | undefined {
  if (!relationTarget) {
    return undefined
  }
  return { for_select_resource: relationTarget.for_select_resource, cardinality: relationTarget.cardinality }
}

/**
 * Maps one `EffectiveAttribute` to the `CustomFieldDescriptor` shape the
 * registry's controls expect. `mandatory` stays `false`: required-ness is
 * driven by `attribute.is_required` at the call site (the `MetaField`'s
 * `required` prop and the dynamic Zod schema), not by the descriptor —
 * mirrors the Opportunity path exactly.
 */
export function toCustomFieldDescriptor(attribute: EffectiveAttribute): CustomFieldDescriptor {
  return {
    key: attribute.code,
    type: attribute.type,
    group: null,
    mandatory: false,
    label: attribute.name,
    source: 'custom',
    description: attribute.description,
    help_text: attribute.help_text,
    placeholder: attribute.placeholder,
    icon: attribute.icon,
    tab: null,
    sort_order: attribute.sort_order,
    config: attribute.config as CustomFieldConfig | null,
    options: attribute.options.map(toOption),
    relation: toRelation(attribute.relation_target),
  }
}
