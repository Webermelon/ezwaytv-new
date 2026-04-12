@extends('backend.layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h3>{{ $module_title ?? 'Channel Schedules' }} - {{ $data->name }}</h3>
            <div class="card mt-3">
                <div class="card-body">
                    <form id="schedule-form" class="mb-3">
                        <input type="hidden" id="channel_id" value="{{ $data->id }}">
                        <input type="hidden" id="schedule_id" value="">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label">Title</label>
                                <input class="form-control" id="title" />
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Start</label>
                                <input type="datetime-local" class="form-control" id="start_at" />
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">End</label>
                                <input type="datetime-local" class="form-control" id="end_at" />
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary" id="save-schedule">Save</button>
                                <button type="button" class="btn btn-secondary ms-2" id="cancel-edit" style="display:none">Cancel</button>
                            </div>
                        </div>
                    </form>

                    <table class="table table-striped" id="schedules-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const channelId = document.getElementById('channel_id').value;
    const tableBody = document.querySelector('#schedules-table tbody');
    const form = document.getElementById('schedule-form');
    const saveBtn = document.getElementById('save-schedule');
    const cancelBtn = document.getElementById('cancel-edit');

    function isoToLocalDatetime(iso){
        if(!iso) return '';
        const d = new Date(iso);
        const pad = n => n.toString().padStart(2,'0');
        const yyyy = d.getFullYear();
        const mm = pad(d.getMonth()+1);
        const dd = pad(d.getDate());
        const hh = pad(d.getHours());
        const mi = pad(d.getMinutes());
        return `${yyyy}-${mm}-${dd}T${hh}:${mi}`;
    }

    async function loadSchedules(){
        tableBody.innerHTML = '<tr><td colspan="5">Loading...</td></tr>';
        const res = await fetch('/api/channel-schedules?channel_id='+channelId);
        const json = await res.json();
        const items = json.data || [];
        if(items.length===0){
            tableBody.innerHTML = '<tr><td colspan="5">No schedules</td></tr>';
            return;
        }
        tableBody.innerHTML = items.map(s => `
            <tr data-id="${s.id}">
                <td>${s.id}</td>
                <td>${s.title ?? ''}</td>
                <td>${s.start_at ?? ''}</td>
                <td>${s.end_at ?? ''}</td>
                <td>
                    <button class="btn btn-sm btn-secondary edit-schedule">Edit</button>
                    <button class="btn btn-sm btn-danger delete-schedule">Delete</button>
                </td>
            </tr>
        `).join('');

        document.querySelectorAll('.edit-schedule').forEach(btn => btn.addEventListener('click', onEdit));
        document.querySelectorAll('.delete-schedule').forEach(btn => btn.addEventListener('click', onDelete));
    }

    function onEdit(e){
        const tr = e.target.closest('tr');
        const id = tr.dataset.id;
        // fetch schedule details from API
        fetch('/api/channel-schedules?channel_id='+channelId).then(r=>r.json()).then(json=>{
            const s = (json.data||[]).find(x=>String(x.id)===String(id));
            if(!s) return;
            document.getElementById('schedule_id').value = s.id;
            document.getElementById('title').value = s.title || '';
            document.getElementById('start_at').value = isoToLocalDatetime(s.start_at);
            document.getElementById('end_at').value = isoToLocalDatetime(s.end_at);
            cancelBtn.style.display = 'inline-block';
        });
    }

    function onDelete(e){
        const tr = e.target.closest('tr');
        const id = tr.dataset.id;
        if(!confirm('Delete schedule #'+id+' ?')) return;
        fetch('/api/channel-schedules/'+id, { method: 'DELETE' }).then(()=> loadSchedules());
    }

    cancelBtn.addEventListener('click', function(){
        document.getElementById('schedule_id').value = '';
        form.reset();
        cancelBtn.style.display = 'none';
    });

    form.addEventListener('submit', async function(ev){
        ev.preventDefault();
        const id = document.getElementById('schedule_id').value;
        const payload = {
            channel_id: channelId,
            title: document.getElementById('title').value || null,
            start_at: document.getElementById('start_at').value || null,
            end_at: document.getElementById('end_at').value || null,
        };
        if(id){
            await fetch('/api/channel-schedules/'+id, { method: 'PUT', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
        } else {
            await fetch('/api/channel-schedules', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
        }
        document.getElementById('schedule_id').value = '';
        form.reset();
        cancelBtn.style.display = 'none';
        loadSchedules();
    });

    loadSchedules();
})();
</script>

@endsection
