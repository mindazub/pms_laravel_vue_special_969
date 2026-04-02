<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScrapboardRequest;
use App\Http\Requests\UpdateScrapboardRequest;
use App\Models\Scrapboard;
use App\Models\User;
use App\Services\ScrapboardWorkbookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ScrapboardController extends Controller
{
    public function __construct(private ScrapboardWorkbookService $workbookService) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Scrapboard::class);
        $this->provisionStarterScrapboards($request->user());

        $scrapboards = Scrapboard::query()
            ->visibleTo($request->user())
            ->orderBy('name')
            ->get();

        return $this->renderPage($scrapboards, $scrapboards->first());
    }

    public function show(Request $request, Scrapboard $scrapboard): Response
    {
        $this->authorize('view', $scrapboard);

        $scrapboards = Scrapboard::query()
            ->visibleTo($request->user())
            ->orderBy('name')
            ->get();

        return $this->renderPage($scrapboards, $scrapboard);
    }

    public function store(StoreScrapboardRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $scrapboard = Scrapboard::query()->create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'slug' => $this->generateSlug($validated['name']),
            'workbook_data' => $this->workbookService->normalize($validated['workbook'] ?? null),
        ]);

        return response()->json($this->scrapboardPayload($scrapboard->fresh()), 201);
    }

    public function update(UpdateScrapboardRequest $request, Scrapboard $scrapboard): JsonResponse
    {
        $validated = $request->validated();

        $scrapboard->update([
            'name' => $validated['name'] ?? $scrapboard->name,
            'slug' => array_key_exists('name', $validated)
                ? $this->generateSlug($validated['name'], $scrapboard->id)
                : $scrapboard->slug,
            'workbook_data' => array_key_exists('workbook', $validated)
                ? $this->workbookService->normalize($validated['workbook'])
                : $this->workbookService->normalize($scrapboard->workbook_data),
        ]);

        return response()->json($this->scrapboardPayload($scrapboard->fresh()));
    }

    public function destroy(Request $request, Scrapboard $scrapboard): JsonResponse
    {
        $this->authorize('delete', $scrapboard);

        $scrapboard->delete();

        return response()->json([
            'message' => 'Scrapboard deleted successfully.',
        ]);
    }

    private function renderPage(Collection $scrapboards, ?Scrapboard $selectedScrapboard): Response
    {
        $selected = $selectedScrapboard;

        if ($selected === null && $scrapboards->isNotEmpty()) {
            $selected = $scrapboards->first();
        }

        return Inertia::render('Scrapboards/Index', [
            'scrapboards' => $scrapboards
                ->map(fn (Scrapboard $scrapboard): array => $this->scrapboardSummary($scrapboard))
                ->values()
                ->all(),
            'selectedScrapboard' => $selected ? $this->scrapboardPayload($selected) : null,
        ]);
    }

    private function scrapboardSummary(Scrapboard $scrapboard): array
    {
        return [
            'id' => $scrapboard->id,
            'name' => $scrapboard->name,
            'slug' => $scrapboard->slug,
            'updated_at' => $scrapboard->updated_at?->toDateTimeString(),
        ];
    }

    private function scrapboardPayload(Scrapboard $scrapboard): array
    {
        return [
            ...$this->scrapboardSummary($scrapboard),
            'workbook' => $this->workbookService->normalize($scrapboard->workbook_data),
        ];
    }

    private function provisionStarterScrapboards(User $user): void
    {
        if (Scrapboard::query()->where('user_id', $user->id)->exists()) {
            return;
        }

        collect(['Scrapboard 1', 'Scrapboard 2'])->each(function (string $name) use ($user): void {
            Scrapboard::query()->create([
                'user_id' => $user->id,
                'name' => $name,
                'slug' => $this->generateSlug($name),
                'workbook_data' => $this->workbookService->defaultWorkbook(),
            ]);
        });
    }

    private function generateSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = 'scrapboard';
        }

        do {
            $candidate = $baseSlug.'-'.Str::lower(Str::random(6));
            $query = Scrapboard::query()->where('slug', $candidate);

            if ($ignoreId !== null) {
                $query->whereKeyNot($ignoreId);
            }
        } while ($query->exists());

        return $candidate;
    }
}
