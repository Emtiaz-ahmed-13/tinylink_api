<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUrlRequest;
use App\Models\Url;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UrlController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 10), 50);

        $urls = $request->user()
            ->urls()
            ->latest()
            ->paginate($perPage);

        return $this->success('URLs retrieved successfully', $urls);
    }

    public function store(StoreUrlRequest $request): JsonResponse
    {
        $code = $request->validated('custom_code') ?: $this->uniqueShortCode();

        $url = $request->user()->urls()->create([
            'original_url' => $request->validated('url'),
            'short_code' => $code,
        ]);

        return $this->success('URL shortened successfully', $url, 201);
    }

    public function show(Url $url): JsonResponse
    {
        $this->authorize('view', $url);

        return $this->success('URL retrieved successfully', $url);
    }

    public function destroy(Url $url): JsonResponse
    {
        $this->authorize('delete', $url);
        $url->delete();

        return $this->success('URL deleted successfully');
    }

    private function uniqueShortCode(): string
    {
        do {
            $code = Str::random(5);
        } while (Url::where('short_code', $code)->exists());

        return $code;
    }
}
