import { z } from "zod";
import type { TFunction } from "i18next";

/**
 * Zod schema of the collective agreement form, built as a factory so the
 * messages are localized. Mirrors the frozen backend contract (spec 0272).
 */

/** Backend `name` / level `description` column limit (`max:191`). */
const NAME_MAX_LENGTH = 191;
/** Backend `code` limits (`max:50`), for the agreement and for each level. */
const CODE_MAX_LENGTH = 50;

export function buildCollectiveAgreementSchema(t: TFunction) {
  return z.object({
    name: z
      .string()
      .min(1, t("collectiveAgreements.form.nameRequired"))
      .max(NAME_MAX_LENGTH, t("collectiveAgreements.form.nameMax")),
    code: z
      .string()
      .max(CODE_MAX_LENGTH, t("collectiveAgreements.form.codeMax"))
      .nullable(),
    // Ordered, authoritative list: the array replaces the saved levels. `id` keeps an existing level.
    levels: z.array(
      z.object({
        id: z.number().optional(),
        code: z
          .string()
          .trim()
          .min(1, t("collectiveAgreements.form.levelCodeRequired"))
          .max(CODE_MAX_LENGTH, t("collectiveAgreements.form.levelCodeMax")),
        description: z
          .string()
          .max(
            NAME_MAX_LENGTH,
            t("collectiveAgreements.form.levelDescriptionMax"),
          )
          .nullable(),
      }),
    ),
  });
}

export type CollectiveAgreementFormValues = z.infer<
  ReturnType<typeof buildCollectiveAgreementSchema>
>;
export type CollectiveAgreementLevelFormValues =
  CollectiveAgreementFormValues["levels"][number];
