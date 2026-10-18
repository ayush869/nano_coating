<?php

namespace App\Http\Controllers;

use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    /** Catalog images and logo. */
    public function public(string $dir, string $file)
    {
        $path = $dir.'/'.basename($file);
        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, null, ['Cache-Control' => 'public, max-age=604800']);
    }

    /** Site photos, for signed-in staff and customers. */
    public function photo(string $file)
    {
        $path = 'photos/'.basename($file);
        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    /**
     * Upload a site photo. Large camera images are scaled down to 1600 px
     * so a phone upload on site stays fast and storage stays small.
     */
    public function upload(Request $request)
    {
        $request->validate(['photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:12288']]);

        $file = $request->file('photo');

        // Without the GD extension, keep the upload as it is instead of failing.
        if (! function_exists('imagecreatefromstring')) {
            abort_unless(@getimagesize($file->getRealPath()), 422, 'That file is not a readable image.');
            $path = $file->storeAs('photos', Str::random(32).'.'.$file->guessExtension(), 'public');

            return response()->json(['path' => $path, 'url' => Media::url($path)]);
        }

        $path = 'photos/'.Str::random(32).'.jpg';
        $img = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        abort_unless($img, 422, 'That file is not a readable image.');

        if (imagesx($img) > 1600 || imagesy($img) > 1600) {
            $scale = 1600 / max(imagesx($img), imagesy($img));
            $img = imagescale($img, (int) (imagesx($img) * $scale), (int) (imagesy($img) * $scale));
        }
        ob_start();
        imagejpeg($img, null, 82);
        Storage::disk('public')->put($path, ob_get_clean());

        return response()->json(['path' => $path, 'url' => Media::url($path)]);
    }
}
