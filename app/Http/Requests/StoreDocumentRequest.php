<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

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
        $maxUploadKb = $this->maxUploadKilobytes();
        $rules = [];
        $uploadedFiles = $this->file('files');
        $singleFileUpload = $this->hasFile('files') && !is_array($uploadedFiles);

        if ($this->has('url')) {
            $rules = [
                'name' => 'required|string|max:255',
                'folder_id' => 'required|exists:folders,id',
                'url' => 'required|url|max:2048',
                'visibility' => 'nullable|in:public,private',
            ];
        } elseif ($this->has('folder_name')) {
            $rules = $singleFileUpload
                ? [
                    'folder_id' => 'required|exists:folders,id',
                    'folder_name' => 'required|string|max:255',
                    'files' => "required|file|max:{$maxUploadKb}",
                    'visibility' => 'nullable|in:public,private',
                ]
                : [
                    'folder_id' => 'required|exists:folders,id',
                    'folder_name' => 'required|string|max:255',
                    'files' => 'required|array|min:1',
                    'files.*' => "required|file|max:{$maxUploadKb}",
                    'visibility' => 'nullable|in:public,private',
                ];
        } else {
            $rules = $singleFileUpload
                ? [
                    'folder_id' => 'required|exists:folders,id',
                    'files' => "required|file|max:{$maxUploadKb}",
                ]
                : [
                    'folder_id' => 'required|exists:folders,id',
                    'files' => 'required|array|min:1',
                    'files.*' => "required|file|max:{$maxUploadKb}",
                ];
        }

        return $rules;
    }
    
    protected function prepareForValidation()
    {
        // Some clients may submit a scalar "files" field in addition to multipart files[];
        // drop it so validation uses the uploaded file bag only.
        if ($this->has('files') && !is_array($this->input('files')) && !$this->hasFile('files')) {
            $this->request->remove('files');
        }

        // Normalize files input
        if (!$this->hasFile('files') && $this->hasFile('files[]')) {
            $this->merge(['files' => $this->file('files[]')]);
        } elseif ($this->hasFile('files') && !is_array($this->file('files'))) {
            $this->merge(['files' => [$this->file('files')]]);
        }
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], 422));
    }

    public function wantsJson(): bool
    {
        return true;
    }

    public function expectsJson(): bool
    {
        return true;
    }

    public function messages()
    {
        $maxMb = (int) floor($this->maxUploadKilobytes() / 1024);

        return [
            'name.required' => 'The URL name is required.',
            'name.string' => 'The document name must be a string.',
            'url.required' => 'The URL is required.',
            'url.string' => 'The URL must be a string.',
            'files.required' => 'Please select at least one file.',
            'files.file' => 'The selected file is invalid.',
            'files.max' => "Each file must be {$maxMb} MB or smaller.",
            'files.array' => 'Invalid upload payload. Please choose your files again.',
            'files.uploaded' => 'Upload failed because the file exceeds the server upload limit. Increase upload_max_filesize and post_max_size in PHP settings.',
            'files.*.required' => 'Please select a file.',
            'files.*.file' => 'The selected file is invalid.',
            'files.*.uploaded' => 'A file failed to upload because it is larger than the server upload limit.',
            'files.*.max' => "Each file must be {$maxMb} MB or smaller.",
        ];
    }

    private function maxUploadKilobytes(): int
    {
        // Fixed maximum upload size of 50MB (in KB)
        $fixedLimitKb = 50 * 1024;
        
        $uploadLimitKb = $this->iniSizeToKilobytes((string) ini_get('upload_max_filesize'));
        $postLimitKb = $this->iniSizeToKilobytes((string) ini_get('post_max_size'));

        // Always use our application limit, ignore lower PHP ini limits
        // This prevents false positive limit errors when PHP reports incorrect values
        $effective = $fixedLimitKb;

        // Keep a safe fallback when PHP returns unexpected values.
        return $effective > 0 ? $effective : 2048;
    }

    private function iniSizeToKilobytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        if (ctype_alpha($unit)) {
            $number = (float) substr($value, 0, -1);
        } else {
            $number = (float) $value;
            $unit = 'b';
        }

        return match ($unit) {
            'g' => (int) round($number * 1024 * 1024),
            'm' => (int) round($number * 1024),
            'k' => (int) round($number),
            default => (int) round($number / 1024),
        };
    }
}
