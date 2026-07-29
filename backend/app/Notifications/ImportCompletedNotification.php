<?php

namespace App\Notifications;

use App\DataObjects\Notifications\NotificationData;
use App\Enums\NotificationLevelEnum;
use App\Models\ImportRun;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent once by ProcessStagedImportJob when a wizard import run finishes (spec
 * 0033, AC-009), guarded by `import_runs.notified_at` so it never fires
 * twice. Persisted via the native `database` channel, following the SAME
 * `{title,message,level,action_url}` contract GenericNotification builds
 * through NotificationData — a dedicated class (not a GenericNotification
 * instance) because the copy and severity level are derived from the run's
 * own outcome (imported/error counts), not passed in by a caller.
 */
class ImportCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly ImportRun $run) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{title: string|null, message: string|null, level: string, action_url: string|null}
     */
    public function toArray(object $notifiable): array
    {
        return (new NotificationData(
            title: 'Import completed',
            message: $this->buildMessage(),
            level: $this->run->error_count > 0 ? NotificationLevelEnum::Warning : NotificationLevelEnum::Success,
            // SPA route is `/imports/:runId` (routes/router.tsx) — a
            // `/imports/{resource}/{id}` deep link matches nothing and lands
            // the user on the not-found page.
            actionUrl: "/imports/{$this->run->id}",
        ))->toArray();
    }

    private function buildMessage(): string
    {
        $imported = $this->run->imported_rows ?? 0;
        $message = "{$imported} row(s) imported.";

        if ($this->run->error_count > 0) {
            $message .= " {$this->run->error_count} row(s) failed.";
        }

        return $message;
    }
}
