import { byName, childKey, personSearchText, type OrgNodeRef, type OrgTreeNode, type PersonNode } from '@/features/org-chart/tree-nodes'
import type { OrgDirectoryPerson } from '@/features/org-chart/types'

/**
 * Builds the People tree (spec 0273 D-3): roots are people with no manager in
 * the list; under each, their direct reports. A person with several managers
 * is attached under every one of them (own path key). The ancestor `path`
 * stops the walk from re-entering a person already on the branch, so a cycle
 * in the data can never loop (same model as the time-entries team tree).
 */
export function buildPeopleTree(people: OrgDirectoryPerson[]): OrgTreeNode[] {
  const byId = new Map(people.map((person) => [person.id, person]))
  const reportsOf = new Map<number, OrgDirectoryPerson[]>()
  people.forEach((person) => {
    person.manager_ids.forEach((managerId) => {
      if (!byId.has(managerId)) return
      reportsOf.set(managerId, [...(reportsOf.get(managerId) ?? []), person])
    })
  })

  function toNode(
    person: OrgDirectoryPerson,
    parentKey: string | null,
    parent: OrgNodeRef | null,
    path: ReadonlySet<number>,
  ): PersonNode {
    const key = childKey(parentKey, `p:${person.id}`)
    const nextPath = new Set(path).add(person.id)
    const self: OrgNodeRef = { kind: 'person', id: person.id }
    return {
      kind: 'person',
      id: person.id,
      key,
      parent,
      person,
      isResponsible: person.is_manager,
      functionNames: person.business_functions,
      searchText: personSearchText(person, person.business_functions),
      children: (reportsOf.get(person.id) ?? [])
        .filter((report) => !nextPath.has(report.id))
        .sort(byName)
        .map((report) => toNode(report, key, self, nextPath)),
    }
  }

  return people
    .filter((person) => !person.manager_ids.some((managerId) => byId.has(managerId)))
    .sort(byName)
    .map((person) => toNode(person, null, null, new Set()))
}
