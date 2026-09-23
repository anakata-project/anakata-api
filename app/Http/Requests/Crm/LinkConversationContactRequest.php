<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;

class LinkConversationContactRequest extends FormRequest
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
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
        ];
    }

    public function contact(): Contact
    {
        $contact = Contact::query()->findOrFail($this->integer('contact_id'));

        return $contact->currentSurvivor();
    }
}
