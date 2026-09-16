<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if (! $request->hasFile('file')) {
            return ApiResponse::success(null, 'No file received');
        }

        $request->validate([
            'file' => ['file', 'max:'.config('dailzo.upload_max_kb'), 'mimes:jpg,jpeg,png,webp,gif,svg'],
        ]);

        $file = $request->file('file');
        $ext = $file->getClientOriginalExtension() ?: 'png';
        $name = now()->getTimestampMs().'-'.random_int(0, 999_999_999).'.'.$ext;

        Storage::disk('uploads')->putFileAs('', $file, $name);

        return ApiResponse::success([
            'fileName' => $file->getClientOriginalName(),
            'storedAs' => $name,
            'url' => 'uploads/'.$name,
        ], 'File uploaded successfully');
    }
}
