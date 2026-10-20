<?php

namespace App\Http\Controllers;

use App\Enums\GuideStatus;
use App\Jobs\SyncLocalGuide;
use App\Models\LocalGuide;
use App\Services\GoogleMaps\GuideRegistrar;
use App\Services\GoogleMaps\InvalidProfileUrlException;
use App\Support\GuidePresenter;
use App\Support\Rankings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GuideController extends Controller
{
    /** Module A: add a profile from a pasted URL / short link / share text. */
    public function store(Request $request, GuideRegistrar $registrar): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'profile_url' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $result = $registrar->register($data['profile_url'], $request->user());
        } catch (InvalidProfileUrlException $e) {
            throw ValidationException::withMessages(['profile_url' => $e->userMessage()]);
        }

        $guide = $result['guide'];
        $payload = [
            'contrib_id' => $guide->contrib_id,
            'created' => $result['created'],
            'queued' => $result['queued'],
            'position' => $result['position'],
        ];

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['guide' => GuidePresenter::summary($guide), ...$payload], $result['created'] ? 201 : 200);
        }

        if ($request->input('redirect') === 'back') {
            return back()->with('queued', $payload);
        }

        return redirect()->route('guides.show', $guide)->with('queued', $payload);
    }

    public function show(LocalGuide $guide): Response
    {
        abort_if($guide->status === GuideStatus::Inactive, 404);

        $guide->load(['frameGenerations' => fn ($q) => $q->with('frame:id,title,slug')->latest('created_at')->limit(12)]);

        return Inertia::render('guides/show', [
            'guide' => GuidePresenter::detail($guide),
            'ranks' => $guide->status === GuideStatus::Active ? Rankings::for($guide) : null,
            'history' => GuidePresenter::history($guide),
            'generations' => $guide->frameGenerations->map(fn ($g) => [
                'id' => $g->id,
                'image_url' => $g->imageUrl(),
                'share_url' => $g->shareUrl(),
                'frame_title' => $g->frame->title,
                'created_at' => $g->created_at->toIso8601String(),
            ]),
        ]);
    }

    /** Polled by the pending profile page and the frame editor freshness check. */
    public function status(LocalGuide $guide, GuideRegistrar $registrar): JsonResponse
    {
        return response()->json([
            'guide' => GuidePresenter::summary($guide),
            'stale' => $guide->isStale((int) config('localguides.sync.stale_after_hours', 24)),
            'position' => $guide->status === GuideStatus::Pending ? $registrar->queuePosition() : null,
        ]);
    }

    /** Freshness rule: queue a priority sync if stats are older than 24 h. */
    public function refresh(Request $request, LocalGuide $guide): JsonResponse
    {
        $stale = $guide->isStale((int) config('localguides.sync.stale_after_hours', 24));

        if ($stale && $guide->status !== GuideStatus::Inactive) {
            SyncLocalGuide::dispatch($guide, true);
        }

        return response()->json([
            'queued' => $stale,
            'guide' => GuidePresenter::summary($guide),
        ]);
    }
}
