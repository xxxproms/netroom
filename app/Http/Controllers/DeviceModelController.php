<?php

namespace App\Http\Controllers;

use App\Models\DeviceModel;
use App\Models\PortTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DeviceModelController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', DeviceModel::class);

        $models = DeviceModel::with('portTemplates')
            ->orderBy('vendor')
            ->orderBy('model')
            ->get()
            ->map(fn (DeviceModel $model) => [
                'id' => $model->id,
                'vendor' => $model->vendor,
                'model' => $model->model,
                'kind' => $model->kind,
                'u_height' => $model->u_height,
                'notes' => $model->notes,
                'port_count' => $model->portTemplates->sum('count'),
                'port_templates' => $model->portTemplates->values()->map(fn (PortTemplate $template) => [
                    'id' => $template->id,
                    'name_prefix' => $template->name_prefix,
                    'start_number' => $template->start_number,
                    'count' => $template->count,
                    'media' => $template->media,
                    'speed_mbps' => $template->speed_mbps,
                    'role' => $template->role,
                ])->all(),
            ]);

        return Inertia::render('device-models/Index', [
            'models' => $models,
            'kinds' => DeviceModel::KINDS,
            'media' => PortTemplate::MEDIA,
            'roles' => PortTemplate::ROLES,
            'can' => [
                'manage' => request()->user()->can('create', DeviceModel::class),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', DeviceModel::class);

        $validated = $this->validated($request);
        $templates = $validated['port_templates'] ?? [];
        unset($validated['port_templates']);

        $model = DeviceModel::create($validated);
        $this->syncTemplates($model, $templates);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Model created.')]);

        return to_route('device-models.index');
    }

    public function update(Request $request, DeviceModel $deviceModel): RedirectResponse
    {
        $this->authorize('update', $deviceModel);

        $validated = $this->validated($request, $deviceModel);
        $templates = $validated['port_templates'] ?? [];
        unset($validated['port_templates']);

        $deviceModel->update($validated);
        $this->syncTemplates($deviceModel, $templates);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Model updated.')]);

        return to_route('device-models.index');
    }

    public function destroy(DeviceModel $deviceModel): RedirectResponse
    {
        $this->authorize('delete', $deviceModel);

        $deviceModel->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Model deleted.')]);

        return to_route('device-models.index');
    }

    /**
     * Port templates are edited as a whole list, so they are replaced rather
     * than diffed. Devices already created keep the ports they were given.
     *
     * @param  array<int, array<string, mixed>>  $templates
     */
    private function syncTemplates(DeviceModel $model, array $templates): void
    {
        $model->portTemplates()->delete();

        foreach ($templates as $sort => $template) {
            $model->portTemplates()->create([
                ...$template,
                // Most ports are numbered without a prefix, and the form sends
                // that as an empty field rather than an empty string.
                'name_prefix' => $template['name_prefix'] ?? '',
                'sort' => $sort,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?DeviceModel $model = null): array
    {
        return $request->validate([
            'vendor' => ['required', 'string', 'max:60'],
            'model' => [
                'required', 'string', 'max:120',
                Rule::unique('device_models', 'model')
                    ->where('vendor', $request->input('vendor'))
                    ->ignore($model),
            ],
            'kind' => ['required', Rule::in(DeviceModel::KINDS)],
            'u_height' => ['required', 'integer', 'min:1', 'max:10'],
            'notes' => ['nullable', 'string'],
            'port_templates' => ['array'],
            'port_templates.*.name_prefix' => ['nullable', 'string', 'max:20'],
            'port_templates.*.start_number' => ['required', 'integer', 'min:1', 'max:999'],
            'port_templates.*.count' => ['required', 'integer', 'min:1', 'max:200'],
            'port_templates.*.media' => ['required', Rule::in(PortTemplate::MEDIA)],
            'port_templates.*.speed_mbps' => ['nullable', 'integer', 'min:10'],
            'port_templates.*.role' => ['required', Rule::in(PortTemplate::ROLES)],
        ]);
    }
}
