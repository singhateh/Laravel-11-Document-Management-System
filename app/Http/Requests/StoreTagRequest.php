<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTagRequest extends FormRequest
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

    public function rules()
    {
        return [
            'category_name' => 'required',
            'tags.*' => 'required',
            'folder_name' => 'required'
        ];
    }

    public function messages()
    {
        return [
            'category_name.required' => 'The category name is required.',
            'tags.*.required' => 'At least one tag is required.',
            'folder_name.required' => 'The folder name is required.'
        ];
    }
}
