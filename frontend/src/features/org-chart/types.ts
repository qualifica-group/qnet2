/** Contract of `GET /api/org-chart/functions` and `GET /api/org-chart/people` (spec 0273). */

export type OrgFunctionType = 'business_unit' | 'business_service'

export interface OrgPerson {
  id: number
  name: string
  avatar_url: string | null
  job_roles: string[]
  is_manager: boolean
}

export interface OrgFunction {
  id: number
  name: string
  type: OrgFunctionType | null
  parent_id: number | null
  manager: { id: number; name: string } | null
  people: OrgPerson[]
}

export interface OrgChartFunctions {
  functions: OrgFunction[]
  all_functions_people: OrgPerson[]
}

export interface OrgDirectoryPerson extends OrgPerson {
  manager_ids: number[]
  business_functions: string[]
}

/** Request bodies of the three move endpoints (spec 0273 D-4). */
export interface MovePersonManagerPayload {
  from_manager_id: number | null
  to_manager_id: number | null
}

export interface MoveFunctionParentPayload {
  parent_id: number | null
}

export interface MovePersonFunctionPayload {
  from_function_id: number
  to_function_id: number
}
