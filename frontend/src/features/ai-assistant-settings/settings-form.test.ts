import { describe, expect, it } from 'vitest'
import type { TFunction } from 'i18next'
import {
  buildSettingsSchema,
  toFormValues,
  toPayload,
} from '@/features/ai-assistant-settings/settings-form'
import type { AiAssistantSettings } from '@/features/ai-assistant-settings/types'

const t = ((key: string) => key) as unknown as TFunction

const saved: AiAssistantSettings = {
  enabled: true,
  model_id: 'deepseek/deepseek-v4-flash-0731',
  instructions: '## Ruolo',
  instructions_max_chars: 50,
  spend_cap_person_usd: '5.50',
  spend_cap_total_usd: null,
  routing: { zdr: true, data_collection: 'deny', require_parameters: true, sort: 'throughput' },
  openrouter_key_present: true,
  updated_at: null,
  updated_by: null,
}

describe('ai assistant settings form', () => {
  it('maps the saved settings to form values (null cap becomes empty text)', () => {
    expect(toFormValues(saved)).toMatchObject({ spend_cap_person_usd: '5.50', spend_cap_total_usd: '' })
  })

  it('sends empty caps as null and never sends the key flag or the audit fields', () => {
    const payload = toPayload({ ...toFormValues(saved), spend_cap_person_usd: '' })

    expect(payload.spend_cap_person_usd).toBeNull()
    expect(payload.spend_cap_total_usd).toBeNull()
    expect(Object.keys(payload).sort()).toEqual(
      ['enabled', 'instructions', 'model_id', 'routing', 'spend_cap_person_usd', 'spend_cap_total_usd'],
    )
  })

  it('rejects instructions over the server limit, bad caps and a missing model', () => {
    const schema = buildSettingsSchema(t, saved.instructions_max_chars)
    const result = schema.safeParse({
      ...toFormValues(saved),
      instructions: 'a'.repeat(51),
      spend_cap_total_usd: '-1',
      model_id: '',
    })

    expect(result.success).toBe(false)
    const paths = result.success ? [] : result.error.issues.map((issue) => issue.path.join('.'))
    expect(paths.sort()).toEqual(['instructions', 'model_id', 'spend_cap_total_usd'])
  })

  it('accepts a valid form', () => {
    expect(buildSettingsSchema(t, saved.instructions_max_chars).safeParse(toFormValues(saved)).success).toBe(true)
  })
})
