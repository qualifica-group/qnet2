import { describe, expect, it } from 'vitest'
import { moduleScreen } from '@/features/work-orders/work-order-screens'

describe('work orders module registry entry', () => {
  // The create form draws its own "Crea commessa" band (spec 0196): the
  // page/Sheet host must not add a second heading above it.
  it('owns its form header', () => {
    expect(moduleScreen.formOwnsHeader).toBe(true)
  })
})
