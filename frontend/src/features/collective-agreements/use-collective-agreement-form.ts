import { useMemo, useState } from "react";
import { useForm } from "react-hook-form";
import type { Path, UseFormSetError } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslation } from "react-i18next";
import { useQueryClient } from "@tanstack/react-query";
import axios from "axios";
import { toast } from "sonner";
import { applyServerValidationErrors } from "@/features/auth/form-errors";
import {
  collectiveAgreementKeys,
  createCollectiveAgreement,
  updateCollectiveAgreement,
} from "@/features/collective-agreements/api";
import {
  buildCreatePayload,
  buildUpdatePayload,
} from "@/features/collective-agreements/collective-agreement-form-payload";
import {
  buildCollectiveAgreementSchema,
  type CollectiveAgreementFormValues,
} from "@/features/collective-agreements/collective-agreement-schema";
import type {
  CollectiveAgreementDetail,
  CollectiveAgreementFormMode,
} from "@/features/collective-agreements/types";

export type { CollectiveAgreementFormValues };

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ["name", "code"] as const;

/** Server-side key of the level list; `levels.N.code` style keys target a single level row. */
const LEVELS_FIELD = "levels";
const LEVEL_ROW_KEY = /^levels\.(\d+)\.(code|description)$/;

const CREATE_DEFAULTS: CollectiveAgreementFormValues = {
  name: "",
  code: null,
  levels: [],
};

/** Maps the 422 on `levels` (level in use) and on a single level row; returns whether anything was mapped. */
function applyLevelErrors(
  error: unknown,
  setError: UseFormSetError<CollectiveAgreementFormValues>,
  inUse: string,
) {
  const errors = axios.isAxiosError(error)
    ? (error.response?.data?.errors as Record<string, string[]> | undefined)
    : undefined;
  if (!errors) {
    return;
  }
  if (errors[LEVELS_FIELD]) {
    setError(LEVELS_FIELD, { message: inUse });
  }
  for (const [key, messages] of Object.entries(errors)) {
    const match = LEVEL_ROW_KEY.exec(key);
    if (match && messages[0]) {
      setError(
        `levels.${Number(match[1])}.${match[2] as "code" | "description"}`,
        { message: messages[0] },
      );
    }
  }
}

interface UseCollectiveAgreementFormArgs {
  mode: CollectiveAgreementFormMode;
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (collectiveAgreement: CollectiveAgreementDetail) => void;
}

/**
 * Owns every non-render concern of the collective agreement form: RHF/Zod
 * wiring, defaults, 422 mapping, submit. Create backs the create form; edit
 * backs the detail's in-place rows (diff PATCH).
 */
export function useCollectiveAgreementForm({
  mode,
  onSuccess,
}: UseCollectiveAgreementFormArgs) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [serverError, setServerError] = useState<string | null>(null);
  const isEdit = mode.type === "edit";

  const schema = useMemo(() => buildCollectiveAgreementSchema(t), [t]);

  const defaultValues = useMemo<CollectiveAgreementFormValues>(() => {
    if (mode.type === "edit") {
      const { name, code, levels } = mode.collectiveAgreement;
      return {
        name,
        code,
        levels: levels.map(({ id, code: levelCode, description }) => ({
          id,
          code: levelCode,
          description,
        })),
      };
    }
    return CREATE_DEFAULTS;
  }, [mode]);

  // Edit re-syncs on a saved record; `keepDirtyValues` spares a row still open over a background refetch.
  const form = useForm<CollectiveAgreementFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: { keepDirtyValues: true },
  });

  const onSubmit = async (values: CollectiveAgreementFormValues) => {
    setServerError(null);
    const errorFields: Path<CollectiveAgreementFormValues>[] = [
      ...SERVER_ERROR_FIELDS,
    ];
    try {
      if (mode.type === "edit") {
        const saved = await updateCollectiveAgreement(
          mode.collectiveAgreement.id,
          buildUpdatePayload(values, mode.collectiveAgreement),
        );
        queryClient.setQueryData(
          collectiveAgreementKeys.detail(mode.collectiveAgreement.id),
          saved,
        );
        toast.success(t("collectiveAgreements.form.updated"));
        onSuccess(saved);
        return;
      }
      const created = await createCollectiveAgreement(
        buildCreatePayload(values),
      );
      toast.success(t("collectiveAgreements.form.created"));
      onSuccess(created);
    } catch (error) {
      if (applyServerValidationErrors(error, form.setError, errorFields)) {
        applyLevelErrors(
          error,
          form.setError,
          t("collectiveAgreements.form.levelsInUse"),
        );
      } else {
        setServerError(t("collectiveAgreements.form.genericError"));
      }
    }
  };

  return {
    form,
    serverError,
    clearServerError: () => setServerError(null),
    onSubmit,
  };
}
