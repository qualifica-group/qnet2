<?php

declare(strict_types=1);

namespace App\Http\Requests\ArchivioSmart;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates PUT /api/archivio-smart/settings (spec 0403, D-2).
 */
class SaveArchivioSmartSettingsRequest extends FormRequest
{
    /** Environments where a plain http base URL is tolerated (local mocks). */
    private const INSECURE_URL_ENVIRONMENTS = ['local', 'testing'];

    public function authorize(): bool
    {
        // Authorization handled in the controller (document-checklists.manageConnection).
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $schemes = app()->environment(self::INSECURE_URL_ENVIRONMENTS) ? 'url:http,https' : 'url:https';

        return [
            'base_url' => ['required', 'string', 'max:255', $schemes],
            'token' => ['nullable', 'string', 'min:10', 'max:500'],
            'webhook_secret' => ['sometimes', 'nullable', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'base_url' => __('archivio_smart.attributes.base_url'),
            'token' => __('archivio_smart.attributes.token'),
            'webhook_secret' => __('archivio_smart.attributes.webhook_secret'),
        ];
    }

    /**
     * Blank token means "keep the saved one".
     */
    public function newToken(): ?string
    {
        $token = $this->validated('token');

        return filled($token) ? $token : null;
    }

    /**
     * Omitted key = keep the saved secret; a sent key (blank included) = replace or remove it (spec 0428).
     */
    public function changesWebhookSecret(): bool
    {
        return array_key_exists('webhook_secret', $this->validated());
    }

    public function newWebhookSecret(): ?string
    {
        $secret = $this->validated('webhook_secret');

        return filled($secret) ? $secret : null;
    }

    public function baseUrl(): string
    {
        return rtrim((string) $this->validated('base_url'), '/');
    }
}
