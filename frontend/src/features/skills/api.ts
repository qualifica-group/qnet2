import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import {
  SKILLS_DOMAIN,
  type Skill,
  type SkillDetail,
  type SkillPayload,
  type UpdateSkillPayload,
} from '@/features/skills/types'

const ENDPOINT = `/${SKILLS_DOMAIN}`

/** Centralized query keys of the skills catalogue. */
export const skillKeys = {
  all: [SKILLS_DOMAIN] as const,
  detail: (id: number) => [SKILLS_DOMAIN, 'detail', id] as const,
}

/** Read-only fallback when the envelope carries no authorization metadata: the backend stays the authority. */
const VIEW_ONLY_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: false, update: false, delete: false, export: false, import: false },
  fields: {},
  actions: {},
}

type PermissionsEnvelope = ApiResponseWithPermissions<Skill, ResourcePermissions | undefined>

function withPermissions({ data, permissions }: PermissionsEnvelope): SkillDetail {
  return { ...data, permissions: permissions ?? VIEW_ONLY_PERMISSIONS }
}

/** Fetches a skill together with the actor's authorization metadata for it. */
export async function fetchSkill(id: number): Promise<SkillDetail> {
  const { data } = await apiClient.get<PermissionsEnvelope>(`${ENDPOINT}/${id}`)
  return withPermissions(data)
}

export async function createSkill(payload: SkillPayload): Promise<Skill> {
  const { data } = await apiClient.post<ApiResponse<Skill>>(ENDPOINT, payload)
  return data.data
}

/** Partial update (PATCH): the in-place detail saves one field at a time. */
export async function updateSkill(id: number, payload: UpdateSkillPayload): Promise<SkillDetail> {
  const { data } = await apiClient.patch<PermissionsEnvelope>(`${ENDPOINT}/${id}`, payload)
  return withPermissions(data)
}

/** 422 when the skill is assigned to people: the caller maps it to "deactivate it instead". */
export async function deleteSkill(id: number): Promise<void> {
  await apiClient.delete(`${ENDPOINT}/${id}`)
}
