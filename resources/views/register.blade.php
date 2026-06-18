<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ثبت‌نام در سیستم | دستیار کتاب</title>
    <link href="https://cdn.jsdelivr.net/npm/vazirmatn@3.3.0/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            font-family: Vazirmatn, Tahoma, sans-serif; 
            min-height: 100vh;
        }
        .register-card {
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>

    <div class="container d-flex align-items-center justify-content-center" style="min-height: 100vh;">
        <div class="col-md-5">
            <div class="card register-card p-4 my-4">
                <div class="text-center mb-4">
                    <h3 class="fw-bold text-dark">ایجاد حساب کاربری</h3>
                    <p class="text-muted small">خوش آمدید! لطفاً مشخصات خود را وارد کنید</p>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger py-2 small">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('register.store') }}" method="POST">
                    @csrf
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">نام و نام خانوادگی</label>
                        <input type="text" name="name" class="form-placeholder form-control" value="{{ old('name') }}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">آدرس ایمیل</label>
                        <input type="email" name="email" class="form-control" style="direction: ltr;" value="{{ old('email') }}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">رمز عبور (حداقل ۸ کاراکتر)</label>
                        <input type="password" name="password" class="form-control" style="direction: ltr;" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold">تکرار رمز عبور</label>
                        <input type="password" name="password_confirmation" class="form-control" style="direction: ltr;" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 rounded-pill py-2 fw-bold shadow-sm" style="background: #4f46e5; border: none;">
                        ثبت‌نام و ورود به داشبورد
                    </button>
                </form>

            </div>
        </div>
    </div>

</body>
</html>