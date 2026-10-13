import { describe, expect, it } from 'vitest'
import { buildRegistryTypePatch, readRegistryTypeTab } from '@/features/registries/use-registry-type-tab'

const set = (values: Array<string | null>) => ({ filterType: 'set', values })

describe('registry type tabs', () => {
  it.each([
    ['all', { registry_type: null }],
    ['individual', { registry_type: set(['individual']) }],
    ['company', { registry_type: set(['company']) }],
  ] as const)('builds the %s filter model patch', (tab, patch) => {
    expect(buildRegistryTypePatch(tab)).toEqual(patch)
  })

  it('reads the active tab back from the live grid model', () => {
    expect(readRegistryTypeTab({})).toBe('all')
    expect(readRegistryTypeTab({ registry_type: set([]) })).toBe('all')
    expect(readRegistryTypeTab({ registry_type: set(['individual']) })).toBe('individual')
    expect(readRegistryTypeTab({ registry_type: set(['company']) })).toBe('company')
  })

  it('selects no tab when the user customized the type filter', () => {
    expect(readRegistryTypeTab({ registry_type: set(['individual', 'company']) })).toBeNull()
    expect(readRegistryTypeTab({ registry_type: set([null]) })).toBeNull()
    expect(readRegistryTypeTab({ registry_type: set(['all']) })).toBeNull()
  })
})
