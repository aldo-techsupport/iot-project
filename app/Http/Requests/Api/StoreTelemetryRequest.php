<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreTelemetryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'temperature' => ['required', 'numeric', 'between:-20,80'],
            'humidity' => ['required', 'numeric', 'between:0,100'],
            'noise_db' => ['required', 'numeric', 'between:0,130'],
            'measured_at' => ['nullable', 'date_format:Y-m-d\TH:i:sP,Y-m-d\TH:i:s.uP,Y-m-d H:i:s'],
        ];
    }

    public function messages(): array
    {
        return [
            'temperature.between' => 'Temperature must be between -20°C and 80°C',
            'humidity.between' => 'Humidity must be between 0% and 100%',
            'noise_db.between' => 'Noise level must be between 0dB and 130dB',
            'measured_at.date_format' => 'measured_at must be in ISO 8601 format (e.g., 2025-12-22T10:30:00+07:00)',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'data' => null,
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }

    public function getMeasuredAt(): \Carbon\Carbon
    {
        if ($this->filled('measured_at')) {
            return \Carbon\Carbon::parse($this->input('measured_at'));
        }
        return now();
    }
}
