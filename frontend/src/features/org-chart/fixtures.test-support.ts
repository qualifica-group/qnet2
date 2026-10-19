import type { OrgChartFunctions, OrgDirectoryPerson, OrgPerson } from '@/features/org-chart/types'

export function person(id: number, name: string, overrides: Partial<OrgPerson> = {}): OrgPerson {
  return { id, name, avatar_url: null, job_roles: [], is_manager: false, ...overrides }
}

export function directoryPerson(
  id: number,
  name: string,
  managerIds: number[] = [],
  overrides: Partial<OrgDirectoryPerson> = {},
): OrgDirectoryPerson {
  return { ...person(id, name), manager_ids: managerIds, business_functions: [], ...overrides }
}

export const FUNCTIONS_FIXTURE: OrgChartFunctions = {
  functions: [
    {
      id: 2,
      name: 'Sales',
      type: 'business_unit',
      parent_id: 1,
      manager: { id: 11, name: 'Bruno Neri' },
      people: [person(11, 'Bruno Neri', { job_roles: ['Account'] }), person(12, 'Carla Gallo')],
    },
    { id: 1, name: 'Company', type: null, parent_id: null, manager: null, people: [person(10, 'Anna Rossi')] },
    { id: 3, name: 'Support', type: 'business_service', parent_id: 1, manager: null, people: [] },
  ],
  unassigned_people: [person(13, 'Dario Fiore')],
}

export const PEOPLE_FIXTURE: OrgDirectoryPerson[] = [
  directoryPerson(1, 'Anna Rossi', [], { is_manager: true, business_functions: [{ id: 1, name: 'Company', is_manager: true }, { id: 2, name: 'Sales', is_manager: false }] }),
  directoryPerson(2, 'Bruno Neri', [1], { job_roles: ['Account'] }),
  directoryPerson(3, 'Carla Gallo', [1, 2]),
  directoryPerson(4, 'Dario Fiore', [99]),
]
