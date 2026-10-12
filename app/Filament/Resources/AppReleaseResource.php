<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppReleaseResource\Pages;
use App\Models\AppRelease;
use App\Support\UploadLimits;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

/**
 * 🌟 نسخه‌های اپ (توزیع از خودِ سایت). هر انتشار: سکو، نسخه، build number، فایلِ نصبی،
 * تغییرات، اجباری‌بودن. اپ با /api/app/latest نسخه‌ی تازه را می‌فهمد و کاربر را به
 * صفحه‌ی /app می‌فرستد.
 */
class AppReleaseResource extends Resource
{
    protected static ?string $model = AppRelease::class;
    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';
    protected static ?string $modelLabel = 'نسخه‌ی اپ';
    protected static ?string $pluralModelLabel = 'نسخه‌های اپ';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('انتشار')->schema([
                Grid::make(3)->schema([
                    Select::make('platform')
                        ->label('سکو')
                        ->options(AppRelease::PLATFORMS)
                        ->default('android')
                        ->required()
                        ->live(),

                    TextInput::make('version_name')
                        ->label('نسخه')
                        ->helperText('همان عددِ قبل از «+» در pubspec.yaml. مثال: 1.2.0')
                        ->required()
                        ->maxLength(50),

                    TextInput::make('build_number')
                        ->label('Build number')
                        ->helperText('همان عددِ بعد از «+» در pubspec.yaml؛ باید از انتشارِ قبلی بزرگ‌تر باشد. مثال: 1.2.0+7 ← 7')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->required()
                        ->default(fn (Get $get) => ((int) AppRelease::where('platform', $get('platform') ?? 'android')->max('build_number')) + 1)
                        ->unique(
                            ignoreRecord: true,
                            modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('platform', $get('platform')),
                        )
                        ->validationMessages([
                            'unique' => 'این build number برای این سکو قبلاً منتشر شده.',
                        ]),
                ]),

                FileUpload::make('file_path')
                    ->label('فایلِ نصبی (اندروید: APK؛ ویندوز: ZIP یا EXE/MSIX)')
                    // نوعِ MIMEی که مرورگرها برای APK می‌فرستند یکسان نیست.
                    ->acceptedFileTypes([
                        'application/vnd.android.package-archive',
                        'application/java-archive',
                        'application/zip',
                        'application/x-zip-compressed',
                        'application/x-msdownload',
                        'application/x-msdos-program',
                        'application/vnd.microsoft.portable-executable',
                        'application/octet-stream',
                    ])
                    ->disk('local')
                    ->directory('app-releases')
                    ->visibility('private')
                    ->maxSize(UploadLimits::effectiveMaxKb())
                    ->helperText(UploadLimits::helperText())
                    ->required(),

                Textarea::make('changelog')
                    ->label('تغییراتِ این نسخه')
                    ->helperText('در اپ (پنجره‌ی به‌روزرسانی) و صفحه‌ی دریافتِ اپ نشان داده می‌شود. هر خط یک مورد.')
                    ->rows(5),

                Grid::make(2)->schema([
                    Toggle::make('is_mandatory')
                        ->label('به‌روزرسانیِ اجباری')
                        ->helperText('کاربرانِ نسخه‌های قدیمی‌تر تا به‌روز نکنند نمی‌توانند پنجره را ببندند.'),
                    Toggle::make('is_published')
                        ->label('منتشر شود')
                        ->helperText('خاموش = هنوز به اپ و سایت اعلام نمی‌شود.')
                        ->default(true),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('build_number', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('platform')->label('سکو')
                    ->formatStateUsing(fn (string $state) => AppRelease::PLATFORMS[$state] ?? $state),
                Tables\Columns\TextColumn::make('version_name')->label('نسخه'),
                Tables\Columns\TextColumn::make('build_number')->label('Build')->sortable(),
                Tables\Columns\TextColumn::make('file_size')->label('حجم')
                    ->formatStateUsing(fn ($state, AppRelease $record) => $record->humanSize()),
                Tables\Columns\IconColumn::make('is_mandatory')->label('اجباری')->boolean(),
                Tables\Columns\IconColumn::make('is_published')->label('منتشرشده')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->label('تاریخ')->dateTime('Y-m-d H:i'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('platform')->label('سکو')->options(AppRelease::PLATFORMS),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppReleases::route('/'),
            'create' => Pages\CreateAppRelease::route('/create'),
            'edit' => Pages\EditAppRelease::route('/{record}/edit'),
        ];
    }
}
