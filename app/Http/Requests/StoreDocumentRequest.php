<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
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
        $rules = [];

        if ($this->has('url')) {
            $rules = [
                'name' => 'required|string|max:255',
                'folder_id' => 'required|exists:folders,id',
                'url' => 'required|url|max:2048',
                'visibility' => 'nullable|in:public,private',
            ];
        } elseif ($this->has('folder_name')) {
            $rules = [
                'folder_id' => 'required|exists:folders,id',
                'folder_name' => 'required|string|max:255',
                'files' => 'required|array|min:1',
                'files.*' => 'required|file',
                'visibility' => 'nullable|in:public,private',
            ];
        } else {
            $rules = [
                'folder_id' => 'required|exists:folders,id',
                'files' => 'required|array|min:1',
                'files.*' => 'required|file',
            ];
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'name.required' => 'The URL name is required.',
            'name.string' => 'The document name must be a string.',
            'url.required' => 'The URL is required.',
            'url.string' => 'The URL must be a string.',
            'files.*.required' => 'Please select a file.',
            'files.*.file' => 'The selected file is invalid.',
        ];
    }
}
