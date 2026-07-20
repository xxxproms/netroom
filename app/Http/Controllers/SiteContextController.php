<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Support\SiteContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SiteContextController extends Controller
{
    /**
     * Switch the site the panel is showing. An empty value means "all sites".
     */
    public function update(Request $request, SiteContext $context): RedirectResponse
    {
        $validated = $request->validate([
            'site_id' => ['nullable', 'exists:sites,id'],
        ]);

        $site = $validated['site_id'] ? Site::findOrFail((int) $validated['site_id']) : null;

        if ($site) {
            $this->authorize('view', $site);
        }

        $context->remember($site);

        return back();
    }
}
