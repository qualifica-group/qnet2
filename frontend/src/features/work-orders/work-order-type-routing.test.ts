import { describe, expect, it } from 'vitest'
import { workOrderModuleFor, workOrderPath, workOrderRedirectPath } from '@/features/work-orders/work-order-type-routing'

/** "Commesse" and "Commesse di Progetto": one page per type (user decision 2026-11-25). */
describe('work order type routing', () => {
  it('opens a project work order in "Commesse di Progetto" and any other in "Commesse"', () => {
    expect(workOrderModuleFor('project').domain).toBe('project-work-orders')
    expect(workOrderModuleFor('processing').domain).toBe('work-orders')
    expect(workOrderModuleFor(undefined).domain).toBe('work-orders')
    expect(workOrderPath('project', 4)).toBe('/project-work-orders/4')
    expect(workOrderPath('processing', 4)).toBe('/work-orders/4')
  })

  it('sends a work order reached on the other module page to its own, keeping the query', () => {
    expect(workOrderRedirectPath('project', 4, '/work-orders/4', '?tab=notes')).toBe('/project-work-orders/4?tab=notes')
    expect(workOrderRedirectPath('processing', 4, '/project-work-orders/4', '')).toBe('/work-orders/4')
  })

  it('leaves alone the right page and any surface that is not a page', () => {
    expect(workOrderRedirectPath('project', 4, '/project-work-orders/4', '')).toBeNull()
    expect(workOrderRedirectPath('processing', 4, '/work-orders/4', '')).toBeNull()
    expect(workOrderRedirectPath('project', 4, '/contracts/12', '')).toBeNull()
  })
})
