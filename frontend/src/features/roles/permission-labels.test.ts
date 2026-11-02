import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { abilityLabel, fieldPermissionLabel } from '@/features/roles/permission-labels'

/**
 * Spec 0008 AC-010: the role field-permissions matrix must show readable
 * labels (not raw dot-path tokens) for the 11 new `personal_data.*` keys under
 * the `users` resource. `fieldPermissionLabel` resolves `users.form.<field>`,
 * so a dot-path field key (`personal_data.first_name`) walks the nested
 * `users.form.personal_data.first_name` i18n entry unchanged.
 */

const PERSONAL_DATA_FIELDS = [
  'type',
  'title',
  'first_name',
  'last_name',
  'company_name',
  'tax_code',
  'vat_number',
  'sdi_code',
  'birth_date',
  'contacts',
  'addresses',
] as const

describe('fieldPermissionLabel — personal_data.* keys (spec 0008)', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('en')
  })

  it.each(PERSONAL_DATA_FIELDS)('resolves a readable label for personal_data.%s', (field) => {
    const label = fieldPermissionLabel('users', `personal_data.${field}`, i18n)

    // Readable = a translated string, never the raw dot-path token itself.
    expect(label).not.toBe(`personal_data.${field}`)
    expect(label.length).toBeGreaterThan(0)
  })

  it('matches the existing personalData.form label for a shared field (no drift)', () => {
    expect(fieldPermissionLabel('users', 'personal_data.first_name', i18n)).toBe(
      i18n.t('personalData.form.firstName'),
    )
  })
})

describe('abilityLabel — viewCompetence (spec 0340 AC-018)', () => {
  it.each([
    ['it', 'Visualizza per competenza'],
    ['en', 'View by competence'],
  ])('labels quotes.viewCompetence in %s', async (lang, expected) => {
    await i18n.changeLanguage(lang)

    expect(abilityLabel('quotes.viewCompetence', i18n)).toBe(expected)
  })
})
