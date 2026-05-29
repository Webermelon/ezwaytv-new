<div class="mb-3">
    <label for="author_channel_id" class="form-label">On Demand</label>
    <select name="author_channel_id" id="author_channel_id" class="form-select">
        <option value="">-- None --</option>
        @php $channels = \App\Models\AuthorChannel::all(); @endphp
        @foreach($channels as $ch)
            <option value="{{ $ch->id }}" {{ isset($video) && $video->authorChannels->contains($ch->id) ? 'selected' : '' }}>{{ $ch->name }}</option>
        @endforeach
    </select>
</div>
