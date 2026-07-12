<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicStorageController extends Controller
{
    public function show(string $path): BinaryFileResponse
    {
        $path = ltrim(str_replace(['..', '\\'], ['', '/'], $path), '/');

        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $absolutePath = Storage::disk('public')->path($path);

        return response()->file($absolutePath, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
