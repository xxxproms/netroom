<?php

namespace App\Http\Requests;

use App\Models\Cable;
use App\Models\Concerns\Terminates;
use App\Models\Outlet;
use App\Models\Port;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CableRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'a_type' => ['required', Rule::in(['port', 'outlet'])],
            'a_id' => ['required', 'integer'],
            'b_type' => ['required', Rule::in(['port', 'outlet'])],
            'b_id' => ['required', 'integer'],
            'media' => ['required', Rule::in(Cable::MEDIA)],
            // Fibre is pulled as one strand or two; copper has none to state.
            'strands' => [
                'nullable',
                Rule::requiredIf($this->input('media') === 'fibre'),
                Rule::in(Cable::STRANDS),
            ],
            'label' => ['nullable', 'string', 'max:60'],
            'length_cm' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'status' => ['required', Rule::in(Cable::STATUSES)],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $a = $this->end('a');
            $b = $this->end('b');

            if (! $a instanceof Model) {
                $validator->errors()->add('a_id', __('This cable end no longer exists.'));
            }

            if (! $b instanceof Model) {
                $validator->errors()->add('b_id', __('This cable end no longer exists.'));

                return;
            }

            if (! $a instanceof Model) {
                return;
            }

            if ($a->is($b)) {
                $validator->errors()->add('b_id', __('A cable cannot end where it starts.'));

                return;
            }

            foreach (['a' => $a, 'b' => $b] as $side => $end) {
                if ($this->occupied($end)) {
                    $validator->errors()->add("{$side}_id", __('There is already a cable in this port.'));
                }
            }
        });
    }

    /**
     * The end a side points at, or null when it is gone.
     */
    public function end(string $side): (Model&Terminates)|null
    {
        $id = (int) $this->input("{$side}_id");

        return $this->input("{$side}_type") === 'outlet'
            ? Outlet::with('workplace')->find($id)
            : Port::with('device')->find($id);
    }

    public function siteOf(Model&Terminates $end): ?int
    {
        if ($end instanceof Outlet) {
            return $end->workplace->site_id;
        }

        return $end instanceof Port ? $end->device->site_id : null;
    }

    /**
     * One socket takes one cable, so anything already plugged in blocks it —
     * unless it is the very cable being edited.
     */
    private function occupied(Model&Terminates $end): bool
    {
        $cable = $end->cable();

        if (! $cable instanceof Cable) {
            return false;
        }

        return ! $cable->is($this->route('cable'));
    }
}
