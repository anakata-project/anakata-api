<?php

declare(strict_types=1);

namespace App\Support\Templates;

use App\Models\MessageTemplateVersion;

final class RenderedTemplate
{
    public function __construct(
        public MessageTemplateVersion $version,
        public string $subject,
        public string $html,
    ) {}
}
