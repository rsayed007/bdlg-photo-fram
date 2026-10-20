<?php

namespace App\Http\Controllers;

use App\Models\FrameGeneration;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShareController extends Controller
{
    public function show(FrameGeneration $generation): Response
    {
        $generation->load(['frame.event', 'localGuide']);

        $name = $generation->localGuide?->name ?? ($generation->stats_snapshot['name'] ?? __('site.name'));
        $eventName = $generation->frame->event?->name ?? $generation->frame->title;
        $title = "{$name} — {$generation->frame->title}";

        return Inertia::render('share', [
            'generation' => [
                'id' => $generation->id,
                'image_url' => $generation->imageUrl(),
                'download_url' => route('share.download', $generation),
                'share_url' => $generation->shareUrl(),
                'downloads' => $generation->downloads,
                'name' => $name,
                'event' => $eventName,
                'frame_title' => $generation->frame->title,
                'frame_create_url' => route('frames.create', $generation->frame),
                'guide_url' => $generation->localGuide ? route('guides.show', $generation->localGuide) : null,
                'width' => $generation->frame->size_w,
                'height' => $generation->frame->size_h,
                'created_at' => $generation->created_at->toIso8601String(),
            ],
            'meta' => [
                'type' => 'article',
                'title' => $title,
                'description' => __('share.og_description', ['name' => $name, 'event' => $eventName]),
                'url' => $generation->shareUrl(),
                'image' => $generation->imageUrl(),
                'image_width' => $generation->frame->size_w,
                'image_height' => $generation->frame->size_h,
            ],
        ]);
    }

    public function download(FrameGeneration $generation): StreamedResponse
    {
        abort_unless(Storage::exists($generation->output_path), 404);

        $generation->increment('downloads');

        $filename = Str::slug(($generation->stats_snapshot['name'] ?? 'frame').'-'.$generation->frame->slug).'.png';

        return Storage::download($generation->output_path, $filename, ['Content-Type' => 'image/png']);
    }
}
