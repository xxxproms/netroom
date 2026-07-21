<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Rack;
use App\Models\Workplace;
use App\Support\QrCode;
use App\Support\SiteContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Printable QR labels for the physical estate: a sheet of stickers, each one
 * carrying an item's name and a code that opens its page in the panel. What
 * you can label is what the site picker lets you see.
 */
class LabelController extends Controller
{
    private const TYPES = ['devices', 'racks', 'workplaces'];

    public function __construct(
        private readonly SiteContext $context,
        private readonly QrCode $qr,
    ) {}

    public function index(): Response
    {
        return Inertia::render('labels/Index', [
            'counts' => [
                'devices' => $this->context->scope(Device::query())->count(),
                'racks' => Rack::whereHas('room', fn (Builder $q) => $this->context->scope($q))->count(),
                'workplaces' => $this->context->scope(Workplace::query())->count(),
            ],
        ]);
    }

    /**
     * The standalone print sheet — deliberately not an app page, so the browser
     * prints labels and nothing else.
     */
    public function print(Request $request): View
    {
        $type = (string) $request->query('type', 'devices');

        if (! in_array($type, self::TYPES, true)) {
            abort(404);
        }

        return view('labels.print', [
            'type' => $type,
            'labels' => $this->labels($type),
        ]);
    }

    /**
     * @return array<int, array{title: string, lines: list<string>, qr: string}>
     */
    private function labels(string $type): array
    {
        return match ($type) {
            'racks' => $this->rackLabels(),
            'workplaces' => $this->workplaceLabels(),
            default => $this->deviceLabels(),
        };
    }

    /**
     * @return array<int, array{title: string, lines: list<string>, qr: string}>
     */
    private function deviceLabels(): array
    {
        return $this->context->scope(Device::query())
            ->with(['site:id,name', 'deviceModel:id,vendor,model'])
            ->orderBy('name')
            ->get()
            ->map(fn (Device $device) => $this->label(
                $device->name,
                array_filter([
                    $device->deviceModel?->model,
                    $device->mgmt_ip,
                    $device->site?->name,
                ]),
                route('devices.show', $device),
            ))
            ->all();
    }

    /**
     * @return array<int, array{title: string, lines: list<string>, qr: string}>
     */
    private function rackLabels(): array
    {
        return Rack::whereHas('room', fn (Builder $q) => $this->context->scope($q))
            ->with(['room:id,name,site_id', 'room.site:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Rack $rack) => $this->label(
                $rack->name,
                array_filter([$rack->room?->name, $rack->room?->site?->name]),
                route('racks.show', $rack),
            ))
            ->all();
    }

    /**
     * @return array<int, array{title: string, lines: list<string>, qr: string}>
     */
    private function workplaceLabels(): array
    {
        return $this->context->scope(Workplace::query())
            ->with(['site:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Workplace $workplace) => $this->label(
                $workplace->name,
                array_filter([$workplace->person, $workplace->site?->name]),
                route('workplaces.show', $workplace),
            ))
            ->all();
    }

    /**
     * @param  array<int, string|null>  $lines
     * @return array{title: string, lines: list<string>, qr: string}
     */
    private function label(string $title, array $lines, string $url): array
    {
        return [
            'title' => $title,
            'lines' => array_values(array_map(fn ($line) => (string) $line, $lines)),
            'qr' => $this->qr->svg($url, 128),
        ];
    }
}
