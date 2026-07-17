<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8">
<title>کتاب‌ها</title>
<style>body{font-family:Tahoma;background:#f8fafc;margin:24px}
table{border-collapse:collapse;width:100%;background:#fff}
td,th{border:1px solid #e5e7eb;padding:8px;text-align:right}
a{color:#2563eb;text-decoration:none}</style></head><body>
<h1>کتاب‌ها</h1>
<table>
  <tr><th>عنوان</th><th>اصلی (ص/ص/ت)</th><th>نمونه (ص/ص/ت)</th><th></th></tr>
  @foreach ($books as $b)
    <tr>
      <td>{{ $b->title }}</td>
      <td>{{ $b->main_stats['pages'] }} / {{ $b->main_stats['audio'] }} / {{ $b->main_stats['images'] }}</td>
      <td>{{ $b->sample_stats['pages'] }} / {{ $b->sample_stats['audio'] }} / {{ $b->sample_stats['images'] }}</td>
      <td><a href="{{ route('admin.books.manage', $b) }}">مدیریت محتوا ›</a></td>
    </tr>
  @endforeach
</table>
</body></html>