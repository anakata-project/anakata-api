<?php

declare(strict_types=1);

namespace App\Support\SalesMaterials;

use App\Models\SalesMaterial;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MaterialFile
{
    public const MAX_KILOBYTES = 51200;

    /** @var array<string, string> */
    public const EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'video/mp4' => 'mp4',
        'application/zip' => 'zip',
        'application/x-zip-compressed' => 'zip',
    ];

    public static function detect(UploadedFile $file): ?string
    {
        $path = $file->getRealPath();

        if (! is_string($path) || $path === '') {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        return is_string($mime) && $mime !== '' ? $mime : null;
    }

    public static function extension(string $mime): string
    {
        return self::EXTENSIONS[$mime] ?? throw new RuntimeException('Unsupported sales material type.');
    }

    public static function store(UploadedFile $file, string $mime): string
    {
        $name = Str::uuid()->toString().'.'.self::extension($mime);
        $stored = Storage::disk('materials')->putFileAs('', $file, $name);

        if (! is_string($stored) || $stored === '') {
            throw new RuntimeException('The sales material could not be stored.');
        }

        return $stored;
    }

    public static function stream(SalesMaterial $material): StreamedResponse
    {
        $path = $material->file_path;

        if (! is_string($path) || $path === '' || $material->purged_at !== null || ! Storage::disk('materials')->exists($path)) {
            abort(404);
        }

        $base = Str::slug($material->title);
        $filename = ($base !== '' ? $base : 'material').'-v'.$material->version.'.'.self::extension($material->mime);

        return Storage::disk('materials')->download($path, $filename, [
            'Content-Type' => $material->mime,
            'Cache-Control' => 'private',
        ]);
    }
}
