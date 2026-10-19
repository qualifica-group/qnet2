import { useFieldArray, useFormContext } from "react-hook-form";
import { useTranslation } from "react-i18next";
import { ArrowDown, ArrowUp, Plus, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import type { CollectiveAgreementFormValues } from "@/features/collective-agreements/collective-agreement-schema";

/** Row layout: code | description | move up | move down | remove (icon cells are `size-6` = 1.5rem). */
const ROW_GRID_CLASS =
  "grid grid-cols-[minmax(0,1fr)_minmax(0,2fr)_1.5rem_1.5rem_1.5rem] items-start gap-2";

/** Key RHF assigns to each row: the default `id` would shadow the level's own persisted `id`. */
const ROW_KEY = "rowKey";

/**
 * Repeatable editor of the agreement's levels: add, remove and reorder (up/down)
 * rows of code + description. The order of the rows is the order sent to the API.
 * Must render inside the record's `<Form>` provider.
 */
export function LevelsEditor() {
  const { t } = useTranslation();
  const { control, getFieldState, formState } =
    useFormContext<CollectiveAgreementFormValues>();
  const { fields, append, remove, move } = useFieldArray({
    control,
    name: "levels",
    keyName: ROW_KEY,
  });
  const listError =
    getFieldState("levels", formState).error?.message ??
    formState.errors.levels?.root?.message;

  return (
    <div className="flex flex-col gap-2">
      {fields.length > 0 ? (
        <>
          <div className={ROW_GRID_CLASS} aria-hidden="true">
            <span className="text-xs font-medium text-muted-foreground">
              {t("collectiveAgreements.form.levelCode")}
            </span>
            <span className="text-xs font-medium text-muted-foreground">
              {t("collectiveAgreements.form.levelDescription")}
            </span>
          </div>
          <ul className="flex flex-col gap-2">
            {fields.map((levelField, index) => (
              <li key={levelField[ROW_KEY]} className={ROW_GRID_CLASS}>
                <FormField
                  control={control}
                  name={`levels.${index}.code`}
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel className="sr-only">
                        {t("collectiveAgreements.form.levelCodeN", {
                          n: index + 1,
                        })}
                      </FormLabel>
                      <FormControl>
                        <Input autoComplete="off" {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={control}
                  name={`levels.${index}.description`}
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel className="sr-only">
                        {t("collectiveAgreements.form.levelDescriptionN", {
                          n: index + 1,
                        })}
                      </FormLabel>
                      <FormControl>
                        <Input
                          autoComplete="off"
                          {...field}
                          value={field.value ?? ""}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-xs"
                  className="mt-1.5 text-muted-foreground"
                  aria-label={t("collectiveAgreements.form.levelMoveUp", {
                    n: index + 1,
                  })}
                  disabled={index === 0}
                  onClick={() => move(index, index - 1)}
                >
                  <ArrowUp aria-hidden="true" />
                </Button>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-xs"
                  className="mt-1.5 text-muted-foreground"
                  aria-label={t("collectiveAgreements.form.levelMoveDown", {
                    n: index + 1,
                  })}
                  disabled={index === fields.length - 1}
                  onClick={() => move(index, index + 1)}
                >
                  <ArrowDown aria-hidden="true" />
                </Button>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-xs"
                  className="mt-1.5 text-muted-foreground hover:text-destructive"
                  aria-label={t("collectiveAgreements.form.levelRemove", {
                    n: index + 1,
                  })}
                  onClick={() => remove(index)}
                >
                  <Trash2 aria-hidden="true" />
                </Button>
              </li>
            ))}
          </ul>
        </>
      ) : (
        <p className="text-sm text-muted-foreground">
          {t("collectiveAgreements.form.levelsEmpty")}
        </p>
      )}
      {listError ? (
        <p role="alert" className="text-xs font-medium text-destructive">
          {listError}
        </p>
      ) : null}
      <Button
        type="button"
        variant="outline"
        size="sm"
        className="w-full border-dashed bg-card text-muted-foreground hover:text-foreground"
        onClick={() => append({ code: "", description: null })}
      >
        <Plus aria-hidden="true" />
        {t("collectiveAgreements.form.levelAdd")}
      </Button>
    </div>
  );
}
