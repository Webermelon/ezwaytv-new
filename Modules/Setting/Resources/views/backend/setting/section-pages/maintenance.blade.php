@extends('setting::backend.setting.index')

@section('settings-content')
    <div class="card">
        <div class="card-body">
            <h4 class="mb-3">{{ __('setting_sidebar.lbl_maintenance') }}</h4>
            <p>Select a template to activate maintenance mode for the frontend.</p>

            <div id="maintenance-templates" style="display:flex;gap:12px;flex-wrap:wrap;">
                <div class="template-item" data-url="{{ asset('default-image/404-background.jpg') }}" style="cursor:pointer">
                    <img src="{{ asset('default-image/404-background.jpg') }}" alt="t1" style="width:180px;height:100px;object-fit:cover;border:1px solid #ddd;padding:4px">
                    <div style="text-align:center;margin-top:6px"><button class="btn btn-sm btn-primary enable-template">Use</button></div>
                </div>
                <div class="template-item" data-url="{{ asset('default-image/Default-Image.jpg') }}" style="cursor:pointer">
                    <img src="{{ asset('default-image/Default-Image.jpg') }}" alt="t2" style="width:180px;height:100px;object-fit:cover;border:1px solid #ddd;padding:4px">
                    <div style="text-align:center;margin-top:6px"><button class="btn btn-sm btn-primary enable-template">Use</button></div>
                </div>
                <div class="template-item" data-url="{{ asset('dummy-images/login_banner.jpg') }}" style="cursor:pointer">
                    <img src="{{ asset('dummy-images/login_banner.jpg') }}" alt="t3" style="width:180px;height:100px;object-fit:cover;border:1px solid #ddd;padding:4px">
                    <div style="text-align:center;margin-top:6px"><button class="btn btn-sm btn-primary enable-template">Use</button></div>
                </div>
            </div>

            <div style="margin-top:16px">
                <label>Custom template URL or HTML</label>
                <div style="display:flex;gap:8px;align-items:center">
                    <input id="custom-template" class="form-control" placeholder="https://example.com/img.jpg or HTML" />
                    <button id="enable-custom" class="btn btn-success">Enable</button>
                    <button id="disable-maintenance" class="btn btn-danger">Disable</button>
                </div>
                <div id="maintenance-message" style="margin-top:8px"></div>
            </div>
        </div>
    </div>

@endsection

@push('after-scripts')
<script>
  (function(){
    const enableUrl = "{{ route('backend.settings.enable_maintenance') }}";
    const disableUrl = "{{ route('backend.settings.disable_maintenance') }}";
    const token = '{{ csrf_token() }}';

    function postJson(url, data){
      return fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': token,
          'Accept': 'application/json'
        },
        body: JSON.stringify(data)
      }).then(r=>r.json());
    }

    document.addEventListener('DOMContentLoaded', function(){
      document.querySelectorAll('.enable-template').forEach(btn=>{
        btn.addEventListener('click', function(e){
          const item = e.target.closest('.template-item');
          const url = item.getAttribute('data-url');
          postJson(enableUrl, { template: url }).then(res=>{
            const msg = document.getElementById('maintenance-message');
            msg.innerText = res.message || 'Enabled';
          }).catch(()=>{document.getElementById('maintenance-message').innerText = 'Error';});
        });
      });

      document.getElementById('enable-custom').addEventListener('click', function(){
        const val = document.getElementById('custom-template').value.trim();
        if(!val) return alert('Enter template URL or HTML');
        postJson(enableUrl, { template: val }).then(res=>{
          document.getElementById('maintenance-message').innerText = res.message || 'Enabled';
        }).catch(()=>{document.getElementById('maintenance-message').innerText = 'Error';});
      });

      document.getElementById('disable-maintenance').addEventListener('click', function(){
        postJson(disableUrl, {}).then(res=>{
          document.getElementById('maintenance-message').innerText = res.message || 'Disabled';
        }).catch(()=>{document.getElementById('maintenance-message').innerText = 'Error';});
      });
    });
  })();
</script>
@endpush
