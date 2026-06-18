<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد کاربری | دستیار کتاب</title>
    <link href="https://cdn.jsdelivr.net/npm/vazirmatn@3.3.0/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    
    <style>
        body { 
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); 
            font-family: Vazirmatn, Tahoma, sans-serif; 
            min-height: 100vh;
        }
        .main-card { 
            border-radius: 20px; 
            border: none; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }
        .avatar-circle {
            width: 70px;
            height: 70px;
            background: #4f46e5;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin: 0 auto 15px;
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3);
        }
        .premium-badge {
            background-color: #ffe6e6;
            color: #ff4d4d;
            border: 1px solid #ffcccc;
            border-radius: 12px;
            padding: 15px;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm py-3 mb-5">
        <div class="container">
            <a class="navbar-brand fw-bold text-info" href="#">📚 دستیار هوشمند کتاب</a>
            
            <form action="{{ route('web.logout') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-danger btn-sm px-3 rounded-pill d-flex align-items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-right" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5 .5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0z"/>
                        <path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708z"/>
                    </svg>
                    خروج از حساب
                </button>
            </form>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-7">
                <div class="card main-card p-5 text-center">
                    
                    <div class="avatar-circle">
                        {{ mb_substr(Auth::user()->name, 0, 1) }}
                    </div>

                    <h3 class="fw-bold text-dark mb-1">{{ Auth::user()->name }}</h3>
                    <p class="text-muted small mb-4">{{ Auth::user()->email }}</p>
                    
                    <hr class="my-4 text-secondary opacity-25">

                    <div class="text-start">
                        <h5 class="fw-bold mb-3 text-secondary">وضعیت اشتراک اپلیکیشن فلاتر:</h5>
                        <div class="premium-badge d-flex align-items-center gap-3">
                            <span style="font-size: 24px;">⚠️</span>
                            <div>
                                <strong class="d-block mb-1">حساب شما رایگان (محدود) است</strong>
                                <span class="small opacity-75">برای دسترسی به بانک کامل لغات آیلتس، پادکست‌ها و ترجمه‌های فارسی/عربی، لطفاً اشتراک خود را از داخل اپلیکیشن فعال کنید.</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-2">
                        <span class="badge bg-light text-secondary border px-3 py-2 rounded-pill small">ℹ️ این پنل صرفاً جهت مدیریت حساب وب شماست.</span>
                    </div>

                </div>
            </div>
        </div>
    </div>

</body>
</html>