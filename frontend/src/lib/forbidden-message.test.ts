import { describe, expect, it } from 'vitest'
import { AxiosError, type AxiosResponse } from 'axios'
import i18n from '@/i18n'
import { apiErrorMessage, forbiddenMessage } from '@/lib/forbidden-message'

function httpError(status: number, data?: unknown): AxiosError {
  return new AxiosError('failed', undefined, undefined, undefined, { status, data } as AxiosResponse)
}

describe('forbiddenMessage', () => {
  it('composes the missing permission from the ability and module labels', () => {
    const error = httpError(403, {
      success: false,
      message: 'Non hai il permesso «opportunities.delete».',
      authorization: { reason: 'missing_permission', permission: 'opportunities.delete' },
    })

    expect(forbiddenMessage(error, i18n)).toBe('Non hai il permesso «Elimina» sul modulo «Opportunità».')
  })

  it('returns the server reason when the permission is not the cause', () => {
    const error = httpError(403, {
      success: false,
      message: 'Non puoi modificare questa commessa.',
      authorization: { reason: 'denied', permission: null },
    })

    expect(forbiddenMessage(error, i18n)).toBe('Non puoi modificare questa commessa.')
  })

  it('returns the message of an old-shape 403 without the authorization block', () => {
    expect(forbiddenMessage(httpError(403, { success: false, message: 'Vietato.' }), i18n)).toBe('Vietato.')
  })

  it('falls back to the generic sentence on an empty 403', () => {
    expect(forbiddenMessage(httpError(403), i18n)).toBe(i18n.t('common.forbidden'))
    expect(forbiddenMessage(httpError(403, { message: '  ' }), i18n)).toBe(i18n.t('common.forbidden'))
  })

  it.each([404, 500])('returns null for a %i', (status) => {
    expect(forbiddenMessage(httpError(status, { message: 'x' }), i18n)).toBeNull()
  })

  it('returns null for a network error and for non-axios values', () => {
    expect(forbiddenMessage(new AxiosError('Network Error'), i18n)).toBeNull()
    expect(forbiddenMessage(new Error('boom'), i18n)).toBeNull()
    expect(forbiddenMessage('403', i18n)).toBeNull()
  })
})

describe('apiErrorMessage', () => {
  it('returns the forbidden sentence on a 403 and the fallback otherwise', () => {
    expect(apiErrorMessage(httpError(403, { message: 'Vietato.' }), 'Errore', i18n)).toBe('Vietato.')
    expect(apiErrorMessage(httpError(500), 'Errore', i18n)).toBe('Errore')
    expect(apiErrorMessage(new Error('boom'), 'Errore', i18n)).toBe('Errore')
  })
})
