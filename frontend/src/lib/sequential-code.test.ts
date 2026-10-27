import { describe, expect, it } from 'vitest'
import { submittedCode } from '@/lib/sequential-code'

describe('submittedCode', () => {
  it('omits the code still equal to the server suggestion, so the server assigns the next free one', () => {
    expect(submittedCode('QUO-0012', 'QUO-0012')).toEqual({})
  })

  it('omits the suggestion even with surrounding blanks', () => {
    expect(submittedCode('  QUO-0012 ', 'QUO-0012')).toEqual({})
  })

  it('omits a blank code', () => {
    expect(submittedCode('   ', 'QUO-0012')).toEqual({})
    expect(submittedCode('', undefined)).toEqual({})
  })

  it('sends a code the user typed, trimmed', () => {
    expect(submittedCode('  ACME-2026  ', 'QUO-0012')).toEqual({ code: 'ACME-2026' })
  })

  it('sends a typed code when there was no suggestion', () => {
    expect(submittedCode('QUO-0099', undefined)).toEqual({ code: 'QUO-0099' })
  })
})
