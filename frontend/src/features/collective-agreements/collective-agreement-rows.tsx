import { useTranslation } from "react-i18next";
import { useFormContext, useWatch } from "react-hook-form";
import { DetailEmpty } from "@/components/detail/detail-panel";
import { TextInlineRow } from "@/components/record-form/inline-rows";
import {
  RecordInlineField,
  type InlineEdit,
} from "@/components/record-form/record-inline-field";
import { LevelsEditor } from "@/features/collective-agreements/levels-editor";
import type { CollectiveAgreementFormValues } from "@/features/collective-agreements/collective-agreement-schema";

interface CollectiveAgreementRowsProps {
  inline: InlineEdit;
  canEdit: boolean;
}

/** The levels, replaced as a whole list: an ordered list when closed, the repeatable editor when open. */
function LevelsRow({ inline, canEdit }: CollectiveAgreementRowsProps) {
  const { t } = useTranslation();
  const { control } = useFormContext<CollectiveAgreementFormValues>();
  const levels = useWatch({ control, name: "levels" });

  return (
    <RecordInlineField
      field="levels"
      label={t("collectiveAgreements.form.levels")}
      inline={inline}
      canEdit={canEdit}
      editor={<LevelsEditor />}
    >
      {levels.length > 0 ? (
        <ol className="flex flex-col gap-0.5 text-sm">
          {levels.map((level, index) => (
            <li key={level.id ?? `new-${index}`}>
              <span className="font-medium">{level.code}</span>
              {level.description ? (
                <span className="text-muted-foreground">
                  {" "}
                  – {level.description}
                </span>
              ) : null}
            </li>
          ))}
        </ol>
      ) : (
        <DetailEmpty />
      )}
    </RecordInlineField>
  );
}

/** The agreement's rows, shared by the detail (saved one at a time) and the create draft (kept until Save). */
export function CollectiveAgreementRows({
  inline,
  canEdit,
}: CollectiveAgreementRowsProps) {
  const { t } = useTranslation();

  return (
    <>
      <TextInlineRow<CollectiveAgreementFormValues>
        name="name"
        label={t("collectiveAgreements.form.name")}
        inline={inline}
        canEdit={canEdit}
      />
      <TextInlineRow<CollectiveAgreementFormValues>
        name="code"
        label={t("collectiveAgreements.form.code")}
        inline={inline}
        canEdit={canEdit}
      />
      <LevelsRow inline={inline} canEdit={canEdit} />
    </>
  );
}
