<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use App\Enums\TemplateVariable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTemplateDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'array'],
            'body.paragraphs' => ['required', 'array', 'min:1'],
            'body.paragraphs.*' => ['required', 'string'],
            'body.list' => ['present', 'array'],
            'body.list.*' => ['string'],
            'body.cta' => ['nullable', 'array'],
            'body.cta.label' => ['required_with:body.cta', 'string', 'max:120'],
            'body.cta.link_key' => ['required_with:body.cta', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var array<string, mixed> $body */
            $body = $this->input('body', []);
            $texts = [];

            if (is_string($this->input('subject'))) {
                $texts[] = $this->string('subject')->toString();
            }

            foreach (is_array($body['paragraphs'] ?? null) ? $body['paragraphs'] : [] as $paragraph) {
                if (is_string($paragraph)) {
                    $texts[] = $paragraph;
                }
            }

            foreach (is_array($body['list'] ?? null) ? $body['list'] : [] as $item) {
                if (is_string($item)) {
                    $texts[] = $item;
                }
            }

            $cta = $body['cta'] ?? null;

            if (is_array($cta)) {
                if (is_string($cta['label'] ?? null)) {
                    $texts[] = $cta['label'];
                }

                $link = $cta['link_key'] ?? null;
                $allowed = [
                    TemplateVariable::DepositLink->value,
                    TemplateVariable::CompleteLink->value,
                    TemplateVariable::UnsubscribeLink->value,
                ];

                if (is_string($link) && ! in_array($link, $allowed, true)) {
                    $validator->errors()->add('body.cta.link_key', 'The call to action must use a link variable.');
                }
            }

            foreach ($texts as $text) {
                if (preg_match('/<\s*\/?\s*[a-z!]|javascript\s*:/i', $text) === 1) {
                    $validator->errors()->add('body', 'A template body cannot contain HTML.');

                    return;
                }
            }
        });
    }

    /**
     * @return array{paragraphs: list<string>, list: list<string>, cta: array{label: string, link_key: string}|null}
     */
    public function body(): array
    {
        /** @var array<string, mixed> $body */
        $body = $this->validated('body');
        /** @var list<string> $paragraphs */
        $paragraphs = array_values($body['paragraphs']);
        /** @var list<string> $list */
        $list = array_values($body['list']);
        $cta = $body['cta'] ?? null;

        return [
            'paragraphs' => $paragraphs,
            'list' => $list,
            'cta' => is_array($cta) ? [
                'label' => (string) $cta['label'],
                'link_key' => (string) $cta['link_key'],
            ] : null,
        ];
    }
}
