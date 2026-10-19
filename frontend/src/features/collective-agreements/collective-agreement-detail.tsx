import { useMemo } from "react";
import { useTranslation } from "react-i18next";
import { ScrollText } from "lucide-react";
import { DetailMonogram } from "@/components/detail/detail-panel";
import {
  RecordCanvas,
  RecordCardHeader,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from "@/components/detail/record-panel";
import { RecordBody } from "@/components/detail/record-body";
import type { RecordCollaborationTab } from "@/components/detail/record-collaboration-card";
import {
  useInlineEditingField,
  useRecordInlineEdit,
} from "@/components/record-form/use-record-inline-edit";
import { Form } from "@/components/ui/form";
import { activityLogTab } from "@/features/activity-log/activity-log-tab";
import { ResourcePermissionsProvider } from "@/features/authorization/permissions";
import { formatDateTime } from "@/features/table/cell-renderers";
import { CollectiveAgreementRows } from "@/features/collective-agreements/collective-agreement-rows";
import {
  useCollectiveAgreementForm,
  type CollectiveAgreementFormValues,
} from "@/features/collective-agreements/use-collective-agreement-form";
import {
  COLLECTIVE_AGREEMENTS_DOMAIN,
  type CollectiveAgreementDetailWithPermissions,
  type CollectiveAgreementFormMode,
} from "@/features/collective-agreements/types";

interface CollectiveAgreementDetailViewProps {
  collectiveAgreement: CollectiveAgreementDetailWithPermissions;
  /** Called after an in-place save, so the host refreshes whatever lists the record. */
  onChanged?: () => void;
}

/**
 * Detail of a single collective agreement on the record kit: identity band, the rows
 * (name, code, levels) edited IN PLACE one at a time (each save a
 * PATCH of that field alone), the activity log in the side column and a metadata footer.
 */
export function CollectiveAgreementDetailView(
  props: CollectiveAgreementDetailViewProps,
) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider
      permissions={props.collectiveAgreement.permissions}
    >
      <CollectiveAgreementDetailContent {...props} />
    </ResourcePermissionsProvider>
  );
}

function CollectiveAgreementDetailContent({
  collectiveAgreement,
  onChanged,
}: CollectiveAgreementDetailViewProps) {
  const { t } = useTranslation();
  const editing = useInlineEditingField();
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<CollectiveAgreementFormMode>(
    () => ({ type: "edit", collectiveAgreement }),
    [collectiveAgreement],
  );
  const { form, onSubmit, serverError, clearServerError } =
    useCollectiveAgreementForm({
      mode,
      onSuccess: () => {
        editing.close();
        onChanged?.();
      },
    });
  const inline = useRecordInlineEdit<CollectiveAgreementFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t("collectiveAgreements.form.genericError"),
  });
  const canEdit = collectiveAgreement.permissions.resource.update;
  const createdAt = formatDateTime(collectiveAgreement.created_at);
  const updatedAt = formatDateTime(collectiveAgreement.updated_at);
  const tabs: RecordCollaborationTab[] = collectiveAgreement.permissions.actions
    .view_activity
    ? [
        activityLogTab(
          COLLECTIVE_AGREEMENTS_DOMAIN,
          collectiveAgreement.id,
          t("activityLog.title"),
        ),
      ]
    : [];

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader
            media={
              <DetailMonogram
                name={collectiveAgreement.name}
                icon={<ScrollText />}
              />
            }
            title={collectiveAgreement.name}
          />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection
              title={t("collectiveAgreements.form.sections.identity.title")}
              full
            >
              <RecordFieldList>
                <CollectiveAgreementRows inline={inline} canEdit={canEdit} />
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      {createdAt || updatedAt ? (
        <RecordMeta>
          {createdAt ? (
            <span>
              <span className="font-medium">
                {t("collectiveAgreements.detail.created_at")}
              </span>{" "}
              <span aria-hidden="true">·</span> {createdAt}
            </span>
          ) : null}
          {updatedAt ? (
            <span>
              <span className="font-medium">
                {t("collectiveAgreements.detail.updated_at")}
              </span>{" "}
              <span aria-hidden="true">·</span> {updatedAt}
            </span>
          ) : null}
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  );
}
