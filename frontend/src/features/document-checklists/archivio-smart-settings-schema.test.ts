import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildArchivioSmartSettingsSchema } from '@/features/document-checklists/archivio-smart-settings-schema'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const schema = () => buildArchivioSmartSettingsSchema(i18n.t)

describe('buildArchivioSmartSettingsSchema (spec 0403)', () => {
  it('accepts a URL with an empty token (token unchanged)', () => {
    expect(schema().safeParse({ base_url: 'https://api.example.com/v1', token: '', webhook_secret: '' }).success).toBe(true)
  })

  it('accepts a token between 10 and 500 characters', () => {
    expect(schema().safeParse({ base_url: 'https://a.test', token: 'as_1234567', webhook_secret: '' }).success).toBe(true)
  })

  it('rejects a missing or invalid URL', () => {
    expect(schema().safeParse({ base_url: '', token: '', webhook_secret: '' }).success).toBe(false)
    expect(schema().safeParse({ base_url: 'not a url', token: '', webhook_secret: '' }).success).toBe(false)
  })

  it('rejects a base URL longer than 255 characters', () => {
    expect(schema().safeParse({ base_url: `https://a.test/${'x'.repeat(250)}`, token: '', webhook_secret: '' }).success).toBe(false)
  })

  it('treats the webhook secret like the token: empty = unchanged, 10 to 500 characters (spec 0428)', () => {
    const base = { base_url: 'https://a.test', token: '' }
    expect(schema().safeParse({ ...base, webhook_secret: 'whsec_12345' }).success).toBe(true)
    expect(schema().safeParse({ ...base, webhook_secret: 'short' }).success).toBe(false)
    expect(schema().safeParse({ ...base, webhook_secret: 'x'.repeat(501) }).success).toBe(false)
  })

  it('rejects a too short and a too long token', () => {
    expect(schema().safeParse({ base_url: 'https://a.test', token: 'short', webhook_secret: '' }).success).toBe(false)
    expect(schema().safeParse({ base_url: 'https://a.test', token: 'x'.repeat(501), webhook_secret: '' }).success).toBe(false)
  })
})
