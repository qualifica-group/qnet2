import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  AbilityMap,
  ChangePasswordPayload,
  ForgotPasswordPayload,
  ImpersonationState,
  LoginPayload,
  LoginResult,
  ResetPasswordPayload,
  SetPasswordPayload,
  UpdateProfilePayload,
  User,
} from '@/features/auth/types'

export async function login(payload: LoginPayload): Promise<LoginResult> {
  const { data } = await apiClient.post<ApiResponse<LoginResult>>('/auth/login', payload)
  return data.data
}

export async function forgotPassword(payload: ForgotPasswordPayload): Promise<void> {
  await apiClient.post('/auth/forgot-password', payload)
}

export async function resetPassword(payload: ResetPasswordPayload): Promise<void> {
  await apiClient.post('/auth/reset-password', payload)
}

export async function setPassword(payload: SetPasswordPayload): Promise<void> {
  await apiClient.post('/auth/set-password', payload)
}

export async function logout(): Promise<void> {
  await apiClient.post('/auth/logout')
}

export async function fetchMe(): Promise<User> {
  const { data } = await apiClient.get<ApiResponse<User>>('/auth/me')
  return data.data
}

export async function updateProfile(payload: UpdateProfilePayload): Promise<User> {
  const { data } = await apiClient.patch<ApiResponse<User>>('/auth/me', payload)
  return data.data
}

export async function changePassword(payload: ChangePasswordPayload): Promise<void> {
  await apiClient.put('/auth/me/password', payload)
}

/** Uploads the current user's avatar (multipart). Returns the updated user. */
export async function uploadMyAvatar(file: File): Promise<User> {
  const formData = new FormData()
  formData.append('avatar', file)
  const { data } = await apiClient.post<ApiResponse<User>>('/auth/me/avatar', formData)
  return data.data
}

/** Removes the current user's avatar. Returns the updated user. */
export async function deleteMyAvatar(): Promise<User> {
  const { data } = await apiClient.delete<ApiResponse<User>>('/auth/me/avatar')
  return data.data
}

export async function fetchAbilities(): Promise<AbilityMap> {
  const { data } = await apiClient.get<ApiResponse<AbilityMap>>('/auth/me/abilities')
  return data.data
}

/** Starts impersonating the target user; returns a token headed for them. */
export async function impersonateUser(userId: number): Promise<LoginResult> {
  const { data } = await apiClient.post<ApiResponse<LoginResult>>(
    `/users/${userId}/impersonate`,
  )
  return data.data
}

/** Ends impersonation; returns a fresh token for the original actor. */
export async function stopImpersonation(): Promise<LoginResult> {
  const { data } = await apiClient.post<ApiResponse<LoginResult>>('/auth/stop-impersonation')
  return data.data
}

/** The original actor's identity when the current token is an impersonation. */
export async function fetchImpersonation(): Promise<ImpersonationState> {
  const { data } = await apiClient.get<ApiResponse<ImpersonationState>>('/auth/impersonation')
  return data.data
}
