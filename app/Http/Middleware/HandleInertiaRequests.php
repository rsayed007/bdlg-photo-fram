<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * @var string
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $locale = app()->getLocale();
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'is_admin' => $user->isAdmin(),
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ] : null,
            ],
            'locale' => $locale,
            'translations' => fn () => $this->translations($locale),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'queued' => fn () => $request->session()->get('queued'),
            ],
        ];
    }

    /** @return array<string, string> */
    private function translations(string $locale): array
    {
        static $cache = [];

        return $cache[$locale] ??= (function () use ($locale) {
            $path = lang_path("{$locale}.json");

            return File::exists($path) ? (array) json_decode(File::get($path), true) : [];
        })();
    }
}
