import { describe, expect, it } from 'vitest'
import { createSseParser } from '@/features/ai-assistant/sse-parser'

describe('createSseParser', () => {
  it('rebuilds frames split in the middle of an event (AC-020)', () => {
    const parser = createSseParser()
    const frames = [
      ...parser.push('event: delta\nda'),
      ...parser.push('ta: {"text":"Ci'),
      ...parser.push('ao"}\n\nevent: delta\ndata: {"text":"!"}\n'),
      ...parser.push('\n'),
    ]
    expect(frames).toEqual([
      { event: 'delta', data: '{"text":"Ciao"}' },
      { event: 'delta', data: '{"text":"!"}' },
    ])
  })

  it('returns several events from one chunk, in order', () => {
    const parser = createSseParser()
    const frames = parser.push('event: a\ndata: 1\n\nevent: b\ndata: 2\n\n')
    expect(frames.map((frame) => frame.event)).toEqual(['a', 'b'])
  })

  it('handles CRLF split between chunks, comments and multi-line data', () => {
    const parser = createSseParser()
    const frames = [
      ...parser.push(': ping\r\nevent: x\r\ndata: l1\r'),
      ...parser.push('\ndata: l2\r\n\r\n'),
    ]
    expect(frames).toEqual([{ event: 'x', data: 'l1\nl2' }])
  })

  it('flushes a trailing frame on end()', () => {
    const parser = createSseParser()
    expect(parser.push('event: x\ndata: 1')).toEqual([])
    expect(parser.end()).toEqual([{ event: 'x', data: '1' }])
  })
})
