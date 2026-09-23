<?php

declare(strict_types=1);

namespace App\Support\Portal;

use App\Models\AgencyUser;
use App\Models\ChangeHistory;
use App\Support\Iso;
use Illuminate\Support\Collection;

final class PortalActivity
{
    /** @var list<string> */
    public const EVENTS = [
        'portal.signed_in',
        'portal.sign_in_failed',
        'portal.request_created',
        'portal.material_downloaded',
    ];

    /**
     * @param  Collection<int, AgencyUser>  $users
     * @return array{
     *     at: string,
     *     event: string,
     *     agency_user: array{id: int|null, name: string},
     *     references: list<string>|null,
     *     material: array{id: int, title: string, version: int}|null
     * }
     */
    public static function present(ChangeHistory $entry, Collection $users): array
    {
        $context = $entry->getAttribute('context');
        $userId = self::userId($context);
        $user = $userId !== null ? $users->get($userId) : null;

        return [
            'at' => Iso::utc($entry->created_at),
            'event' => $entry->event,
            'agency_user' => [
                'id' => $userId,
                'name' => $user instanceof AgencyUser ? $user->name : $entry->actor_label,
            ],
            'references' => self::references($entry),
            'material' => self::material($entry),
        ];
    }

    public static function userId(mixed $context): ?int
    {
        if (! is_array($context)) {
            return null;
        }

        $rawId = $context['agency_user_id'] ?? null;

        if (is_int($rawId)) {
            return $rawId;
        }

        if (is_string($rawId) && ctype_digit($rawId)) {
            return (int) $rawId;
        }

        return null;
    }

    /**
     * @return list<string>|null
     */
    private static function references(ChangeHistory $entry): ?array
    {
        if ($entry->event !== 'portal.request_created' || ! is_array($entry->after)) {
            return null;
        }

        $raw = $entry->after['references'] ?? null;

        if (! is_array($raw)) {
            return null;
        }

        $references = [];

        foreach ($raw as $reference) {
            if (is_string($reference) && $reference !== '') {
                $references[] = $reference;
            }
        }

        return $references;
    }

    /**
     * @return array{id: int, title: string, version: int}|null
     */
    private static function material(ChangeHistory $entry): ?array
    {
        if ($entry->event !== 'portal.material_downloaded' || ! is_array($entry->after)) {
            return null;
        }

        $id = $entry->after['material_id'] ?? null;
        $title = $entry->after['title'] ?? null;
        $version = $entry->after['version'] ?? null;

        if (! is_numeric($id) || ! is_string($title) || ! is_numeric($version)) {
            return null;
        }

        return [
            'id' => (int) $id,
            'title' => $title,
            'version' => (int) $version,
        ];
    }
}
