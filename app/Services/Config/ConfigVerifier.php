<?php

declare(strict_types=1);

namespace App\Services\Config;

use App\Models\ConfigVersion;
use App\Support\Config\ConfigVerifyFailure;
use App\Support\Config\ConfigVerifyReport;
use Illuminate\Support\Facades\Validator;

final class ConfigVerifier
{
    public function __construct(private readonly ConfigRegistry $registry) {}

    public function report(): ConfigVerifyReport
    {
        $valid = [];
        $failures = [];

        foreach ($this->registry->kinds() as $kind) {
            $modelClass = $kind->modelClass();
            $row = $modelClass::query()->orderByDesc('version')->first();

            if (! $row instanceof ConfigVersion) {
                $failures[] = new ConfigVerifyFailure(
                    $kind,
                    null,
                    null,
                    'No published '.$kind->label().' — run the seeders',
                );

                continue;
            }

            $document = $row->document ?? [];
            $validator = Validator::make($document, $kind->documentClass()::rules());

            if ($validator->fails()) {
                /** @var array<string, list<string>> $errors */
                $errors = $validator->errors()->toArray();

                foreach ($errors as $path => $messages) {
                    foreach ($messages as $message) {
                        $failures[] = new ConfigVerifyFailure(
                            $kind,
                            $row->version,
                            $path,
                            $message,
                        );
                    }
                }

                continue;
            }

            $valid[] = $kind->value.' v'.$row->version.': valid';
        }

        return new ConfigVerifyReport($valid, $failures);
    }
}
