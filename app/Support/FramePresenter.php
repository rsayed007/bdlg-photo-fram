<?php

namespace App\Support;

use App\Models\Frame;
use App\Models\FrameGeneration;

class FramePresenter
{
    /** @return array<string, mixed> */
    public static function card(Frame $frame): array
    {
        return [
            'id' => $frame->id,
            'slug' => $frame->slug,
            'title' => $frame->title,
            'event' => $frame->event?->name,
            'thumbnail_url' => $frame->assetUrl('thumbnail'),
            'size_w' => $frame->size_w,
            'size_h' => $frame->size_h,
            'create_url' => route('frames.create', $frame),
        ];
    }

    /**
     * Everything the editor needs to render the frame.
     *
     * @return array<string, mixed>
     */
    public static function editor(Frame $frame): array
    {
        return [
            ...self::card($frame),
            'background_url' => $frame->assetUrl('background'),
            'overlay_url' => $frame->assetUrl('overlay'),
            'config' => $frame->config,
        ];
    }

    /** @return array<string, mixed> */
    public static function generationCard(FrameGeneration $generation): array
    {
        return [
            'id' => $generation->id,
            'image_url' => $generation->imageUrl(),
            'share_url' => $generation->shareUrl(),
            'frame_title' => $generation->frame->title,
            'guide_name' => $generation->localGuide->name ?? ($generation->stats_snapshot['name'] ?? null),
            'created_at' => $generation->created_at->toIso8601String(),
        ];
    }
}
