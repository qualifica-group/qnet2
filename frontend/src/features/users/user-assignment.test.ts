import { describe, expect, it } from 'vitest'
import { summarizeAssignment } from '@/features/users/user-assignment'

/**
 * The verdict the user form and the user scheda both render (user directive
 * 2026-09-11). It must read the configuration the way the SERVER reads it
 * (`AssignmentCandidates`): Sede and competence both filter, and both filter
 * to nothing when empty — since spec 0111 rev.2 (D-9) an empty competence set
 * is no longer a jolly.
 */

const COMPLETE_ROW = { business_function_id: 4, product_category_id: 21 }

describe('summarizeAssignment', () => {
  it('counts a complete configuration and calls it assignable', () => {
    const summary = summarizeAssignment({
      competenceRows: [COMPLETE_ROW, { business_function_id: 5, product_category_id: 22 }],
      primarySiteId: 8,
      remoteSiteIds: [9, 10],
    })

    expect(summary.competenceCount).toBe(2)
    expect(summary.physicalSiteCount).toBe(1)
    expect(summary.remoteSiteCount).toBe(2)
    expect(summary.siteCount).toBe(3)
    expect(summary.blockers).toEqual([])
    expect(summary.assignable).toBe(true)
  })

  it('does not count a half-filled competence row: it covers nothing', () => {
    const summary = summarizeAssignment({
      competenceRows: [COMPLETE_ROW, { business_function_id: 5, product_category_id: null }],
      primarySiteId: 8,
      remoteSiteIds: [],
    })

    expect(summary.competenceCount).toBe(1)
    expect(summary.assignable).toBe(true)
  })

  it('blocks on competence when every row is incomplete', () => {
    const summary = summarizeAssignment({
      competenceRows: [{ business_function_id: null, product_category_id: null }],
      primarySiteId: 8,
      remoteSiteIds: [],
    })

    expect(summary.competenceCount).toBe(0)
    expect(summary.blockers).toEqual(['competence'])
    expect(summary.assignable).toBe(false)
  })

  it('treats a remote site as a full Sede membership, like the server does', () => {
    const summary = summarizeAssignment({
      competenceRows: [COMPLETE_ROW],
      primarySiteId: null,
      remoteSiteIds: [9],
    })

    expect(summary.physicalSiteCount).toBe(0)
    expect(summary.siteCount).toBe(1)
    expect(summary.assignable).toBe(true)
  })

  it('blocks on the Sede when there is neither a physical nor a remote one', () => {
    const summary = summarizeAssignment({
      competenceRows: [COMPLETE_ROW],
      primarySiteId: null,
      remoteSiteIds: [],
    })

    expect(summary.blockers).toEqual(['site'])
    expect(summary.assignable).toBe(false)
  })

  it('reports BOTH blockers for an unconfigured person, competence first', () => {
    const summary = summarizeAssignment({
      competenceRows: [],
      primarySiteId: null,
      remoteSiteIds: [],
    })

    expect(summary.blockers).toEqual(['competence', 'site'])
    expect(summary.assignable).toBe(false)
  })
})
