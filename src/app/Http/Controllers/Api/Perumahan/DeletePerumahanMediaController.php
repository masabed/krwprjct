<?php

namespace App\Http\Controllers\Api\Perumahan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Perumahan;

class DeletePerumahanMediaController extends Controller
{
    /**
     * Delete a specific photo or PDF from a Perumahan post by filename.
     */
    public function delete(Request $request, $uuid)
    {
        $user = auth()->user();

        // Authorization check
        if (!in_array($user->role, ['admin', 'admin_bidang'])) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        // Validate form-data or JSON
        $request->validate([
            'filename' => 'required|string',
            'type' => 'required|in:photo,pdf',
        ]);

        $filename = $request->input('filename');
        $type = $request->input('type');

        $perumahan = Perumahan::find($uuid);

        if (!$perumahan) {
            return response()->json(['error' => 'Post not found.'], 404);
        }

        // Choose which media array to work with
        $mediaList = $type === 'photo' ? ($perumahan->photos ?? []) : ($perumahan->pdfs ?? []);

        // Match full path based on filename
        $match = collect($mediaList)->first(fn($path) => basename($path) === $filename);

        if (!$match) {
            return response()->json(['error' => ucfirst($type) . ' not found.'], 404);
        }

        // Delete from storage if exists
        if (Storage::disk('public')->exists($match)) {
            Storage::disk('public')->delete($match);
        }

        // Remove the file from the array
        $updatedList = array_values(array_filter($mediaList, fn($item) => $item !== $match));

        if ($type === 'photo') {
            $perumahan->photos = $updatedList;
        } else {
            $perumahan->pdfs = $updatedList;
        }

        $perumahan->save();

        return response()->json([
            'success' => true,
            'message' => ucfirst($type) . ' deleted successfully.',
            'photos' => $perumahan->photos,
            'pdfs' => $perumahan->pdfs,
        ]);
    }
}
