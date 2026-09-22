import { describe, expect, it } from 'vitest'
import { completionTone } from '@/features/work-orders/task-board/task-board-completion-tone'

describe('completionTone', () => {
  it('goes red, amber, blue, green as the percentage grows', () => {
    expect(completionTone(0).indicator).toBe('bg-destructive')
    expect(completionTone(33).indicator).toBe('bg-destructive')
    expect(completionTone(34).indicator).toBe('bg-warning')
    expect(completionTone(66).indicator).toBe('bg-warning')
    expect(completionTone(67).indicator).toBe('bg-primary')
    expect(completionTone(99).indicator).toBe('bg-primary')
    expect(completionTone(100).indicator).toBe('bg-success')
  })

  it('pairs the bar with a track wash and a text colour of the same hue', () => {
    expect(completionTone(50)).toEqual({ indicator: 'bg-warning', track: 'bg-warning/15', text: 'text-warning' })
  })
})
