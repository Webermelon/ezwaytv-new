<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Site Under Maintenance</title>
    <style>
        body { font-family: Arial, sans-serif; text-align:center; padding:40px; background:#f4f6f8; color:#333 }
        .card { max-width:800px; margin:40px auto; background:#fff; padding:30px; border-radius:8px; box-shadow:0 6px 18px rgba(0,0,0,0.06)}
        img.maint { max-width:100%; height:auto; margin-bottom:20px }
    </style>
</head>
<body>
    <div class="card">
        @if(!empty($template))
            @if(str_starts_with($template, 'http') || str_starts_with($template, '/'))
                <!-- <img src="{{ $template }}" alt="maintenance" class="maint"> -->
                   <img src="https://ezwayott.sfo3.digitaloceanspaces.com/logos/image/eZway_Tv_2_png2_1_768x236_69a67764acc24.png" width=200; alt="maintenance" class="maint">
            @else
                <div>{!! $template !!}</div>
            @endif
        @endif
        <h1>We'll be back soon</h1>
        <p>Our site is currently under maintenance. We are working to improve the experience and will be back shortly.</p>
    </div>
</body>
</html>
