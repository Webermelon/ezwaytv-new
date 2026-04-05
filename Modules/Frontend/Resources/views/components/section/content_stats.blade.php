{{-- EZStats: inline stats under title (views · plays) --}}
<div id="ezway-stats-line" class="text-muted small mb-2" style="display:none;">
    <span id="ezway-plays-block" style="display:none;"><span id="ezway-plays-num"></span> views</span>
    <span id="ezway-stats-sep" style="display:none;"> · </span>
    <span id="ezway-views-block" style="display:none;"><i class="ph ph-eye"></i> <span id="ezway-views-num"></span></span>
</div>
<script>
document.addEventListener('DOMContentLoaded', function(){
    try {
        var meta = window._ezPageMeta || {};
        var ctype = meta.content_type;
        var cid = meta.content_id;
        if (!ctype || !cid) return;

        function human(n) {
            n = Number(n) || 0;
            if (n < 1000) return String(n);
            if (n < 1000000) return (Math.round(n / 100) / 10).toFixed(1).replace(/\.0$/, '') + 'K';
            return (Math.round(n / 100000) / 10).toFixed(1).replace(/\.0$/, '') + 'M';
        }

        fetch('/api/statistics/content-stats?content_type=' + encodeURIComponent(ctype) + '&content_id=' + encodeURIComponent(cid), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function(r){ return r.json(); }).then(function(d){
            if (!d) return;
            var line = document.getElementById('ezway-stats-line');
            var viewsBlock = document.getElementById('ezway-views-block');
            var playsBlock = document.getElementById('ezway-plays-block');
            var sep = document.getElementById('ezway-stats-sep');
            var showViews = false, showPlays = false;

            if (d.show_views_frontend) {
                document.getElementById('ezway-views-num').textContent = human(d.total_views || 0);
                viewsBlock.style.display = '';
                showViews = true;
            } else {
                viewsBlock.style.display = 'none';
            }

            if (d.show_plays_frontend) {
                document.getElementById('ezway-plays-num').textContent = human(d.total_plays || 0);
                playsBlock.style.display = '';
                showPlays = true;
            }

            if (showViews && showPlays) sep.style.display = '';
            if (showViews || showPlays) line.style.display = '';
        }).catch(function(){});
    } catch(e){}
});
</script>
