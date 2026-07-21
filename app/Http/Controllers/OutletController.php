<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\Workplace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class OutletController extends Controller
{
    public function store(Request $request, Workplace $workplace): RedirectResponse
    {
        $this->authorize('update', $workplace);

        $workplace->outlets()->create($this->validated($request, $workplace));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Outlet added.')]);

        return back();
    }

    public function update(Request $request, Outlet $outlet): RedirectResponse
    {
        $this->authorize('update', $outlet);

        $outlet->update($this->validated($request, $outlet->workplace, $outlet));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Outlet updated.')]);

        return back();
    }

    public function destroy(Outlet $outlet): RedirectResponse
    {
        $this->authorize('delete', $outlet);

        $outlet->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Outlet deleted.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Workplace $workplace, ?Outlet $outlet = null): array
    {
        return $request->validate([
            'label' => [
                'required', 'string', 'max:60',
                Rule::unique('outlets')->where('workplace_id', $workplace->id)->ignore($outlet),
            ],
            'media' => ['required', Rule::in(Outlet::MEDIA)],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
