import type { TFunction } from 'i18next'
import type { LayoutFormMode, LayoutFormScope } from '@/features/attributes/attribute-layout-types'
import type { UseAttributeLayoutLabels } from '@/features/product-categories/use-attribute-layout'
import type { AttributeContext } from '@/features/product-categories/types'

/**
 * Shared building blocks between the attribute-layout EDITOR (mounted in the
 * category edit form) and its read-only PREVIEW counterpart (mounted in the
 * category detail) — spec 0062. Kept in a plain `.ts` module (no JSX) so
 * `react-refresh/only-export-components` never flags these alongside a
 * component export.
 */

/** The two attribute usage contexts a layout can be configured for (spec 0061). */
export const ATTRIBUTE_LAYOUT_CONTEXTS: AttributeContext[] = ['product', 'opportunity']

/**
 * Mirrors backend `App\Enums\LayoutFormScope` (spec 0062 D3 revised): the
 * shared layout first — it is the default and the common case — then the
 * three per-mode overrides of it.
 */
export const ATTRIBUTE_LAYOUT_FORM_SCOPES: LayoutFormScope[] = ['all', 'create', 'edit', 'view']

/**
 * Which form mode the live preview renders a scope as. The shared scope has
 * no single mode of its own, so it previews as `edit`: the editable shape it
 * drives in both create and edit (`view` alone would render read-only).
 */
export function previewModeForScope(scope: LayoutFormScope): LayoutFormMode {
  return scope === 'all' ? 'edit' : scope
}

/** Builds `useAttributeLayout`'s toast/error copy from the `attributeLayout` i18next namespace. */
export function buildAttributeLayoutLabels(t: TFunction): UseAttributeLayoutLabels {
  return {
    saved: t('section.saved'),
    forbidden: t('section.forbidden'),
    saveError: t('section.saveError'),
    invalid: t('section.invalid'),
    reset: t('section.resetDone'),
  }
}
