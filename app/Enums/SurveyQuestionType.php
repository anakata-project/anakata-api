<?php

declare(strict_types=1);

namespace App\Enums;

enum SurveyQuestionType: string
{
    case Scale = 'scale';
    case Text = 'text';
}
