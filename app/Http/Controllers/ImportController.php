<?php

namespace App\Http\Controllers;

use App\Support\Export\InventoryExport;
use App\Support\Import\ApplyCableImport;
use App\Support\Import\ApplySwitchImport;
use App\Support\Import\CableWorkbookParser;
use App\Support\Import\SwitchWorkbookParser;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        private readonly InventoryExport $export,
        private readonly CableWorkbookParser $cableParser,
        private readonly ApplyCableImport $cableApply,
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

        $filename = $request->file('file')->getClientOriginalName();

        // Our own export carries a Commutation sheet — treat it as the return
        // leg of the cabling round-trip rather than a switch registry.
        if (CableWorkbookParser::handles(Storage::path($path))) {
            return $this->cablingPreview($token, $filename, Storage::path($path));
        }

        $parsed = $this->parser->parse(Storage::path($path));

        return Inertia::render('import/Preview', [
            'token' => $token,
            'filename' => $filename,
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

    /**
     * The cabling round-trip preview: what the Commutation sheet would lay,
     * refresh, or skip — worked out against the panel, nothing written yet.
     */
    private function cablingPreview(string $token, string $filename, string $path): Response
    {
        $parsed = $this->cableParser->parse($path);

        return Inertia::render('import/Cabling', [
            'token' => $token,
            'filename' => $filename,
            'counts' => $parsed['counts'],
            'rows' => array_map(fn (array $row) => [
                'label' => $row['label'],
                'a' => $row['a'],
                'b' => $row['b'],
                'media' => $row['media'],
                'color' => $row['color'],
                'status' => $row['status'],
                'action' => $row['action'],
                'reason' => $row['reason'],
            ], $parsed['rows']),
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

        if (CableWorkbookParser::handles(Storage::path($path))) {
            return $this->commitCabling(Storage::path($path), $path);
        }

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

    /**
     * @param  string  $fullPath  the stored file on disk
     * @param  string  $path  its path within the storage disk, to clean up
     */
    private function commitCabling(string $fullPath, string $path): RedirectResponse
    {
        $result = $this->cableApply->apply($this->cableParser->parse($fullPath));

        Storage::delete($path);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Cabling imported: :created laid, :updated refreshed, :skipped skipped.', [
                'created' => $result['created'],
                'updated' => $result['updated'],
                'skipped' => $result['skipped'],
            ]),
        ]);

        return to_route('cables.index');
    }

    /**
     * Hand the current scope back as a workbook — the export half of the pair.
     */
    public function export(): StreamedResponse
    {
        $this->authorizeImport();

        $contents = $this->export->contents();
        $filename = 'netroom-'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(
            fn () => print ($contents),
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
