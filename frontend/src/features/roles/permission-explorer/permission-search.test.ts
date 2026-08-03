import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import type { PermissionCatalogueArea } from '@/features/roles/permission-catalogue-api'
import { filterPermissionCatalogue } from '@/features/roles/permission-explorer/permission-search'

/**
 * Spec 0076 AC-012/013/014: real-time search over the module label, the
 * technical permission name, the ability label and the field label
 * (including a custom one) — pure filter, no rendering.
 */

const AREAS: PermissionCatalogueArea[] = [
  {
    key: 'marketing-leads',
    label_key: 'navigation.marketingLeads',
    resources: [
      {
        resource: 'leads',
        label_key: 'navigation.leads',
        permissions: [
          { name: 'leads.viewAny', ability: 'viewAny' },
          { name: 'leads.export', ability: 'export' },
        ],
        fields: [
          { key: 'registry_id', type: 'relation', group: null, mandatory: false, custom: false, label: null },
          { key: 'custom.budget', type: 'number', group: 'Extra', mandatory: false, custom: true, label: 'Budget stimato' },
        ],
      },
    ],
  },
  {
    key: 'shared',
    label_key: 'permissions.areas.shared',
    resources: [
      {
        resource: 'notes',
        label_key: 'permissions.resources.notes',
        permissions: [{ name: 'notes.viewAny', ability: 'viewAny' }],
        fields: [],
      },
    ],
  },
]

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('filterPermissionCatalogue', () => {
  it('returns every area unchanged for an empty query', () => {
    expect(filterPermissionCatalogue(AREAS, '', i18n)).toBe(AREAS)
    expect(filterPermissionCatalogue(AREAS, '   ', i18n)).toBe(AREAS)
  })

  it('matches on the module label', () => {
    const result = filterPermissionCatalogue(AREAS, 'lead', i18n)
    expect(result.map((area) => area.key)).toEqual(['marketing-leads'])
    // The whole module — not a sub-filtered permission list — is kept.
    expect(result[0]!.resources[0]!.permissions).toHaveLength(2)
  })

  it('matches on the technical permission name (AC-013)', () => {
    const result = filterPermissionCatalogue(AREAS, 'leads.export', i18n)
    expect(result.map((area) => area.key)).toEqual(['marketing-leads'])
  })

  it('matches on the translated ability label', () => {
    const result = filterPermissionCatalogue(AREAS, 'export', i18n)
    expect(result.map((area) => area.key)).toEqual(['marketing-leads'])
  })

  it('matches on a custom field label (AC-013)', () => {
    const result = filterPermissionCatalogue(AREAS, 'budget', i18n)
    expect(result.map((area) => area.key)).toEqual(['marketing-leads'])
  })

  it('drops an area whose modules have no match at all (AC-014)', () => {
    const result = filterPermissionCatalogue(AREAS, 'nonexistent-term', i18n)
    expect(result).toEqual([])
  })

  it('is case-insensitive', () => {
    const result = filterPermissionCatalogue(AREAS, 'LEADS.EXPORT', i18n)
    expect(result.map((area) => area.key)).toEqual(['marketing-leads'])
  })
})
