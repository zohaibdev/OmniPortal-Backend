<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
            'folder' => 'nullable|string|max:100',
        ]);

        $file = $request->file('file');
        $folder = $request->get('folder', 'uploads');

        // Generate unique filename
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

        // Store file
        $path = $file->storeAs($folder, $filename, 'public');

        return response()->json([
            'message' => 'File uploaded',
            'url' => Storage::url($path),
            'path' => $path,
            'filename' => $filename,
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);
    }

    public function uploadMultiple(Request $request): JsonResponse
    {
        $request->validate([
            'files' => 'required|array|max:10',
            'files.*' => 'file|max:10240',
            'folder' => 'nullable|string|max:100',
        ]);

        $folder = $request->get('folder', 'uploads');
        $uploaded = [];

        foreach ($request->file('files') as $file) {
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs($folder, $filename, 'public');

            $uploaded[] = [
                'url' => Storage::url($path),
                'path' => $path,
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
            ];
        }

        return response()->json([
            'message' => 'Files uploaded',
            'files' => $uploaded,
        ]);
    }

    public function delete(Request $request): JsonResponse
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        if (Storage::disk('public')->exists($request->path)) {
            Storage::disk('public')->delete($request->path);

            return response()->json([
                'message' => 'File deleted',
            ]);
        }

        return response()->json([
            'message' => 'File not found',
        ], 404);
    }
}
