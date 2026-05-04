@extends('backend.layouts.app')

@section('content')
<div class="container">
    <h1>Author Channels</h1>
    <a href="{{ route('backend.author_channels.create') }}" class="btn btn-primary">Create Channel</a>
    <table class="table mt-3">
        <thead>
            <tr><th>ID</th><th>Name</th><th>Active</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @foreach($channels as $ch)
            <tr>
                <td>{{ $ch->id }}</td>
                <td>{{ $ch->name }}</td>
                <td>{{ $ch->is_active ? 'Yes' : 'No' }}</td>
                <td>
                    <a href="{{ route('backend.author_channels.edit', $ch->id) }}" class="btn btn-sm btn-secondary">Edit</a>
                    <form action="{{ route('backend.author_channels.delete', $ch->id) }}" method="POST" style="display:inline">@csrf
                        <button class="btn btn-sm btn-danger">Delete</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    {{ $channels->links() }}
</div>
@endsection
