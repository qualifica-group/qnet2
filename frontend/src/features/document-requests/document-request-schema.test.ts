import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  buildCreateDocumentRequestSchema,
  buildEditDocumentRequestSchema,
  toCreateDocumentRequestPayload,
  type DocumentRequestFormValues,
} from '@/features/document-requests/document-request-schema'
import { requestActions } from '@/features/document-requests/document-request-status'
import { buildRequest } from '@/features/document-requests/document-request-fixtures'
import { documentRequests as en } from '@/i18n/locales/en-document-requests'
import { documentRequests as itLocale } from '@/i18n/locales/it-document-requests'

const VALID: DocumentRequestFormValues = {
  document_checklist_id: 3,
  due_date: '2999-01-01',
  recipient_email: 'a@example.com',
  message: 'ciao',
  send: true,
}

describe('document request schema', () => {
  const t = i18n.t.bind(i18n)

  it('accepts empty optional fields and rejects a missing checklist on create only', () => {
    const optionalEmpty = { ...VALID, due_date: '', message: '' }
    expect(buildCreateDocumentRequestSchema(t).safeParse(optionalEmpty).success).toBe(true)
    expect(buildCreateDocumentRequestSchema(t).safeParse({ ...VALID, document_checklist_id: null }).success).toBe(false)
    expect(buildEditDocumentRequestSchema(t).safeParse({ ...VALID, document_checklist_id: null }).success).toBe(true)
  })

  it('requires the recipient email on create and edit (AC-021)', () => {
    for (const schema of [buildCreateDocumentRequestSchema(t), buildEditDocumentRequestSchema(t)]) {
      const result = schema.safeParse({ ...VALID, recipient_email: '  ' })
      expect(result.success).toBe(false)
      expect(result.error?.issues[0]).toMatchObject({ path: ['recipient_email'], message: "Indica l'email del destinatario." })
    }
  })

  it('mirrors the server limits: past date, email, 2000 characters', () => {
    const schema = buildEditDocumentRequestSchema(t)
    expect(schema.safeParse({ ...VALID, due_date: '2000-01-01' }).success).toBe(false)
    expect(schema.safeParse({ ...VALID, recipient_email: 'x' }).success).toBe(false)
    expect(schema.safeParse({ ...VALID, message: 'x'.repeat(2000) }).success).toBe(true)
    expect(schema.safeParse({ ...VALID, message: 'x'.repeat(2001) }).success).toBe(false)
  })

  it('maps empty optional strings to null and trims the email in the payload', () => {
    expect(toCreateDocumentRequestPayload({ ...VALID, due_date: '', recipient_email: ' a@example.com ', message: '' })).toEqual({
      document_checklist_id: 3,
      due_date: null,
      recipient_email: 'a@example.com',
      message: null,
      send: true,
    })
  })
})

describe('requestActions', () => {
  it('allows reminders only on sent or in progress, edit and cancel until a terminal state', () => {
    expect(requestActions(buildRequest({ status: 'sent' }))).toEqual({ edit: true, remind: true, cancel: true, refresh: true, complete: false })
    expect(requestActions(buildRequest({ status: null }))).toMatchObject({ edit: true, remind: false, cancel: true })
    expect(requestActions(buildRequest({ status: 'cancelled' }))).toMatchObject({ edit: false, remind: false, cancel: false })
    expect(requestActions(buildRequest({ status: 'expired' }))).toMatchObject({ edit: false, cancel: false })
  })

  it('hides refresh on a request that never reached ArchivioSmart, but keeps edit and cancel (AC-022)', () => {
    expect(requestActions(buildRequest({ status: null, delivery_status: 'failed' }))).toMatchObject({ edit: true, cancel: true, refresh: false })
    expect(requestActions(buildRequest({ status: null, delivery_status: 'pending' }))).toMatchObject({ refresh: true })
  })
})

describe('documentRequests i18n parity (spec 0428)', () => {
  type Tree = { [key: string]: string | Tree }
  const leaves = (tree: Tree, prefix = ''): string[] =>
    Object.entries(tree).flatMap(([key, value]) =>
      typeof value === 'string' ? [`${prefix}${key}`] : leaves(value, `${prefix}${key}.`),
    )

  it('has the same keys in it and en', () => {
    expect(leaves(itLocale).sort()).toEqual(leaves(en).sort())
  })
})
