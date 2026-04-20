<button id="livetv-show-full-schedule" class="livetv-schedule-toggle" aria-label="Show full schedule">Show full schedule</button>
<script>
document.addEventListener('DOMContentLoaded', function(){
    var btn = document.getElementById('livetv-show-full-schedule');
    var panel = document.getElementById('livetv-schedule-panel');
    var miniBar = document.getElementById('livetv-schedule-mini');
    if(!btn) return;
    btn.addEventListener('click', function(){
        if(panel){
            panel.style.display = 'block';
            panel.setAttribute('aria-hidden','false');
            // ensure timeline component can auto-scroll; trigger a resize event
            try{ window.dispatchEvent(new Event('resize')); }catch(e){}
        }
        if(miniBar) miniBar.style.display = 'none';
        try{ panel && panel.scrollIntoView({behavior:'smooth'}); }catch(e){}
    });
});
</script>
