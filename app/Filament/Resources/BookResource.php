<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookResource\Pages;
use App\Models\Book;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\Facades\Storage;
use App\Models\BookCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class BookResource extends Resource
{
    protected static ?string $model = Book::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $modelLabel = 'کتاب';
    protected static ?string $pluralModelLabel = 'کتاب‌ها';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('اطلاعات پایه کتاب')->schema([
                Grid::make(2)->schema([
                    TextInput::make('title')
                        ->label('عنوان کتاب')->required(),

                    TextInput::make('folder_name')
                        ->label('نام پوشه در سرور (فقط انگلیسی)')
                        ->helperText('مثال: ielts-book-1')
                        ->required()->live(),

                    // 🌟 قیمت با جداکننده‌ی هزارگان نمایش داده می‌شود (۱,۲۵۰,۰۰۰)؛ ویرگول‌ها
                    // پیش از اعتبارسنجی و ذخیره حذف می‌شوند، پس در DB همان عددِ خام است.
                    TextInput::make('price')
                        ->label('قیمت (تومان)')
                        ->mask(RawJs::make("\$money(\$input, '.', ',', 0)"))
                        ->stripCharacters(',')
                        ->numeric()->minValue(0)->required()->default(0)
                        ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'numeric'])
                        ->live(onBlur: true)
                        ->helperText('اگر رایگان است، 0'),

                    TextInput::make('discount')
                        ->label('درصد تخفیف')->numeric()->default(0)
                        ->minValue(0)->maxValue(100)
                        ->live(onBlur: true)
                        // قیمتِ نهایی (همان که در ویترین و صفحه‌ی خرید دیده می‌شود)
                        ->helperText(function (Get $get): ?string {
                            $price = (int) preg_replace('/\D/', '', (string) $get('price'));
                            $discount = max(0, min(100, (int) $get('discount')));
                            if ($price <= 0 || $discount <= 0) {
                                return null;
                            }
                            $final = (int) round($price * (100 - $discount) / 100);
                            return 'قیمتِ پس از تخفیف: ' . number_format($final) . ' تومان';
                        }),
                ]),
            ]),

            // 🌟 SF-1: آنچه کاربرِ اپ پیش از دانلودِ نمونه یا خرید می‌بیند (صفحه‌ی جزئیاتِ کتاب).
            // همه اختیاری؛ اپ هرچه را بیاید نشان می‌دهد و همه را برای حالتِ آفلاین نگه می‌دارد.
            Section::make('معرفی در ویترینِ اپ')
                ->description('جلد، توضیح و مشخصاتی که در صفحه‌ی جزئیاتِ کتاب در اپ دیده می‌شوند. دکمه‌ی «دریافت نمونه» فقط وقتی فعال است که محتوای نمونه آپلود شده باشد.')
                ->schema([
                    Grid::make(3)->schema([
                        FileUpload::make('cover_image')
                            ->label('جلدِ کتاب')
                            ->image()
                            ->disk('local')
                            ->directory('covers')
                            ->visibility('private')
                            ->maxSize(3072)
                            ->imagePreviewHeight('220')
                            ->helperText('JPG/PNG/WebP، حداکثر ۳ مگابایت. نسبتِ پیشنهادی ۳ به ۴.')
                            ->columnSpan(1),
                        Grid::make(1)->schema([
                            TextInput::make('author')->label('نویسنده')->maxLength(255),
                            TextInput::make('publisher')->label('ناشر')->maxLength(255),
                            TextInput::make('level')->label('سطح')->maxLength(50)
                                ->helperText('مثال: Band 4–5 (A2–B1)'),
                        ])->columnSpan(2),
                    ]),
                    Textarea::make('description')
                        ->label('توضیحِ کتاب')
                        ->rows(6)
                        ->helperText('چند جمله درباره‌ی محتوا، مخاطب و ویژگی‌های کتاب. خطِ تازه در اپ هم خطِ تازه است.'),
                ]),

            // 🌟 دسته و ترتیبِ نمایش: کتاب‌ها در سایت و اپ به ترتیبِ «شماره‌ی ردیف»
            // داخلِ دسته‌شان نشان داده می‌شوند، نه به ترتیبِ آپلود.
            Section::make('دسته و ترتیبِ نمایش')->schema([
                Grid::make(2)->schema([
                    Select::make('category_id')
                        ->label('دسته')
                        ->relationship('category', 'title', fn (Builder $query) => $query->orderBy('sort_order'))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        // دسته‌ی تازه همین‌جا هم ساخته می‌شود.
                        ->createOptionForm([
                            TextInput::make('title')->label('عنوانِ دسته')->required()->maxLength(255),
                            TextInput::make('sort_order')->label('شماره‌ی ردیفِ دسته')
                                ->numeric()->integer()->minValue(1)->required()
                                ->default(fn () => BookCategory::nextSortOrder())
                                ->unique(table: BookCategory::class, column: 'sort_order'),
                            Textarea::make('description')->label('توضیح (اختیاری)')->rows(2),
                        ])
                        // با انتخابِ دسته، اگر ردیف هنوز خالی است، «یکی بعد از آخرین» پیشنهاد می‌شود.
                        ->afterStateUpdated(function (Get $get, Set $set, $state) {
                            if (blank($get('sort_order'))) {
                                $set('sort_order', Book::nextSortOrder($state ? (int) $state : null));
                            }
                        }),

                    TextInput::make('sort_order')
                        ->label('شماره‌ی ردیف در دسته')
                        ->helperText('ترتیبِ این کتاب داخلِ دسته‌اش؛ یکتا در هر دسته. مثال: Mindset 01 ← 1')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->required()
                        ->unique(
                            table: Book::class,
                            column: 'sort_order',
                            ignoreRecord: true,
                            modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('category_id', $get('category_id')),
                        )
                        ->validationMessages([
                            'unique' => 'این شماره‌ی ردیف در این دسته قبلاً به کتابِ دیگری داده شده.',
                        ]),
                ]),
            ]),

            Section::make('نسخه‌ها')
                ->description('این اعداد پرچمِ «محتوا عوض شد» برای اپ هستند و هنگام آپلود/حذفِ گروهی خودکار بالا می‌روند.')
                ->schema([
                    Grid::make(4)->schema([
                        TextInput::make('json_version')->label('نسخهٔ دیتای اصلی')->numeric()->default(1),
                        TextInput::make('audio_version')->label('نسخهٔ صوت اصلی')->numeric()->default(1),
                        TextInput::make('images_version')->label('نسخهٔ تصاویر اصلی')->numeric()->default(1),
                        TextInput::make('sample_version')->label('نسخهٔ نمونه')->numeric()->default(1),
                    ]),
                ]),

            // وضعیتِ محتوا؛ آپلود/حذف از دکمه‌های بالای صفحهٔ ویرایش انجام می‌شود
            Section::make('وضعیت محتوا')
                ->visibleOn('edit')
                ->description('برای آپلود یا حذفِ گروهیِ محتوا، از دکمه‌های «آپلود محتوا» و «حذف گروهی» در بالای همین صفحه استفاده کنید.')
                ->schema([
                    Placeholder::make('main_status')
                        ->label('🔵 محتوای اصلی (کامل)')
                        ->content(fn($record) => static::statusText($record, 'main')),

                    Placeholder::make('sample_status')
                        ->label('🟠 محتوای نمونه (دمو رایگان)')
                        ->content(fn($record) => static::statusText($record, 'sample')),
                ]),
        ]);
    }

    protected static function statusText($record, string $scope): string
    {
        if (! $record) {
            return '—';
        }
        $root = $scope === 'sample'
            ? "books/{$record->folder_name}/sample"
            : "books/{$record->folder_name}";

        $pages  = count(Storage::disk('local')->files("$root/pages"));
        $audio  = count(Storage::disk('local')->files("$root/audio"));
        $images = count(Storage::disk('local')->files("$root/images"));
        // 🐞 اسکریپت‌های صوتی (متنِ هم‌زمان‌شده با هر فایلِ صوتی) حالا در
        // یک پوشه‌ی جداگانه از pages/ نوشته می‌شوند — تا شمارشِ «صفحات»
        // بالا با فایل‌های اسکریپتِ صوتی قاطی نشود. این ردیف فقط برای
        // کاملیِ نمایش اضافه شده؛ نبودش هیچ عملکردی را خراب نمی‌کند.
        $audioScripts = count(Storage::disk('local')->files("$root/audio_scripts"));
        $idx    = Storage::disk('local')->exists("$root/index.json") ? '✓ موجود' : '— ندارد';

        return "index.json: {$idx}  |  صفحات: {$pages}  |  صوت: {$audio}  |  تصویر: {$images}  |  اسکریپتِ صوتی: {$audioScripts}";
    }

    public static function table(Table $table): Table
    {
        return $table
            // 🌟 گروه‌بندی بر اساسِ دسته (به ترتیبِ شماره‌ی ردیفِ دسته؛ «بدون دسته» آخر)
            // و داخلِ هر دسته به ترتیبِ شماره‌ی ردیفِ کتاب — همان ترتیبِ سایت و اپ.
            ->groups([
                Tables\Grouping\Group::make('category.title')
                    ->label('دسته')
                    ->getTitleFromRecordUsing(fn (Book $record) => $record->category?->title ?? 'بدون دسته')
                    ->orderQueryUsing(fn (Builder $query, string $direction) => $query
                        ->orderByRaw('(select sort_order from book_categories where book_categories.id = books.category_id) is null')
                        ->orderByRaw('(select sort_order from book_categories where book_categories.id = books.category_id) ' . ($direction === 'desc' ? 'desc' : 'asc')))
                    ->collapsible(),
            ])
            ->defaultGroup('category.title')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->label('ردیف')->sortable(),
                Tables\Columns\TextColumn::make('title')->label('عنوان کتاب')->searchable(),
                Tables\Columns\TextColumn::make('folder_name')->label('نام پوشه'),
                Tables\Columns\TextColumn::make('json_version')->label('نسخه دیتا'),
                Tables\Columns\TextColumn::make('images_version')->label('نسخه تصاویر'),
                Tables\Columns\TextColumn::make('audio_version')->label('نسخه صوت'),
                Tables\Columns\TextColumn::make('created_at')->label('تاریخ ثبت')->dateTime('Y-m-d'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBooks::route('/'),
            'create' => Pages\CreateBook::route('/create'),
            'edit'   => Pages\EditBook::route('/{record}/edit'),
        ];
    }
}
