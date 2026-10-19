import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import {
  USER_SKILLS_DOMAIN,
  type Coverage,
  type CoverageParams,
  type UserSkill,
  type UserSkillPayload,
} from '@/features/user-skills/types'

const ENDPOINT = `/${USER_SKILLS_DOMAIN}`

export async function fetchUserSkill(id: number): Promise<UserSkill> {
  const { data } = await apiClient.get<ApiResponse<UserSkill>>(`${ENDPOINT}/${id}`)
  return data.data
}

export async function createUserSkill(payload: UserSkillPayload): Promise<UserSkill> {
  const { data } = await apiClient.post<ApiResponse<UserSkill>>(ENDPOINT, payload)
  return data.data
}

export async function updateUserSkill(id: number, payload: UserSkillPayload): Promise<UserSkill> {
  const { data } = await apiClient.patch<ApiResponse<UserSkill>>(`${ENDPOINT}/${id}`, payload)
  return data.data
}

export async function deleteUserSkill(id: number): Promise<void> {
  await apiClient.delete(`${ENDPOINT}/${id}`)
}

/** Person x required skill status of the people the actor's visibility reveals. */
export async function fetchCoverage(params: CoverageParams): Promise<Coverage> {
  const { data } = await apiClient.get<ApiResponse<Coverage>>(`${ENDPOINT}/coverage`, { params })
  return data.data
}
