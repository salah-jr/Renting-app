<?php

use App\Models\Office;
use Illuminate\Validation\Rule;

class OfficeValidator{

    public function validate(Office $office, array $attribute): array
    {
        return validator(request()->all(), [
            'title' => [Rule::when($office->exists, 'sometimes'),'requried', 'string'],
            'description' => [Rule::when($office->exists, 'sometimes'),'requried', 'string'],
            'lat' => [Rule::when($office->exists, 'sometimes'),'requried', 'numeric'],
            'lng' => [Rule::when($office->exists, 'sometimes'),'requried', 'numeric'],
            'address_line1' => [Rule::when($office->exists, 'sometimes'),'required', 'string'],
            'price_per_day' => [Rule::when($office->exists, 'sometimes'),'required', 'integer', 'min:100'],
            'hidden' => ['bool'],
            'monthly' => ['integer', 'min:0', 'max:90'],
            'tags' => ['array'],
            'tags.*' => ['integer', Rule::exists('tags', 'id')],

        ])->validate();
    }

}
