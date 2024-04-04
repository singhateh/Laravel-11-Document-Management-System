<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFileRequestRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'request_to' => 'nullable|string|max:255',
            'folder_id' => 'nullable|string|max:255',
            'tag_id' => 'nullable|string|max:255',
            'due_date_in_number' => 'nullable|string|max:255',
            'due_date_in_word' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:255',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'The document name is required.',
            'name.string' => 'The document name must be a string.',
            'name.max' => 'The document name must not exceed 255 characters.',
            'request_to.string' => 'The request to field must be a string.',
            'request_to.max' => 'The request to field must not exceed 255 characters.',
            'folder_id.string' => 'The folder ID field must be a string.',
            'folder_id.max' => 'The folder ID field must not exceed 255 characters.',
            'tag_id.string' => 'The tag ID field must be a string.',
            'tag_id.max' => 'The tag ID field must not exceed 255 characters.',
            'due_date_in_number.string' => 'The due date in number field must be a string.',
            'due_date_in_number.max' => 'The due date in number field must not exceed 255 characters.',
            'due_date_in_word.string' => 'The due date in word field must be a string.',
            'due_date_in_word.max' => 'The due date in word field must not exceed 255 characters.',
            'note.string' => 'The note field must be a string.',
            'note.max' => 'The note field must not exceed 255 characters.',
        ];
    }
}
