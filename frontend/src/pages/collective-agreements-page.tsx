import { useTranslation } from "react-i18next";
import { Can } from "@/features/auth/can";
import { CollectiveAgreementsTable } from "@/features/collective-agreements/collective-agreements-table";

/**
 * Job roles page. Light composition only: gates access with `collective-agreements.view`
 * and mounts the thin adapter over the generic table (`domain="collective-agreements"`).
 */
export default function CollectiveAgreementsPage() {
  const { t } = useTranslation();

  return (
    <Can
      permission="collective-agreements.view"
      fallback={
        <p className="text-sm text-muted-foreground">
          {t("collectiveAgreements.forbidden")}
        </p>
      }
    >
      <div className="flex flex-1 flex-col gap-6">
        <CollectiveAgreementsTable />
      </div>
    </Can>
  );
}
