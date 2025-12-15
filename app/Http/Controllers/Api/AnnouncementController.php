<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    /**
     * Return active announcements for the mobile app.
     */
    public function index(Request $request)
    {
        try {
            $limit = $request->integer('limit');

            $query = Announcement::active()
                ->orderByDesc('starts_at')
                ->orderByDesc('created_at');

            if ($limit) {
                $query->limit($limit);
            }

            $announcements = $query->get([
                'id',
                'title',
                'body',
                'starts_at',
                'ends_at',
                'created_at',
            ]);

            return makeResponse(200, 'Announcements retrieved successfully', $announcements);
        } catch (\Throwable $e) {
            return makeResponse(500, 'Failed to retrieve announcements: ' . $e->getMessage(), []);
        }
    }
}
