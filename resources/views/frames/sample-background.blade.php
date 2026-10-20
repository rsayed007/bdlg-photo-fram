{{-- Placeholder artwork for the seeded sample frame (rendered once by SampleFrameSeeder). --}}
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="utf-8">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { width: {{ $w }}px; height: {{ $h }}px; overflow: hidden; }
    body {
        font-family: 'Hind Siliguri', 'Noto Sans Bengali', sans-serif;
        background: linear-gradient(170deg, #e8f6ef 0%, #ffffff 45%, #fdf3e7 100%);
        position: relative;
        color: #0b3d2e;
    }
    .band { position: absolute; left: 0; right: 0; top: 0; height: 760px;
        background: linear-gradient(160deg, #006a4e 0%, #0b8457 55%, #0a5c43 100%);
        border-bottom-left-radius: 120px; border-bottom-right-radius: 120px; overflow: hidden; }
    .sun { position: absolute; width: 520px; height: 520px; border-radius: 50%;
        background: radial-gradient(circle at 40% 40%, #ff5a6a, #f42a41 60%, #c81d33); right: -140px; top: 120px; opacity: .92; }
    .roads { position: absolute; inset: 0; opacity: .14;
        background-image: repeating-linear-gradient(35deg, #fff 0 4px, transparent 4px 90px), repeating-linear-gradient(-55deg, #fff 0 3px, transparent 3px 120px); }
    .brand { position: absolute; left: 80px; top: 90px; display: flex; align-items: center; gap: 22px; color: #fff; }
    .pin { width: 84px; height: 84px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); background: #fff; display: flex; align-items: center; justify-content: center; }
    .pin span { transform: rotate(45deg); font: 700 40px Poppins, sans-serif; color: #f42a41; }
    .brand .t1 { font-weight: 700; font-size: 44px; line-height: 1.1; }
    .brand .t2 { font: 600 26px Poppins, sans-serif; opacity: .85; letter-spacing: .5px; }
    .title { position: absolute; left: 80px; right: 80px; top: 290px; color: #fff; }
    .title .anniv { font: 700 30px Poppins, sans-serif; letter-spacing: 6px; text-transform: uppercase; color: #ffe08a; }
    .title h1 { font-weight: 700; font-size: 104px; line-height: 1.05; margin-top: 14px; }
    .title h2 { font: 700 46px Poppins, sans-serif; margin-top: 18px; }
    .session { position: absolute; left: 80px; right: 80px; top: 820px; display: flex; gap: 28px; align-items: center; }
    .num { font: 700 190px/0.9 Poppins, sans-serif; color: #f42a41; }
    .session .bn { font-weight: 700; font-size: 62px; line-height: 1.15; color: #0b3d2e; }
    .session .en { font: 600 34px Poppins, sans-serif; color: #2d6a55; margin-top: 6px; }
    .chips { position: absolute; left: 80px; top: 1110px; display: flex; gap: 16px; }
    .chip { background: #0b8457; color: #fff; border-radius: 999px; padding: 10px 26px; font: 600 28px Poppins, sans-serif; }
    .slot { position: absolute; background: #dfeee7; }
    .card { position: absolute; left: 50px; right: 50px; top: 1665px; height: 225px; background: #fff; border-radius: 36px;
        box-shadow: 0 12px 40px rgba(11, 61, 46, .12); border: 3px solid #e3efe9; }
    .divider { position: absolute; left: 90px; right: 90px; top: 1800px; height: 2px; background: #e8f0ec; }
</style>
</head>
<body>
    <div class="band"><div class="roads"></div><div class="sun"></div></div>
    <div class="brand">
        <div class="pin"><span>★</span></div>
        <div><div class="t1">বাংলাদেশ লোকাল গাইডস</div><div class="t2">Bangladesh Local Guides</div></div>
    </div>
    <div class="title">
        <div class="anniv">12th Anniversary</div>
        <h1>১২তম বার্ষিকী মেগা মিট-আপ</h1>
        <h2>Mega Meet-up · Dhaka</h2>
    </div>
    <div class="session">
        <div class="num">03</div>
        <div><div class="bn">রিভিউ, ছবি ও অ্যাক্সেসিবিলিটি</div><div class="en">Reviews, Photos &amp; Accessibility</div></div>
    </div>
    <div class="chips"><span class="chip">#LocalGuides</span><span class="chip">#BDLG12</span></div>

    {{-- Photo areas are left empty (user photos are drawn here, then the overlay on top). --}}
    @foreach ($slots as $s)
        <div class="slot" style="left: {{ $s['x'] }}px; top: {{ $s['y'] }}px; width: {{ $s['w'] }}px; height: {{ $s['h'] }}px; border-radius: {{ $s['radius'] ?? 0 }}px; transform: rotate({{ $s['rotate'] ?? 0 }}deg);"></div>
    @endforeach

    <div class="card"></div>
    <div class="divider"></div>
</body>
</html>
