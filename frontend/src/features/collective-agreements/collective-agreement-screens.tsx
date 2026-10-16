/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from "react-i18next";
import { useQueryClient } from "@tanstack/react-query";
import { DetailError, DetailLoading } from "@/components/detail/detail-panel";
import { useEntityDetail } from "@/hooks/use-entity-detail";
import {
  collectiveAgreementKeys,
  fetchCollectiveAgreement,
} from "@/features/collective-agreements/api";
import { CollectiveAgreementForm } from "@/features/collective-agreements/collective-agreement-form";
import { CollectiveAgreementDetailView } from "@/features/collective-agreements/collective-agreement-detail";
import { OPEN_MODE_MODAL } from "@/features/modules/types";
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from "@/features/modules/types";
import {
  COLLECTIVE_AGREEMENTS_DOMAIN,
  type CollectiveAgreementDetail,
} from "@/features/collective-agreements/types";

/**
 * Content-only `collective-agreements` screens for the module registry (spec 0042). The
 * detail edits its rows in place, so `onEdit` is never used; each save
 * reports through `onChanged`.
 */
export function CollectiveAgreementDetailScreen({
  id,
  onChanged,
}: ModuleDetailScreenProps) {
  const { t } = useTranslation();
  const {
    data: collectiveAgreement,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(collectiveAgreementKeys.detail(id), () =>
    fetchCollectiveAgreement(id),
  );

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t("collectiveAgreements.detail.loadError")}
        retryLabel={t("common.retry")}
        onRetry={() => refetch()}
      />
    );
  }

  if (isLoading || !collectiveAgreement) {
    return <DetailLoading />;
  }

  return (
    <CollectiveAgreementDetailView
      collectiveAgreement={collectiveAgreement}
      onChanged={onChanged}
    />
  );
}

/** Create only: there is no edit form, and the registry generates no `:id/edit` route. */
export function CollectiveAgreementFormScreen({
  mode,
  onSuccess,
  onCancel,
}: ModuleFormScreenProps) {
  const queryClient = useQueryClient();

  if (mode.type !== "create") {
    return null;
  }

  const handleSuccess = (saved: CollectiveAgreementDetail) => {
    void queryClient.invalidateQueries({
      queryKey: collectiveAgreementKeys.detail(saved.id),
    });
    onSuccess(saved.id);
  };

  return (
    <CollectiveAgreementForm onSuccess={handleSuccess} onCancel={onCancel} />
  );
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: COLLECTIVE_AGREEMENTS_DOMAIN,
  basePath: `/${COLLECTIVE_AGREEMENTS_DOMAIN}`,
  defaultMode: OPEN_MODE_MODAL,
  labelKey: "navigation.collectiveAgreements",
  DetailScreen: CollectiveAgreementDetailScreen,
  FormScreen: CollectiveAgreementFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The create form renders its own identity band, so hosts must not stack a second heading.
  formOwnsHeader: true,
};
