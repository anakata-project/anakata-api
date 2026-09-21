<?php

declare(strict_types=1);

namespace App\Support\Config;

use App\Enums\ConfigKind;

abstract class ConfigDocument
{
    /**
     * @param  array<string, mixed>  $data
     */
    abstract public static function fromArray(array $data): static;

    /**
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;

    /**
     * Laravel validation rules for the document's array form, keyed with dot notation.
     *
     * @return array<string, mixed>
     */
    abstract public static function rules(): array;

    /**
     * Human label per leaf path, used for the stored change list.
     *
     * @return array<string, string>
     */
    abstract public static function labels(): array;

    abstract public static function kind(): ConfigKind;

    /**
     * Soft checks that do not block publishing.
     *
     * @return list<Warning>
     */
    public function warnings(?self $published): array
    {
        return [];
    }

    /**
     * @return list<Change>
     */
    public function changesAgainst(?self $published): array
    {
        return DocumentDiff::compare(
            $published?->toArray() ?? [],
            $this->toArray(),
            static::labels(),
        );
    }

    /**
     * @param  list<Change>  $changes
     */
    public function requiresApprovalReference(array $changes): bool
    {
        return true;
    }

    /**
     * Blocking checks against the published document (e.g. immutable codes).
     *
     * @return array<string, list<string>>
     */
    public function publishErrors(?self $published): array
    {
        return [];
    }
}
