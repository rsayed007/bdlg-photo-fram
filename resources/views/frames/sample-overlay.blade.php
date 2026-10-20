{{-- Transparent overlay drawn above member photos: polaroid borders + pin stickers. --}}
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { width: {{ $w }}px; height: {{ $h }}px; overflow: hidden; background: transparent; }
    .frame { position: absolute; border: 14px solid #ffffff; box-shadow: 0 10px 28px rgba(11, 61, 46, .25); }
    .pin { position: absolute; width: 46px; height: 46px; border-radius: 50%;
        background: radial-gradient(circle at 35% 35%, #ff7b88, #f42a41 55%, #a8152a); box-shadow: 0 6px 10px rgba(0,0,0,.25); }
</style>
</head>
<body>
    @foreach ($slots as $s)
        @php($pad = 14)
        <div class="frame" style="left: {{ $s['x'] - $pad }}px; top: {{ $s['y'] - $pad }}px; width: {{ $s['w'] + 2 * $pad }}px; height: {{ $s['h'] + 2 * $pad }}px; border-radius: {{ ($s['radius'] ?? 0) + $pad }}px; transform: rotate({{ $s['rotate'] ?? 0 }}deg);"></div>
        <div class="pin" style="left: {{ $s['x'] + $s['w'] / 2 - 23 }}px; top: {{ $s['y'] - 34 }}px;"></div>
    @endforeach
</body>
</html>
