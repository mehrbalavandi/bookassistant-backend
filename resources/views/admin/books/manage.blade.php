{{-- resources/views/admin/books/manage.blade.php --}}
{{-- مدیریتِ محتوای یک کتاب. دو پنلِ کاملاً جدا: «اصلی» و «نمونه» تا اشتباه نشوند. --}}
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>مدیریت محتوا — {{ $book->title }}</title>
  <style>
    :root { --main:#2563eb; --sample:#d97706; --line:#e5e7eb; --bg:#f8fafc; }
    * { box-sizing: border-box; }
    body { font-family: Tahoma, sans-serif; background: var(--bg); margin: 0; color:#0f172a; }
    .wrap { max-width: 1000px; margin: 24px auto; padding: 0 16px; }
    h1 { font-size: 20px; }
    .status { background:#dcfce7; border:1px solid #86efac; padding:10px 14px; border-radius:8px; margin-bottom:16px; }
    .panels { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    @media (max-width:760px){ .panels{ grid-template-columns:1fr; } }
    .panel { background:#fff; border:1px solid var(--line); border-radius:12px; overflow:hidden; }
    .panel h2 { margin:0; padding:12px 16px; color:#fff; font-size:16px; display:flex; justify-content:space-between; }
    .panel.main  h2 { background:var(--main); }
    .panel.sample h2 { background:var(--sample); }
    .panel .body { padding:16px; }
    .badge { background:rgba(255,255,255,.25); border-radius:999px; padding:2px 10px; font-size:12px; }
    .kind { border:1px solid var(--line); border-radius:10px; padding:12px; margin-bottom:12px; }
    .kind .row { display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; }
    .count { font-weight:bold; }
    .muted { color:#64748b; font-size:12px; }
    input[type=file]{ font-size:13px; }
    .btn { border:0; border-radius:8px; padding:7px 12px; cursor:pointer; font-size:13px; }
    .btn.up { background:#0f172a; color:#fff; }
    .btn.del { background:#fee2e2; color:#b91c1c; }
    form.inline { display:inline; }
    a.back { color:var(--main); text-decoration:none; }
  </style>
</head>
<body>
<div class="wrap">
  <p><a class="back" href="{{ route('admin.books.index') }}">‹ بازگشت به فهرست کتاب‌ها</a></p>
  <h1>مدیریت محتوا — {{ $book->title }}</h1>
  <p class="muted">پوشه: <code>books/{{ $book->folder_name }}</code></p>

  @if(session('status'))
    <div class="status">{{ session('status') }}</div>
  @endif

  <div class="panels">
    @foreach (['main' => ['محتوای اصلی (کامل)', $main, $book->json_version],
               'sample' => ['محتوای نمونه (دمو رایگان)', $sample, $book->sample_version]] as $scope => $info)
      @php [$label, $stats, $version] = $info; @endphp
      <div class="panel {{ $scope }}">
        <h2>
          <span>{{ $label }}</span>
          <span class="badge">v{{ $version ?? 0 }}</span>
        </h2>
        <div class="body">
          <div class="muted" style="margin-bottom:10px">
            index.json: {{ $stats['hasIndex'] ? '✓ موجود' : '— ندارد' }}
          </div>

          @foreach (['pages' => 'صفحات (JSON)', 'audio' => 'صوت', 'images' => 'تصویر'] as $kind => $kindLabel)
            <div class="kind">
              <div class="row">
                <span>{{ $kindLabel }}</span>
                <span class="count">{{ $stats[$kind] }} فایل</span>
              </div>

              {{-- آپلودِ گروهی --}}
              <form class="inline" method="POST" action="{{ route('admin.books.upload', $book) }}"
                    enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="scope" value="{{ $scope }}">
                <input type="hidden" name="kind"  value="{{ $kind }}">
                <input type="file" name="files[]" multiple required>
                @if ($kind === 'pages')
                  <div class="muted">index.json:
                    <input type="file" name="index" accept="application/json">
                  </div>
                @endif
                <button class="btn up" type="submit">آپلود</button>
              </form>

              {{-- حذفِ گروهی --}}
              @if ($stats[$kind] > 0)
                <form class="inline" method="POST"
                      action="{{ route('admin.books.destroyGroup', [$book, $scope, $kind]) }}"
                      onsubmit="return confirm('همهٔ فایل‌های «{{ $kindLabel }}» ({{ $scope }}) حذف شوند؟');">
                  @csrf @method('DELETE')
                  <button class="btn del" type="submit">حذف همه</button>
                </form>
              @endif
            </div>
          @endforeach
        </div>
      </div>
    @endforeach
  </div>
</div>
</body>
</html>