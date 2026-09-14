<?php

namespace App\Services;

use App\DataObjects\Attachments\CreateAttachmentData;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Business logic for storing and removing file attachments.
 *
 * Keeps the binary (on a filesystem disk) and its metadata row consistent: the
 * file is written first, the row inside a transaction; if persisting the row
 * fails, the just-written file is removed so no orphan binary is left behind.
 */
class AttachmentService
{
    /**
     * Store an uploaded file from the HTTP boundary (validated DTO), authored by
     * the given actor and optionally linked to a polymorphic owner.
     */
    public function store(User $actor, CreateAttachmentData $data): Attachment
    {
        return $this->persist($data, $actor);
    }

    /**
     * Store a file directly for an owning model — the entry point used by the
     * HasAttachments trait (`$model->attach($file)`). The uploader defaults to
     * the currently authenticated user (null in console/queue contexts).
     */
    public function storeFor(Model $owner, UploadedFile $file, ?string $collection = null): Attachment
    {
        $data = new CreateAttachmentData(
            file: $file,
            collection: $collection,
            attachableType: $owner::class,
            attachableId: (int) $owner->getKey(),
        );

        /** @var User|null $actor */
        $actor = auth()->user();

        return $this->persist($data, $actor);
    }

    /**
     * Physically duplicate an existing attachment's binary onto a new UUID
     * object name (spec 0124, D-6) and link the copy to $target — a fresh,
     * independent `attachments` row, never a second reference to the same
     * file. Mirrors persist()'s own consistency shape: the binary is
     * written first, the row inside a transaction, and a failed insert
     * removes the just-written copy so no orphan binary survives.
     *
     * Same disk as the source (never cross-disk): the source's own `disk`
     * column, not `config('attachments.disk')`, so a copy always lands
     * beside the file it was copied from.
     */
    public function copyTo(Attachment $source, Model $target, ?string $collection, User $uploader): Attachment
    {
        $disk = $source->disk;
        $directory = trim((string) config('attachments.directory'), '/');
        $storedName = (string) Str::uuid().($source->extension !== null ? '.'.$source->extension : '');
        $path = $directory !== '' ? $directory.'/'.$storedName : $storedName;

        $copied = Storage::disk($disk)->copy($source->path, $path);

        if ($copied === false) {
            abort(500, 'Failed to copy the attachment file.');
        }

        try {
            return DB::transaction(function () use ($source, $target, $collection, $uploader, $disk, $path): Attachment {
                $attachment = new Attachment([
                    'collection' => $collection ?? $source->collection,
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => $source->original_name,
                    'mime_type' => $source->mime_type,
                    'extension' => $source->extension,
                    'size' => $source->size,
                    'uploaded_by' => $uploader->id,
                ]);

                $attachment->attachable()->associate($target);
                $attachment->save();

                return $attachment;
            });
        } catch (Throwable $exception) {
            // The metadata row failed to persist: drop the orphan copy.
            Storage::disk($disk)->delete($path);

            throw $exception;
        }
    }

    /**
     * Delete the attachment metadata and its stored binary.
     *
     * The row is deleted first inside a transaction; the file is removed only
     * after the row is gone, so a failed DB delete never leaves a dangling
     * record pointing at a missing file.
     */
    public function delete(Attachment $attachment): void
    {
        $disk = $attachment->disk;
        $path = $attachment->path;

        DB::transaction(function () use ($attachment): void {
            $attachment->delete();
        });

        Storage::disk($disk)->delete($path);
    }

    /**
     * Write the binary to disk and persist its metadata row consistently.
     *
     * The stored object name is a random UUID (the original name is never used
     * as a path, preventing traversal and collisions); the client's original
     * name is preserved only as metadata for display and download.
     */
    private function persist(CreateAttachmentData $data, ?User $actor): Attachment
    {
        $disk = (string) config('attachments.disk');
        $directory = trim((string) config('attachments.directory'), '/');

        $file = $data->file;
        $extension = $file->getClientOriginalExtension();
        $storedName = (string) Str::uuid().($extension !== '' ? '.'.$extension : '');

        $path = Storage::disk($disk)->putFileAs($directory, $file, $storedName);

        if ($path === false) {
            abort(500, 'Failed to store the uploaded file.');
        }

        try {
            return DB::transaction(function () use ($actor, $data, $disk, $path, $file, $extension): Attachment {
                $attachment = new Attachment([
                    'collection' => $data->collection,
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'extension' => $extension !== '' ? $extension : null,
                    'size' => $file->getSize(),
                    'uploaded_by' => $actor?->id,
                ]);

                if ($data->hasAttachable()) {
                    // Persist the morph alias (not the FQCN) so the value matches
                    // what the enforced morph map resolves on read. associate()
                    // takes care of writing both *_type (via getMorphClass) and
                    // *_id from a single owner instance.
                    /** @var class-string<Model> $ownerClass */
                    $ownerClass = $data->attachableType;
                    /** @var Model $owner */
                    $owner = $ownerClass::query()->findOrFail($data->attachableId);
                    $attachment->attachable()->associate($owner);
                }

                $attachment->save();

                return $attachment;
            });
        } catch (Throwable $exception) {
            // The metadata row failed to persist: drop the orphan binary.
            Storage::disk($disk)->delete($path);

            throw $exception;
        }
    }
}
