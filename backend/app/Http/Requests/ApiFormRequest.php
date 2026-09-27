<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Authorization is handled by route permission middleware and policies; requests only validate. */
abstract class ApiFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function isUpdate(): bool
    {
        return in_array($this->method(), ['PUT', 'PATCH'], true);
    }

    /** "required" on create, "sometimes|required" on update. */
    protected function requiredOnCreate(): array
    {
        return $this->isUpdate() ? ['sometimes', 'required'] : ['required'];
    }
}
