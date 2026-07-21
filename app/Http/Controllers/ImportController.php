<?php

namespace App\Http\Controllers;

use App\Support\Import\ApplySwitchImport;
use App\Support\Import\SwitchWorkbookParser;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bringing the department's spreadsheet in: upload, look at exactly what would
 * be created and what the sheet gets wrong, then commit. The file is held on
 * disk between the two steps so the preview and the import read the same bytes.
 */
class ImportController extends Controller
{
    public function __construct(
        private readonly SwitchWorkbookParser $parser,
        private readonly ApplySwitchImport $apply,
    ) {}

    private function authorizeImport(): void
    {
        abort_unless(request()->user()?->can(Permissions::IMPORT_EXPORT), 403);
    }

    public function create(): Response
    {
        $this->authorizeImport();

        return Inertia::render('import/Index');
    }

    public function preview(Request $request): Response
    {
        $this->authorizeImport();

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        // Kept under a token so the commit step reads the very same upload.
        $token = Str::uuid()->toString();
        $path = $request->file('file')->storeAs('imports', "{$token}.xlsx");

        abort_if($path === false, 500, 'The upload could not be stored.');

        $parsed = $this->parser->parse(Storage::path($path));

        return Inertia::render('import/Preview', [
            'token' => $token,
            'filename' => $request->file('file')->getClientOriginalName(),
            'domain' => $parsed['domain'],
            'sites' => $parsed['sites'],
            'counts' => $parsed['counts'],
            'vlans' => $parsed['vlans'],
            'switches' => array_map(fn (array $switch) => [
                'name' => $switch['name'],
                'site' => $switch['site'],
                'model' => $switch['model'],
                'mgmt_ip' => $switch['mgmt_ip'],
                'port_count' => $switch['port_count'],
                'uplinks' => $switch['uplinks'],
                'memberships' => count($switch['memberships']),
                'has_warning' => $switch['warnings'] !== [],
            ], $parsed['switches']),
            'warnings' => $parsed['warnings'],
        ]);
    }

    public function commit(Request $request): RedirectResponse
    {
        $this->authorizeImport();

        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $path = "imports/{$validated['token']}.xlsx";

        abort_unless(Storage::exists($path), 404);

        $parsed = $this->parser->parse(Storage::path($path));
        $result = $this->apply->apply($parsed);

        Storage::delete($path);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Imported :devices switches and :memberships VLAN assignments.', [
                'devices' => $result['devices'],
                'memberships' => $result['memberships'],
            ]),
        ]);

        return to_route('devices.index');
    }
}
