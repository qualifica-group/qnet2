import { describe, expect, it } from 'vitest'
import { extractPlainTextFromHtml, looksLikeHtmlValue } from '@/features/activity-log/activity-log-rich-text-value'

describe('looksLikeHtmlValue', () => {
  it('is true for a string that starts with a tag, leading whitespace included', () => {
    expect(looksLikeHtmlValue('<p>Ciao</p>')).toBe(true)
    expect(looksLikeHtmlValue('  <p>Ciao</p>')).toBe(true)
  })

  it('is false for plain text, even containing a stray angle bracket', () => {
    expect(looksLikeHtmlValue('Chiamare entro le 18 < domani')).toBe(false)
    expect(looksLikeHtmlValue('')).toBe(false)
  })
})

describe('extractPlainTextFromHtml (spec 0128 AC-025)', () => {
  it('extracts the visible text of a formatted fragment', () => {
    expect(extractPlainTextFromHtml('<p><strong>Ciao</strong></p>')).toBe('Ciao')
  })

  it('collapses whitespace left by block-level tags across multiple blocks', () => {
    expect(extractPlainTextFromHtml('<p>Uno</p><p>Due</p>')).toBe('Uno Due')
  })

  it('never mounts a script tag: only its (non-rendering) text content is read', () => {
    expect(extractPlainTextFromHtml('<p>Ok</p><script>alert(1)</script>')).toBe('Ok alert(1)')
  })

  it('returns an empty string for markup with no visible text', () => {
    expect(extractPlainTextFromHtml('<p></p>')).toBe('')
  })
})
