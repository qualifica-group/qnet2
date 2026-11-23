import { describe, expect, it } from 'vitest'
import { attributeOptions, initialValues, toPayload } from '@/integrations/formalab/category-settings-form'
import { buildCategorySettings } from '@/integrations/formalab/test-support'

describe('category settings form', () => {
  it('proposes the suggested attributes while nothing is mapped', () => {
    expect(initialValues(buildCategorySettings()).map).toEqual({
      label: 'gol_corso',
      exam_date: 'gol_data_esame',
      total_hours: '',
    })
  })

  it('starts from the effective map once there is one, without suggestions', () => {
    const settings = buildCategorySettings({ field_map: [{ formalab_field: 'label', attribute_code: 'gol_corso' }], own_field_map: true })

    expect(initialValues(settings).map).toEqual({ label: 'gol_corso', exam_date: '', total_hours: '' })
  })

  it('sends enabled only for a root, and null for a child inheriting its map', () => {
    const root = buildCategorySettings()
    expect(toPayload(root, { enabled: true, inherit: false, map: { label: 'gol_corso', exam_date: '', total_hours: '' } })).toEqual({
      enabled: true,
      field_map: [{ formalab_field: 'label', attribute_code: 'gol_corso' }],
    })

    const child = buildCategorySettings({ is_root: false })
    expect(initialValues(child).inherit).toBe(true)
    expect(toPayload(child, { enabled: true, inherit: true, map: { label: 'gol_corso' } })).toEqual({ field_map: null })
  })

  // spec 0426 D-3: the key field accepts only relation attributes towards the Formalab classes
  it('offers the key field only the relation attributes towards the Formalab classes', () => {
    const settings = buildCategorySettings({
      attributes: [
        { code: 'gol_corso', name: 'Classe', type: 'relation', relation_entity_type: 'formalab-classes' },
        { code: 'other_relation', name: 'Altra relazione', type: 'relation', relation_entity_type: 'registries' },
        { code: 'course_text', name: 'Corso testo', type: 'text', relation_entity_type: null },
      ],
    })
    const [keyField, examDate] = settings.fields

    expect(attributeOptions(keyField, settings.attributes).map((attribute) => attribute.code)).toEqual(['gol_corso'])
    expect(attributeOptions(examDate, settings.attributes).map((attribute) => attribute.code)).toEqual(['course_text'])
  })
})
