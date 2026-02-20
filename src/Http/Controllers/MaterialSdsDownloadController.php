<?php

declare(strict_types=1);

namespace Lastdino\Matex\Http\Controllers;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Lastdino\Matex\Models\Material;

class MaterialSdsDownloadController extends Controller
{
    public function __invoke(Request $request, Material $material): Response
    {
        // 認可（必要に応じて Gate/Policy を差し替え）
        if (method_exists($material, 'can') && Gate::has('view-material-sds')) {
            Gate::authorize('view-material-sds', $material);
        }

        $media = $material->getFirstMedia('sds');
        if (! $media) {
            abort(404);
        }

        $disk = $media->disk;
        $path = $media->getPath();

        try {
            $stream = Storage::disk($disk)->readStream($media->getPathRelativeToRoot());
            if ($stream === false) {
                throw new FileNotFoundException($path);
            }
        } catch (\Throwable $e) {
            abort(404);
        }

        $filename = $media->file_name ?? 'sds.pdf';
        $mime = $media->mime_type ?: 'application/pdf';

        return response()->streamDownload(function () use ($stream): void {
            fpassthru($stream);
        }, $filename, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
