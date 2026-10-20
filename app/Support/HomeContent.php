<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Admin-editable home page content (hero text per locale + community links).
 */
class HomeContent
{
    public const KEY = 'home';

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'title' => [
                'bn' => 'বাংলাদেশ লোকাল গাইডস',
                'en' => 'Bangladesh Local Guides',
            ],
            'tagline' => [
                'bn' => 'গুগল ম্যাপসে বাংলাদেশকে তুলে ধরি — একসাথে।',
                'en' => 'Putting Bangladesh on Google Maps — together.',
            ],
            'intro' => [
                'bn' => 'আমরা বাংলাদেশের গুগল ম্যাপস লোকাল গাইডদের কমিউনিটি। রিভিউ, ছবি আর নতুন জায়গা যোগ করে আমরা সবাইকে সঠিক তথ্য খুঁজে পেতে সাহায্য করি। আপনার প্রোফাইল যোগ করুন, লিডারবোর্ডে নিজেকে দেখুন, আর মিট-আপের ফ্রেম বানিয়ে শেয়ার করুন।',
                'en' => 'We are the community of Google Maps Local Guides in Bangladesh. Through reviews, photos and new places we help everyone find accurate information. Add your profile, see yourself on the leaderboard, and create and share meet-up photo frames.',
            ],
            'links' => [
                ['label' => 'Facebook Group', 'url' => 'https://www.facebook.com/groups/'],
                ['label' => 'Local Guides Connect', 'url' => 'https://www.localguidesconnect.com/'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function get(): array
    {
        $stored = Setting::get(self::KEY, []);

        return array_replace(self::defaults(), is_array($stored) ? $stored : []);
    }

    /**
     * Resolve the localized strings for the current locale.
     *
     * @return array{title: string, tagline: string, intro: string, links: list<array{label: string, url: string}>}
     */
    public static function forLocale(string $locale): array
    {
        $content = self::get();
        $pick = fn (string $key): string => (string) ($content[$key][$locale] ?? $content[$key]['en'] ?? '');

        return [
            'title' => $pick('title'),
            'tagline' => $pick('tagline'),
            'intro' => $pick('intro'),
            'links' => array_values(array_filter(
                $content['links'] ?? [],
                fn ($l) => is_array($l) && ! empty($l['label']) && ! empty($l['url']),
            )),
        ];
    }
}
