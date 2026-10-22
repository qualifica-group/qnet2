import { describe, expect, it } from 'vitest'
import { createInstance } from 'i18next'
import { en } from '@/i18n/locales/en'
import { it as itLocale } from '@/i18n/locales/it'
import { formatAbsenceMinutes } from '@/features/absences/absence-duration-format'

async function translator(lng: 'it' | 'en') {
  const i18n = createInstance()
  await i18n.init({
    lng,
    resources: { it: { translation: itLocale }, en: { translation: en } },
    interpolation: { escapeValue: false },
  })
  return i18n.t
}

describe('formatAbsenceMinutes', () => {
  it('formats days and hours in italian', async () => {
    const t = await translator('it')
    expect(formatAbsenceMinutes(600, 480, t)).toBe('1 g 2 h')
    expect(formatAbsenceMinutes(480, 480, t)).toBe('1 g')
    expect(formatAbsenceMinutes(90, 480, t)).toBe('1 h 30 min')
    expect(formatAbsenceMinutes(0, 480, t)).toBe('0 h')
    expect(formatAbsenceMinutes(-600, 480, t)).toBe('-1 g 2 h')
  })

  it('uses d/h/min in english', async () => {
    const t = await translator('en')
    expect(formatAbsenceMinutes(600, 480, t)).toBe('1 d 2 h')
    expect(formatAbsenceMinutes(90, 480, t)).toBe('1 h 30 min')
  })

  it('falls back to hours when the daily minutes are not positive', async () => {
    const t = await translator('it')
    expect(formatAbsenceMinutes(600, 0, t)).toBe('10 h')
  })

  it('keeps leftover minutes after days and hours', async () => {
    const t = await translator('it')
    expect(formatAbsenceMinutes(480 + 125, 480, t)).toBe('1 g 2 h 5 min')
  })
})
