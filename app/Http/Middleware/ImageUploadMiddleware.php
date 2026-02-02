<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ImageUploadMiddleware
{
    /**
     * Handle an incoming request.
     * Validates image uploads - reusable for any image field across the system.
     *
     * @param  string  $fieldName  Form field name for the image (e.g., profile_image, avatar)
     * @param  int  $maxSizeKb  Max file size in KB (default 2048 = 2MB)
     */
    public function handle(Request $request, Closure $next, string $fieldName = 'image', string|int $maxSizeKb = 2048): Response
    {
        if (!$request->hasFile($fieldName)) {
            return $next($request);
        }

        $validator = Validator::make(
            [$fieldName => $request->file($fieldName)],
            [
                $fieldName => [
                    'required',
                    'image',
                    'mimes:jpeg,jpg,png,gif,webp',
                    'max:' . (int) $maxSizeKb,
                ],
            ],
            [
                "{$fieldName}.required" => 'Please select an image to upload.',
                "{$fieldName}.image" => 'The file must be an image.',
                "{$fieldName}.mimes" => 'The image must be a file of type: jpeg, jpg, png, gif, webp.',
                "{$fieldName}.max" => "The image must not be greater than " . ((int) $maxSizeKb) . " KB.",
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Image validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        return $next($request);
    }
}
