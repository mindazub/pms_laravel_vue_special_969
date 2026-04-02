<?php

namespace App\Http\Middleware;

use App\Models\Scrapboard;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        $scrapboardNavigation = collect();

        if ($user !== null) {
            $scrapboardNavigation = Scrapboard::query()
                ->visibleTo($user)
                ->orderBy('name')
                ->limit(12)
                ->get(['id', 'name'])
                ->map(fn (Scrapboard $scrapboard): array => [
                    'id' => $scrapboard->id,
                    'name' => $scrapboard->name,
                ]);

            foreach (['Scrapboard 1', 'Scrapboard 2'] as $index => $fallbackName) {
                if ($scrapboardNavigation->count() > $index) {
                    continue;
                }

                $scrapboardNavigation->push([
                    'id' => null,
                    'name' => $fallbackName,
                ]);
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user === null
                    ? null
                    : [
                        ...$user->toArray(),
                        'role_names' => $user->getRoleNames()->values()->all(),
                    ],
            ],
            'scrapboardNavigation' => $scrapboardNavigation->values()->all(),
        ];
    }
}
