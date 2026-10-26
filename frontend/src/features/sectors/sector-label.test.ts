import { describe, expect, it } from 'vitest'
import { sectorLabel } from '@/features/sectors/sector-label'

describe('sectorLabel (spec 0213)', () => {
  it('prefixes the code to the name', () => {
    expect(sectorLabel({ code: '07a', name: 'Prodotti in carta' })).toBe('07a — Prodotti in carta')
  })

  it('falls back to the name when the code is missing or blank', () => {
    expect(sectorLabel({ code: null, name: 'Varie' })).toBe('Varie')
    expect(sectorLabel({ code: '  ', name: 'Varie' })).toBe('Varie')
  })
})
