<?php

namespace App\Http\Controllers\Api\Perumahan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Perumahan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;

class UpdatePhotosController extends Controller
{
    public function update(Request $request, $id)
    {
        $request->validate([
            'photos.*' => 'required|file|image|max:5120', // max 5MB each
        ]);

        $perumahan = Perumahan::findOrFail($id);

        $uploadedPhotos = [];

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $file) {
                $timestamp = \Carbon\Carbon::now('Asia/Jakarta')->format('YmdHis');
                $unique = uniqid();
                $username = auth()->user()->username; 
                $extension = $file->getClientOriginalExtension();
                $filename = "{$username}_{$timestamp}_{$unique}.{$extension}";
        
                // Store the file
                $file->storeAs('uploads/perumahan/photos', $filename, 'public');
        
                // Manually create relative path (no /storage prefix)
                $uploadedPhotos[] = "/uploads/perumahan/photos/{$filename}";
            }
        
            $existing = is_array($perumahan->photos) ? $perumahan->photos : [];
            $perumahan->photos = array_merge($existing, $uploadedPhotos);
            $perumahan->save();
        }
        

        return response()->json([
            'success' => true,
            'message' => 'Photos updated successfully.',
            'photos' => $perumahan->photos,
        ]);
    }
}
