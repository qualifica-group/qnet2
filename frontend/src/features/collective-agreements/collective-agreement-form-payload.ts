import type {
  CollectiveAgreementFormValues,
  CollectiveAgreementLevelFormValues,
} from "@/features/collective-agreements/collective-agreement-schema";
import type {
  CollectiveAgreementDetail,
  CollectiveAgreementLevelPayload,
  CollectiveAgreementPayload,
  UpdateCollectiveAgreementPayload,
} from "@/features/collective-agreements/types";

/** A blank optional text is sent as `null`. */
function blankToNull(value: string | null): string | null {
  return value === null || value.trim() === "" ? null : value.trim();
}

/** A level as the API receives it: `id` only when the level already exists. */
function levelPayload(
  level: CollectiveAgreementLevelFormValues,
): CollectiveAgreementLevelPayload {
  const base = {
    code: level.code.trim(),
    description: blankToNull(level.description),
  };
  return level.id === undefined ? base : { id: level.id, ...base };
}

/** The saved levels in the payload shape, to tell whether the editor changed them. */
function savedLevels(
  original: CollectiveAgreementDetail,
): CollectiveAgreementLevelPayload[] {
  return original.levels.map(({ id, code, description }) => ({
    id,
    code,
    description,
  }));
}

/** Builds the create payload: every field the form owns. */
export function buildCreatePayload(
  values: CollectiveAgreementFormValues,
): CollectiveAgreementPayload {
  return {
    name: values.name.trim(),
    code: blankToNull(values.code),
    levels: values.levels.map(levelPayload),
  };
}

/** Builds a partial PATCH payload with only the fields that changed; levels travel as a whole list. */
export function buildUpdatePayload(
  values: CollectiveAgreementFormValues,
  original: CollectiveAgreementDetail,
): UpdateCollectiveAgreementPayload {
  const payload: UpdateCollectiveAgreementPayload = {};
  const levels = values.levels.map(levelPayload);

  if (values.name.trim() !== original.name) {
    payload.name = values.name.trim();
  }
  if (blankToNull(values.code) !== original.code) {
    payload.code = blankToNull(values.code);
  }
  if (JSON.stringify(levels) !== JSON.stringify(savedLevels(original))) {
    payload.levels = levels;
  }

  return payload;
}
