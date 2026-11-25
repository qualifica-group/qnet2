import { z } from 'zod'
import type { TFunction } from 'i18next'
import type { DocumentRequestReviewDecision } from '@/features/document-requests/types'

/** Spec 0429: the review note is at most 1000 characters; mandatory only to reject. */
export const REVIEW_NOTE_MAX_LENGTH = 1000

export function buildReviewSchema(t: TFunction, decision: DocumentRequestReviewDecision) {
  const note = z.string().max(REVIEW_NOTE_MAX_LENGTH, t('documentRequests.validation.noteMax'))

  return z.object({
    note:
      decision === 'reject'
        ? note.refine((value) => value.trim() !== '', t('documentRequests.validation.noteRequired'))
        : note,
  })
}

export type ReviewFormValues = z.infer<ReturnType<typeof buildReviewSchema>>
