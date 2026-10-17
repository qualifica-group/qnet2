import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  MoveFunctionParentPayload,
  MovePersonFunctionPayload,
  MovePersonManagerPayload,
  OrgChartFunctions,
  OrgDirectoryPerson,
} from '@/features/org-chart/types'

/** Centralized query keys of the org-chart feature; moves invalidate `all`. */
export const orgChartKeys = {
  all: ['org-chart'] as const,
  functions: ['org-chart', 'functions'] as const,
  people: ['org-chart', 'people'] as const,
}

export async function fetchOrgChartFunctions(): Promise<OrgChartFunctions> {
  const { data } = await apiClient.get<ApiResponse<OrgChartFunctions>>('/org-chart/functions')
  return data.data
}

export async function fetchOrgChartPeople(): Promise<OrgDirectoryPerson[]> {
  const { data } = await apiClient.get<ApiResponse<OrgDirectoryPerson[]>>('/org-chart/people')
  return data.data
}

export async function moveOrgPersonManager(userId: number, payload: MovePersonManagerPayload): Promise<void> {
  await apiClient.post(`/org-chart/people/${userId}/manager`, payload)
}

export async function moveOrgFunctionParent(functionId: number, payload: MoveFunctionParentPayload): Promise<void> {
  await apiClient.post(`/org-chart/functions/${functionId}/parent`, payload)
}

export async function moveOrgPersonFunction(userId: number, payload: MovePersonFunctionPayload): Promise<void> {
  await apiClient.post(`/org-chart/people/${userId}/function`, payload)
}
