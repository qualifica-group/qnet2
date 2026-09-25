import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderHook, act } from '@testing-library/react'
import { useAdvancedFilters } from '@/features/table/advanced-filters/use-advanced-filters'
import type { AdvancedFilterDescriptor, AdvancedFilterValues } from '@/features/table/advanced-filters/types'

const mutateMock = vi.fn()

vi.mock('@/features/table/use-table-filters', () => ({
  useSaveTableFilters: () => ({ mutate: mutateMock, isPending: false }),
}))

/** Minimal, schema-valid descriptor fixture; each test overrides only what it exercises. */
function descriptor(
  overrides: Partial<AdvancedFilterDescriptor> & Pick<AdvancedFilterDescriptor, 'name' | 'type'>,
): AdvancedFilterDescriptor {
  return {
    label: 'table.test.label',
    order: 0,
    required: false,
    visible: true,
    width: 'md',
    multiple: false,
    ...overrides,
  }
}

beforeEach(() => {
  mutateMock.mockReset()
})

describe('useAdvancedFilters', () => {
  it('seeds draft/applied from defaultValue when there is no persisted state', () => {
    const descriptors = [descriptor({ name: 'status', type: 'text', defaultValue: 'active' })]

    const { result } = renderHook(() =>
      useAdvancedFilters({ domain: 'leads', descriptors, applied: null, onApplied: vi.fn() }),
    )

    expect(result.current.draft.status).toBe('active')
  })

  it('seeds draft/applied from the persisted appliedAdvancedFilters, overriding defaults', () => {
    const descriptors = [descriptor({ name: 'status', type: 'text', defaultValue: 'active' })]

    const { result } = renderHook(() =>
      useAdvancedFilters({
        domain: 'leads',
        descriptors,
        applied: { status: 'won' },
        onApplied: vi.fn(),
      }),
    )

    expect(result.current.draft.status).toBe('won')
  })

  it('setFieldValue updates only the draft, leaving applied/getApplied untouched until apply()', () => {
    const descriptors = [descriptor({ name: 'status', type: 'text' })]
    const { result } = renderHook(() =>
      useAdvancedFilters({ domain: 'leads', descriptors, applied: null, onApplied: vi.fn() }),
    )

    act(() => result.current.setFieldValue('status', 'won'))

    expect(result.current.draft.status).toBe('won')
    expect(result.current.getApplied()).toEqual({})
  })

  it('apply() applies the draft, persists the active subset once, and refreshes once', () => {
    const descriptors = [descriptor({ name: 'status', type: 'text' })]
    const onApplied = vi.fn()
    const { result } = renderHook(() =>
      useAdvancedFilters({ domain: 'leads', descriptors, applied: null, onApplied }),
    )

    act(() => result.current.setFieldValue('status', 'won'))
    act(() => result.current.apply())

    expect(result.current.getApplied()).toEqual({ status: 'won' })
    expect(mutateMock).toHaveBeenCalledTimes(1)
    expect(mutateMock).toHaveBeenCalledWith({ advancedFilters: { status: 'won' } })
    expect(onApplied).toHaveBeenCalledTimes(1)
  })

  it('reset() reverts to defaults, persists an empty map once, and refreshes once', () => {
    const descriptors = [descriptor({ name: 'status', type: 'text', defaultValue: 'active' })]
    const onApplied = vi.fn()
    // Hoisted (not inlined in the renderHook callback): a fresh object literal
    // there would be a new reference every render, which is exactly the
    // unstable-`applied`-reference case the hook's content-compare guards.
    const applied = { status: 'won' }
    const { result } = renderHook(() =>
      useAdvancedFilters({ domain: 'leads', descriptors, applied, onApplied }),
    )

    act(() => result.current.reset())

    expect(result.current.draft.status).toBe('active')
    expect(mutateMock).toHaveBeenCalledWith({ advancedFilters: {} })
    expect(onApplied).toHaveBeenCalledTimes(1)
  })

  it('disables Apply while a required field is empty, and does not apply/refresh on attempt', () => {
    const descriptors = [descriptor({ name: 'status', type: 'text', required: true })]
    const onApplied = vi.fn()
    const { result } = renderHook(() =>
      useAdvancedFilters({ domain: 'leads', descriptors, applied: null, onApplied }),
    )

    expect(result.current.canApply).toBe(false)
    expect(result.current.isFieldInvalid(descriptors[0])).toBe(true)

    act(() => result.current.apply())

    expect(mutateMock).not.toHaveBeenCalled()
    expect(onApplied).not.toHaveBeenCalled()
  })

  it('enables Apply once the required field is filled', () => {
    const descriptors = [descriptor({ name: 'status', type: 'text', required: true })]
    const { result } = renderHook(() =>
      useAdvancedFilters({ domain: 'leads', descriptors, applied: null, onApplied: vi.fn() }),
    )

    act(() => result.current.setFieldValue('status', 'won'))

    expect(result.current.canApply).toBe(true)
  })

  it('disables a dependent field while its parent is empty, and clears it once the parent changes', () => {
    const descriptors = [
      descriptor({ name: 'project', type: 'relation' }),
      descriptor({
        name: 'campaign',
        type: 'relation',
        dependency: { on: 'project' },
      }),
    ]
    const { result } = renderHook(() =>
      useAdvancedFilters({ domain: 'projects', descriptors, applied: null, onApplied: vi.fn() }),
    )

    expect(result.current.isFieldDisabled(descriptors[1])).toBe(true)

    act(() => result.current.setFieldValue('campaign', 7))
    act(() => result.current.setFieldValue('project', 1))

    expect(result.current.isFieldDisabled(descriptors[1])).toBe(false)
    // Changing the parent clears whatever the (disabled) child previously held.
    expect(result.current.draft.campaign).toBeNull()
  })

  it('resolves dependencyParamsFor to the parent value keyed by `dependency.param`', () => {
    const descriptors = [
      descriptor({ name: 'project', type: 'relation' }),
      descriptor({
        name: 'campaign',
        type: 'relation',
        dependency: { on: 'project', param: 'project_id' },
      }),
    ]
    const { result } = renderHook(() =>
      useAdvancedFilters({ domain: 'projects', descriptors, applied: null, onApplied: vi.fn() }),
    )

    expect(result.current.dependencyParamsFor(descriptors[1])).toBeUndefined()

    act(() => result.current.setFieldValue('project', 12))

    expect(result.current.dependencyParamsFor(descriptors[1])).toEqual({ project_id: 12 })
  })

  it('counts only filters whose applied value differs from their default', () => {
    const descriptors = [
      descriptor({ name: 'status', type: 'text', defaultValue: 'active' }),
      descriptor({ name: 'notes', type: 'text' }),
    ]
    const { result } = renderHook(() =>
      useAdvancedFilters({
        domain: 'leads',
        descriptors,
        applied: { status: 'active', notes: '' },
        onApplied: vi.fn(),
      }),
    )

    expect(result.current.activeCount).toBe(0)

    act(() => result.current.setFieldValue('status', 'won'))
    act(() => result.current.apply())

    expect(result.current.activeCount).toBe(1)
  })

  it('exposes the active values map alongside the count', () => {
    const descriptors = [descriptor({ name: 'status', type: 'text' })]
    const { result } = renderHook(() =>
      useAdvancedFilters({ domain: 'leads', descriptors, applied: null, onApplied: vi.fn() }),
    )

    act(() => result.current.setFieldValue('status', 'won'))
    act(() => result.current.apply())

    expect(result.current.activeValues).toEqual({ status: 'won' })
  })

  it('applyValues() restores an externally-supplied set (e.g. a saved filter view), persists and refreshes once (spec AC-009)', () => {
    const descriptors = [
      descriptor({ name: 'status', type: 'text' }),
      descriptor({ name: 'notes', type: 'text', defaultValue: 'n/a' }),
    ]
    const onApplied = vi.fn()
    const { result } = renderHook(() =>
      useAdvancedFilters({ domain: 'leads', descriptors, applied: null, onApplied }),
    )

    act(() => result.current.applyValues({ status: 'won' }))

    // The unspecified `notes` falls back to its own default, like the initial seed.
    expect(result.current.draft).toEqual({ status: 'won', notes: 'n/a' })
    expect(result.current.getApplied()).toEqual({ status: 'won' })
    expect(mutateMock).toHaveBeenCalledWith({ advancedFilters: { status: 'won' } })
    expect(onApplied).toHaveBeenCalledTimes(1)
  })

  it('applyValues() bypasses required-gating (a saved view is assumed already valid)', () => {
    const descriptors = [descriptor({ name: 'status', type: 'text', required: true })]
    const onApplied = vi.fn()
    const { result } = renderHook(() =>
      useAdvancedFilters({ domain: 'leads', descriptors, applied: null, onApplied }),
    )

    expect(result.current.canApply).toBe(false)

    act(() => result.current.applyValues({ status: 'won' }))

    expect(result.current.draft.status).toBe('won')
    expect(onApplied).toHaveBeenCalledTimes(1)
  })

  describe('override (spec 0151 D-2, AC-008)', () => {
    it('wins over the persisted applied state for getApplied(), without persisting', () => {
      const descriptors = [
        descriptor({ name: 'status', type: 'enum', defaultValue: 'open', required: true }),
        descriptor({ name: 'assignment', type: 'enum' }),
      ]
      const { result } = renderHook(() =>
        useAdvancedFilters({
          domain: 'tasks',
          descriptors,
          applied: { status: 'open' },
          onApplied: vi.fn(),
          override: { status: 'open', assignment: 'assigned_to_me' },
        }),
      )

      // `status: open` equals its default, so it is not part of the "active"
      // subset sent to the server — same rule as a normal Apply (spec 0151:
      // the backend already defaults to open when the key is omitted).
      expect(result.current.getApplied()).toEqual({ assignment: 'assigned_to_me' })
      expect(mutateMock).not.toHaveBeenCalled()
    })

    it('a non-default override value is part of the active subset', () => {
      const descriptors = [
        descriptor({ name: 'status', type: 'enum', defaultValue: 'open', required: true }),
        descriptor({ name: 'assignment', type: 'enum' }),
      ]
      const { result } = renderHook(() =>
        useAdvancedFilters({
          domain: 'tasks',
          descriptors,
          applied: null,
          onApplied: vi.fn(),
          override: { status: 'in_validation', assignment: 'assigned_by_me' },
        }),
      )

      expect(result.current.getApplied()).toEqual({
        status: 'in_validation',
        assignment: 'assigned_by_me',
      })
    })

    it('apply() persists the draft and clears the override', () => {
      const descriptors = [
        descriptor({ name: 'status', type: 'enum', defaultValue: 'open', required: true }),
        descriptor({ name: 'assignment', type: 'enum' }),
      ]
      const onApplied = vi.fn()
      const onOverrideCleared = vi.fn()
      const { result } = renderHook(() =>
        useAdvancedFilters({
          domain: 'tasks',
          descriptors,
          applied: null,
          onApplied,
          override: { status: 'open', assignment: 'assigned_to_me' },
          onOverrideCleared,
        }),
      )

      act(() => result.current.apply())

      expect(mutateMock).toHaveBeenCalledTimes(1)
      expect(mutateMock).toHaveBeenCalledWith({
        advancedFilters: { assignment: 'assigned_to_me' },
      })
      expect(onApplied).toHaveBeenCalledTimes(1)
      expect(onOverrideCleared).toHaveBeenCalledTimes(1)
    })

    it('reset() persists an empty map and clears the override', () => {
      const descriptors = [descriptor({ name: 'status', type: 'enum', defaultValue: 'open', required: true })]
      const onOverrideCleared = vi.fn()
      const { result } = renderHook(() =>
        useAdvancedFilters({
          domain: 'tasks',
          descriptors,
          applied: null,
          onApplied: vi.fn(),
          override: { status: 'in_validation' },
          onOverrideCleared,
        }),
      )

      act(() => result.current.reset())

      expect(mutateMock).toHaveBeenCalledWith({ advancedFilters: {} })
      expect(onOverrideCleared).toHaveBeenCalledTimes(1)
    })

    it('clearing the override back to null/undefined does not revert the just-applied state', () => {
      const descriptors = [
        descriptor({ name: 'status', type: 'enum', defaultValue: 'open', required: true }),
        descriptor({ name: 'assignment', type: 'enum' }),
      ]
      const onApplied = vi.fn()
      const { result, rerender } = renderHook(
        ({ override, applied }: { override: AdvancedFilterValues | null; applied: AdvancedFilterValues | null }) =>
          useAdvancedFilters({ domain: 'tasks', descriptors, applied, onApplied, override }),
        {
          initialProps: {
            override: { status: 'open', assignment: 'assigned_to_me' } as AdvancedFilterValues | null,
            applied: null as AdvancedFilterValues | null,
          },
        },
      )

      act(() => result.current.apply())
      expect(result.current.getApplied()).toEqual({ assignment: 'assigned_to_me' })

      // Simulate the caller stripping the URL params: `override` clears, but
      // the config's persisted `applied` (still the stale pre-visit value)
      // has not round-tripped back yet.
      rerender({ override: null, applied: null })

      expect(result.current.getApplied()).toEqual({ assignment: 'assigned_to_me' })
    })

    it('a value unknown to the caller is simply absent from the override, so it is ignored', () => {
      const descriptors = [descriptor({ name: 'status', type: 'enum', defaultValue: 'open', required: true })]
      const { result } = renderHook(() =>
        useAdvancedFilters({
          domain: 'tasks',
          descriptors,
          applied: null,
          onApplied: vi.fn(),
          override: { status: 'open' },
        }),
      )

      expect(result.current.getApplied()).toEqual({})
    })
  })

  // Spec 0158 D-5: the chip row's per-advanced-filter "x".
  describe('clearField', () => {
    it('resets one applied field to its default, persists, and refreshes — leaving others untouched', () => {
      const descriptors = [
        descriptor({ name: 'status', type: 'enum', defaultValue: 'open', required: true }),
        descriptor({ name: 'priority', type: 'text' }),
      ]
      const onApplied = vi.fn()
      const { result } = renderHook(() =>
        useAdvancedFilters({
          domain: 'tasks',
          descriptors,
          applied: { status: 'closed', priority: 'high' },
          onApplied,
        }),
      )

      act(() => result.current.clearField('status'))

      expect(result.current.getApplied()).toEqual({ priority: 'high' })
      expect(result.current.draft.status).toBe('open')
      expect(mutateMock).toHaveBeenCalledWith({ advancedFilters: { priority: 'high' } })
      expect(onApplied).toHaveBeenCalledTimes(1)
    })

    it('clears a non-required field to null (absent from the active subset)', () => {
      const descriptors = [descriptor({ name: 'priority', type: 'text' })]
      const { result } = renderHook(() =>
        useAdvancedFilters({ domain: 'tasks', descriptors, applied: { priority: 'high' }, onApplied: vi.fn() }),
      )

      act(() => result.current.clearField('priority'))

      expect(result.current.getApplied()).toEqual({})
    })
  })
})
