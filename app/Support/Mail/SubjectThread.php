<?php

declare(strict_types=1);

namespace App\Support\Mail;

final class SubjectThread
{
    public static function normalise(string $subject): string
    {
        $subject = trim($subject);
        $previous = null;

        while ($subject !== $previous) {
            $previous = $subject;
            $stripped = preg_replace('/^(re|fwd|fw)\s*:\s*/iu', '', $subject);
            $subject = trim(is_string($stripped) ? $stripped : $subject);
        }

        if ($subject === '') {
            return '(no subject)';
        }

        if (mb_strlen($subject) <= 500) {
            return $subject;
        }

        return mb_substr($subject, 0, 500);
    }
}
