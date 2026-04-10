<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShareDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shared_id' => ['required'],
            'token' => ['nullable', 'string', 'max:255', 'unique:share_documents,token'],
            'slug' => ['required', 'in:document,folder,stego'],
            'name' => ['required', 'string', 'max:255'],
            'valid_until' => ['nullable', 'date', 'after:now'],
            'visibility' => ['nullable', 'in:public,private'],
            'permission_level' => ['nullable', 'in:viewer,commenter,editor,co_owner,owner'],
        ];
    }
}
