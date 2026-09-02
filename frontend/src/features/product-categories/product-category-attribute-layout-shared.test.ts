import { describe, expect, it } from 'vitest'
import { ATTRIBUTE_LAYOUT_CONTEXTS } from '@/features/product-categories/product-category-attribute-layout-shared'

/** Spec 0098 AC-020: the configurator's context selector iterates this list. */
describe('ATTRIBUTE_LAYOUT_CONTEXTS', () => {
  it('carries the three contexts, work_order included', () => {
    expect(ATTRIBUTE_LAYOUT_CONTEXTS).toEqual(['product', 'quote', 'work_order'])
  })
})
