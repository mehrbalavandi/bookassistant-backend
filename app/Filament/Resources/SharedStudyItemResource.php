<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SharedStudyItemResource\Pages;
use App\Models\SharedStudyItem;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * 🌟 ST-7: «مرورِ آماده» — واژه‌ها و نکته‌های بیرون از کتاب‌ها که در «مرورِ من»ِ همه‌ی
 * کاربران خودکار می‌نشیند (اپ با GET /api/study/shared می‌گیرد).
 *
 * - **تکی:** «مورد تازه» و فرم.
 * - **گروهی:** در فهرست، «واردکردن از هوش مصنوعی» (JSONِ قالبِ یکسان). دکمه‌ی «پرامپت»
 *   متنی را می‌دهد که به هوش مصنوعی داده می‌شود.
 * - **برداشتن:** «منتشرشده» را خاموش کنید یا حذف کنید؛ اپ‌ها در همگام‌سازیِ بعد پاکش می‌کنند.
 */
class SharedStudyItemResource extends Resource
{
    protected static ?string $model = SharedStudyItem::class;
    protected static ?string $navigationIcon = 'heroicon-o-light-bulb';
    protected static ?string $modelLabel = 'موردِ مرور';
    protected static ?string $pluralModelLabel = 'مرورِ آماده';
    protected static ?int $navigationSort = 6;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function form(Form $form): Form
    {
        $isWord = fn (Get $get) => $get('type') !== 'tip';

        return $form->schema([
            Section::make()->schema([
                Grid::make(3)->schema([
                    Select::make('type')->label('نوع')
                        ->options(['word' => 'واژه / عبارت', 'tip' => 'نکته'])
                        ->default('word')->required()->live(),
                    TextInput::make('text')
                        ->label(fn (Get $get) => $get('type') === 'tip' ? 'عنوانِ نکته (انگلیسی)' : 'واژه یا عبارت (انگلیسی)')
                        ->required()->maxLength(300)->columnSpan(2)
                        ->extraInputAttributes(['dir' => 'ltr']),
                ]),
                Grid::make(3)->visible($isWord)->schema([
                    TextInput::make('payload.pos')->label('نوعِ کلمه')->placeholder('verb, noun, idiom, …')
                        ->extraInputAttributes(['dir' => 'ltr']),
                    TextInput::make('payload.ipa')->label('IPA')->placeholder('/ˈmɪt.ɪ.ɡeɪt/')
                        ->extraInputAttributes(['dir' => 'ltr']),
                    Select::make('payload.cefr')->label('سطح')
                        ->options(array_combine($l = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'], $l)),
                ]),
            ]),

            Section::make('فارسی')->columns(2)->schema([
                TextInput::make('payload.meaningFa')->label('معنی')->visible($isWord),
                TextInput::make('payload.pronounceFa')->label('تلفظ با حروفِ فارسی')->visible($isWord),
                Textarea::make('payload.explanationFa')->label('توضیح')->rows(4)->columnSpanFull(),
            ]),
            Section::make('عربی')->columns(2)->collapsible()->schema([
                TextInput::make('payload.meaningAr')->label('المعنى')->visible($isWord),
                TextInput::make('payload.pronounceAr')->label('النطق بالحروف العربية')->visible($isWord),
                Textarea::make('payload.explanationAr')->label('الشرح')->rows(4)->columnSpanFull(),
            ]),

            Section::make('مثال‌ها')->schema([
                Repeater::make('payload.examples')->label('')
                    ->schema([
                        TextInput::make('en')->label('جمله‌ی انگلیسی')->required()
                            ->extraInputAttributes(['dir' => 'ltr'])->columnSpanFull(),
                        TextInput::make('fa')->label('ترجمه‌ی فارسی'),
                        TextInput::make('ar')->label('الترجمة العربية'),
                    ])
                    ->columns(2)->maxItems(6)->defaultItems(0)
                    ->addActionLabel('افزودنِ مثال'),
            ]),

            Section::make()->columns(3)->schema([
                TagsInput::make('payload.tags')->label('برچسب‌ها')
                    ->suggestions(['Reading', 'Listening', 'Writing', 'Speaking', 'Grammar', 'Vocabulary', 'Pronunciation']),
                Select::make('payload.importance')->label('اهمیتِ پیش‌فرض')
                    ->options([1 => 'معمولی', 2 => 'مهم', 3 => 'خیلی مهم'])->default(2)->required(),
                TextInput::make('payload.source')->label('منبع (اختیاری)')->placeholder('مثلاً کانالِ …'),
                Toggle::make('published')->label('منتشرشده (به اپ‌ها برود)')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('changed_ms', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('type')->label('نوع')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'tip' ? 'نکته' : 'واژه')
                    ->color(fn (string $state) => $state === 'tip' ? 'warning' : 'info'),
                Tables\Columns\TextColumn::make('text')->label('متن')->searchable()->wrap()
                    ->extraAttributes(['dir' => 'ltr']),
                Tables\Columns\TextColumn::make('payload.meaningFa')->label('معنی')->limit(40)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('payload.tags')->label('برچسب')->badge()->separator(','),
                Tables\Columns\IconColumn::make('published')->label('منتشر')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->label('آخرین تغییر')->since()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->label('نوع')
                    ->options(['word' => 'واژه', 'tip' => 'نکته']),
                Tables\Filters\TernaryFilter::make('published')->label('منتشرشده'),
                Tables\Filters\TrashedFilter::make()->label('حذف‌شده‌ها'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('ویرایش'),
                Tables\Actions\DeleteAction::make()->label('حذف'),
                Tables\Actions\RestoreAction::make()->label('بازگردانی'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSharedStudyItems::route('/'),
            'create' => Pages\CreateSharedStudyItem::route('/create'),
            'edit' => Pages\EditSharedStudyItem::route('/{record}/edit'),
        ];
    }
}
