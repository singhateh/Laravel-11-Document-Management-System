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
            'folder_id' => 'nullable|exists:folders,id',
            'tag_id' => 'nullable|exists:tags,id',
            'due_date_in_number' => 'nullable|integer|min:0',
            'note' => 'nullable|string',
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
            'folder_id.exists' => 'The selected folder does not exist.',
            'tag_id.exists' => 'The selected tag does not exist.',
            'due_date_in_number.integer' => 'The due date must be a number.',
            'note.string' => 'The note field must be a string.',
        ];
    }
}
