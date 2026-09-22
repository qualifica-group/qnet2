import { describe, expect, it } from 'vitest'
import type { HelpGuide } from '@/features/help/types'
import { searchHelpGuides } from '@/features/help/help-search-utils'

const GUIDE: HelpGuide = {
  key: 'leads',
  title: 'Lead',
  summary: 'Gestisci i lead in ingresso.',
  sections: [
    {
      id: 'chiamate',
      title: 'Gestione chiamate',
      blocks: [
        { type: 'paragraph', text: 'Registra ogni **telefonata** ricevuta.' },
        { type: 'list', items: ['Nome', 'Telefono'] },
        { type: 'table', headers: ['Campo', 'Note'], rows: [['Città', 'Facoltativa']] },
      ],
    },
    {
      id: 'import',
      title: 'Import lead',
      blocks: [{ type: 'note', text: 'Solo file CSV.' }],
    },
  ],
}

describe('searchHelpGuides (AC-005)', () => {
  it('is case-insensitive', () => {
    expect(searchHelpGuides([GUIDE], 'TELEFONATA')).toHaveLength(1)
  })

  it('is accent-insensitive', () => {
    expect(searchHelpGuides([GUIDE], 'citta')).toHaveLength(1)
    expect(searchHelpGuides([GUIDE], 'città')).toHaveLength(1)
  })

  it('matches a table header/cell', () => {
    const results = searchHelpGuides([GUIDE], 'facoltativa')
    expect(results).toEqual([
      { guideKey: 'leads', guideTitle: 'Lead', sectionId: 'chiamate', sectionTitle: 'Gestione chiamate' },
    ])
  })

  it('matches a section title even with no block hit', () => {
    const results = searchHelpGuides([GUIDE], 'import lead')
    expect(results).toEqual([
      { guideKey: 'leads', guideTitle: 'Lead', sectionId: 'import', sectionTitle: 'Import lead' },
    ])
  })

  it('falls back to the first section when only the guide title matches', () => {
    const titleOnlyGuide: HelpGuide = {
      key: 'opportunities',
      title: 'Opportunità',
      summary: 'Trattative commerciali.',
      sections: [{ id: 'creazione', title: 'Creazione', blocks: [{ type: 'paragraph', text: 'Compila i campi.' }] }],
    }
    const results = searchHelpGuides([titleOnlyGuide], 'Opportunità')
    expect(results).toEqual([
      { guideKey: 'opportunities', guideTitle: 'Opportunità', sectionId: 'creazione', sectionTitle: 'Creazione' },
    ])
  })

  it('returns no results for an unmatched query', () => {
    expect(searchHelpGuides([GUIDE], 'nessuna-corrispondenza')).toEqual([])
  })
})
