<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    /**
     * Display a listing of the announcements.
     */
    public function index()
    {
        $announcements = Announcement::orderByDesc('starts_at')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('admin.announcements.index', compact('announcements'));
    }

    /**
     * Show the form for creating a new announcement.
     */
    public function create()
    {
        return view('admin.announcements.create');
    }

    /**
     * Store a newly created announcement.
     */
    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        Announcement::create($data);

        return redirect()->route('admin.announcements.index')
            ->with('success', 'Announcement created successfully!');
    }

    /**
     * Display the specified announcement.
     */
    public function show(Announcement $announcement)
    {
        return view('admin.announcements.show', compact('announcement'));
    }

    /**
     * Show the form for editing the specified announcement.
     */
    public function edit(Announcement $announcement)
    {
        return view('admin.announcements.edit', compact('announcement'));
    }

    /**
     * Update the specified announcement.
     */
    public function update(Request $request, Announcement $announcement)
    {
        $data = $this->validatedData($request, $announcement->id);
        $data['updated_by'] = auth()->id();

        $announcement->update($data);

        return redirect()->route('admin.announcements.index')
            ->with('success', 'Announcement updated successfully!');
    }

    /**
     * Remove the specified announcement.
     */
    public function destroy(Announcement $announcement)
    {
        $announcement->delete();

        return redirect()->route('admin.announcements.index')
            ->with('success', 'Announcement deleted successfully!');
    }

    /**
     * Validate incoming request data.
     */
    protected function validatedData(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'is_active' => 'sometimes|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]) + [
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
