import { describe, expect, it } from 'vitest'
import { moduleDefinition } from '@/features/work-orders/work-order-module'

describe('work orders module registry entry', () => {
  // The create form draws its own "Crea commessa" band (spec 0196): the
  // page/Sheet host must not add a second heading above it.
  it('owns its form header', () => {
    expect(moduleDefinition.formOwnsHeader).toBe(true)
  })
})
