import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'

/**
 * A single uploaded layout image WITH its binary (spec 0069 images
 * endpoints). Distinct from `DocumentLayoutImageMeta` (`types.ts`, no
 * `data_uri`): this shape only exists behind the dedicated
 * `GET .../images` endpoint, never on the layout detail response, so the
 * base64 payload never rides along with the table/detail (spec 0069
 * `images` endpoint doc). Backend endpoints do not exist yet at the time of
 * writing (spec 0069 wave 2 report) — every test mocks this module.
 */
export interface DocumentLayoutImage {
  attachment_id: number
  filename: string
  mime_type: string
  size: number
  data_uri: string
}

export function documentLayoutImagesKey(layoutId: number) {
  return ['document-layouts', layoutId, 'images'] as const
}

export async function fetchDocumentLayoutImages(layoutId: number): Promise<DocumentLayoutImage[]> {
  const { data } = await apiClient.get<ApiResponse<DocumentLayoutImage[]>>(`/document-layouts/${layoutId}/images`)
  return data.data
}

/** Uploads a new layout image (`jpeg`/`png` only, spec 0069). Returns the created image with its `data_uri`. */
export async function uploadDocumentLayoutImage(layoutId: number, file: File): Promise<DocumentLayoutImage> {
  const formData = new FormData()
  formData.append('file', file)
  const { data } = await apiClient.post<ApiResponse<DocumentLayoutImage>>(
    `/document-layouts/${layoutId}/images`,
    formData,
  )
  return data.data
}

export async function deleteDocumentLayoutImage(layoutId: number, attachmentId: number): Promise<void> {
  await apiClient.delete(`/document-layouts/${layoutId}/images/${attachmentId}`)
}

/** `layoutId === null` (an unsaved, create-mode layout) defers the fetch entirely — there is nothing to load yet. */
export function useDocumentLayoutImages(layoutId: number | null) {
  return useQuery({
    queryKey: layoutId !== null ? documentLayoutImagesKey(layoutId) : ['document-layouts', 'images', 'unsaved'],
    queryFn: () => fetchDocumentLayoutImages(layoutId as number),
    enabled: layoutId !== null,
  })
}

export function useUploadDocumentLayoutImage(layoutId: number | null) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (file: File) => uploadDocumentLayoutImage(layoutId as number, file),
    onSuccess: () => {
      if (layoutId !== null) {
        void queryClient.invalidateQueries({ queryKey: documentLayoutImagesKey(layoutId) })
      }
    },
  })
}

export function useDeleteDocumentLayoutImage(layoutId: number | null) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (attachmentId: number) => deleteDocumentLayoutImage(layoutId as number, attachmentId),
    onSuccess: () => {
      if (layoutId !== null) {
        void queryClient.invalidateQueries({ queryKey: documentLayoutImagesKey(layoutId) })
      }
    },
  })
}
