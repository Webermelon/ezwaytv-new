<?php

namespace App\Http\Controllers;

use App\Models\MusicVideoSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MusicVideoSubmissionController extends Controller
{
    public function create(string $channel = 'ezway-music')
    {
        $channelData = $this->channel($channel);

        abort_unless($channelData, 404);

        return view('frontend::music-video-upload', [
            'channel' => $channelData,
            'user' => Auth::user(),
        ]);
    }

    public function store(Request $request)
    {
        $maxUploadMb = (int) env('MUSIC_VIDEO_MAX_UPLOAD_MB', 2048);

        $data = $request->validate([
            'channel_slug' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'artist_name' => ['nullable', 'string', 'max:255'],
            'submitter_name' => ['nullable', 'string', 'max:255'],
            'submitter_email' => ['nullable', 'email', 'max:255'],
            'submitter_phone' => ['nullable', 'string', 'max:50'],
            'purchase_reference' => ['required', 'string', 'max:255'],
            'purchase_confirmation' => ['accepted'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'poster' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'video' => ['required', 'file', 'mimes:mp4,mov,m4v,webm', 'max:' . ($maxUploadMb * 1024)],
        ]);

        $channel = $this->channel($data['channel_slug']);
        abort_unless($channel, 404);

        $disk = $this->mediaDisk();
        $slug = Str::slug($data['title']) ?: 'music-video';
        $stamp = now()->format('YmdHis') . '-' . Str::random(8);

        $poster = $request->file('poster');
        $video = $request->file('video');

        $posterName = $slug . '-' . $stamp . '.' . $poster->getClientOriginalExtension();
        $videoName = $slug . '-' . $stamp . '.' . $video->getClientOriginalExtension();

        $posterPath = $poster->storeAs('video/image', $posterName, $disk);
        $videoPath = $video->storeAs('video/video', $videoName, $disk);

        $submission = MusicVideoSubmission::create([
            'user_id' => Auth::id(),
            'channel_slug' => $channel['slug'],
            'channel_name' => $channel['name'],
            'title' => $data['title'],
            'artist_name' => $data['artist_name'] ?? null,
            'submitter_name' => $data['submitter_name'] ?? Auth::user()?->name,
            'submitter_email' => $data['submitter_email'] ?? Auth::user()?->email,
            'submitter_phone' => $data['submitter_phone'] ?? null,
            'purchase_reference' => $data['purchase_reference'] ?? null,
            'purchase_confirmed_at' => now(),
            'notes' => $data['notes'] ?? null,
            'poster_disk' => $disk,
            'poster_path' => $posterPath,
            'video_disk' => $disk,
            'video_path' => $videoPath,
            'video_original_name' => $video->getClientOriginalName(),
            'video_mime' => $video->getClientMimeType(),
            'video_size' => $video->getSize(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Your music video was uploaded. Our team will review it and schedule it for the channel.',
                'submission_id' => $submission->id,
                'redirect_url' => route('upload-your-videoes', ['channel' => $channel['slug'], 'submitted' => 1]),
            ]);
        }

        return redirect()
            ->route('upload-your-videoes', ['channel' => $channel['slug']])
            ->with('music_submission_success', 'Your music video was uploaded. Our team will review it and schedule it for the channel.');
    }

    public function adminIndex(Request $request)
    {
        $query = MusicVideoSubmission::query()->latest();

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('artist_name', 'like', "%{$search}%")
                    ->orWhere('submitter_email', 'like', "%{$search}%");
            });
        }

        return view('frontend::backend.music-video-submissions.index', [
            'submissions' => $query->paginate(20)->withQueryString(),
            'statuses' => MusicVideoSubmission::STATUSES,
            'stats' => [
                'total' => MusicVideoSubmission::count(),
                'submitted' => MusicVideoSubmission::where('status', 'submitted')->count(),
                'scheduled' => MusicVideoSubmission::where('status', 'scheduled')->count(),
                'used' => MusicVideoSubmission::where('status', 'used')->count(),
                'trash' => MusicVideoSubmission::onlyTrashed()->count(),
            ],
            'isTrash' => false,
        ]);
    }

    public function trash(Request $request)
    {
        $query = MusicVideoSubmission::onlyTrashed()->latest('deleted_at');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('artist_name', 'like', "%{$search}%")
                    ->orWhere('submitter_email', 'like', "%{$search}%");
            });
        }

        return view('frontend::backend.music-video-submissions.index', [
            'submissions' => $query->paginate(20)->withQueryString(),
            'statuses' => MusicVideoSubmission::STATUSES,
            'stats' => [
                'total' => MusicVideoSubmission::count(),
                'submitted' => MusicVideoSubmission::where('status', 'submitted')->count(),
                'scheduled' => MusicVideoSubmission::where('status', 'scheduled')->count(),
                'used' => MusicVideoSubmission::where('status', 'used')->count(),
                'trash' => MusicVideoSubmission::onlyTrashed()->count(),
            ],
            'isTrash' => true,
        ]);
    }

    public function adminShow($id)
    {
        return view('frontend::backend.music-video-submissions.show', [
            'submission' => MusicVideoSubmission::findOrFail($id),
            'statuses' => MusicVideoSubmission::STATUSES,
        ]);
    }

    public function adminUpdate(Request $request, $id)
    {
        $submission = MusicVideoSubmission::findOrFail($id);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', array_keys(MusicVideoSubmission::STATUSES))],
            'scheduled_at' => ['nullable', 'date'],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $data['reviewed_by'] = Auth::id();
        $data['reviewed_at'] = now();

        $submission->update($data);

        return redirect()
            ->route('backend.users_submission.show', $submission)
            ->with('success', 'Music video submission updated.');
    }

    public function adminSetStatus(Request $request, $id)
    {
        $submission = MusicVideoSubmission::findOrFail($id);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:scheduled,submitted'],
        ]);

        $scheduledAt = $data['status'] === 'scheduled'
            ? ($submission->scheduled_at ?: now())
            : null;

        $submission->update([
            'status' => $data['status'],
            'scheduled_at' => $scheduledAt,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Submission marked as ' . (MusicVideoSubmission::STATUSES[$data['status']] ?? $data['status']) . '.');
    }

    public function download($id)
    {
        $submission = MusicVideoSubmission::findOrFail($id);

        if ($submission->video_disk === 'public' && Storage::disk('public')->exists($submission->video_path)) {
            return Storage::disk('public')->download(
                $submission->video_path,
                $submission->video_original_name ?: basename($submission->video_path)
            );
        }

        return redirect($submission->video_url);
    }

    public function destroy($id)
    {
        $submission = MusicVideoSubmission::findOrFail($id);
        $submission->delete();

        return redirect()
            ->route('backend.users_submission.index')
            ->with('success', 'User submission moved to trash.');
    }

    public function restore($id)
    {
        $submission = MusicVideoSubmission::onlyTrashed()->findOrFail($id);
        $submission->restore();

        return redirect()
            ->route('backend.users_submission.trash')
            ->with('success', 'User submission restored.');
    }

    public function forceDestroy($id)
    {
        $submission = MusicVideoSubmission::onlyTrashed()->findOrFail($id);

        if ($submission->poster_path) {
            Storage::disk($submission->poster_disk)->delete($submission->poster_path);
        }

        if ($submission->video_path) {
            Storage::disk($submission->video_disk)->delete($submission->video_path);
        }

        $submission->forceDelete();

        return redirect()
            ->route('backend.users_submission.trash')
            ->with('success', 'User submission permanently deleted.');
    }

    private function mediaDisk(): string
    {
        return config('filesystems.active') === 'dg-ocean' ? 'dg-ocean' : 'public';
    }

    private function channel(string $slug): ?array
    {
        return config("music_submission_channels.{$slug}");
    }
}
