<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FeedbackResource\Pages;
use App\Models\Feedback;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

/**
 * 🌟 FB-1: انتقادات، پیشنهادات و گزارش‌های ایراد (سایت و اپ). ادمین متن و تصویرها را می‌بیند،
 * پاسخ می‌دهد و وضعیت را عوض می‌کند؛ کاربر پاسخ را در سایت و اپ می‌بیند. شمارِ پیام‌های تازه
 * روی منو است.
 */
class FeedbackResource extends Resource
{
    protected static ?string $model = Feedback::class;
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $modelLabel = 'پیامِ کاربر';
    protected static ?string $pluralModelLabel = 'انتقادات و پیشنهادات';
    protected static ?string $slug = 'feedback';
    protected static ?int $navigationSort = 7;

    public static function getNavigationBadge(): ?string
    {
        $n = Feedback::where('status', 'new')->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        // رکورد گاهی هنگامِ ساختنِ فرم هنوز null است؛ هر محتوا فقط با رکورد ساخته می‌شود.
        $show = fn (callable $make) => fn (?Feedback $record) => $record ? $make($record) : null;

        return $form->schema([
            Section::make()->columns(2)->schema([
                Placeholder::make('who')->label('کاربر')
                    ->content($show(fn (Feedback $r) => new HtmlString(e($r->user?->name ?: '—')
                        .($r->user ? ' <span dir="ltr" style="color:#64748b">'.e($r->user->mobile ?? $r->user->email ?? '').'</span>' : '')))),
                Placeholder::make('meta')->label('نوع / منبع')
                    ->content($show(fn (Feedback $r) => $r->kind_label.' · '.($r->source === 'app' ? 'اپ' : 'سایت')
                        .($r->app_version ? ' · نسخه‌ی '.$r->app_version : ''))),
                Placeholder::make('when')->label('زمان')
                    ->content($show(fn (Feedback $r) => $r->created_at?->format('Y-m-d H:i'))),
                Placeholder::make('platform_view')->label('دستگاه / مرورگر')
                    ->content($show(fn (Feedback $r) => $r->platform ?: '—')),
                Placeholder::make('book_view')->label('کتاب')
                    ->content($show(fn (Feedback $r) => $r->book_title ?: '—')),
                Placeholder::make('subject_view')->label('موضوع')
                    ->content($show(fn (Feedback $r) => $r->subject)),
                Placeholder::make('message_view')->label('شرح')->columnSpanFull()
                    ->content($show(fn (Feedback $r) => new HtmlString('<div style="white-space:pre-line;line-height:2">'.e($r->message).'</div>'))),
                Placeholder::make('images_view')->label('تصویرها')->columnSpanFull()
                    ->visible(fn (?Feedback $record) => (bool) $record?->attachments()->exists())
                    ->content($show(fn (Feedback $r) => new HtmlString('<div style="display:flex;flex-wrap:wrap;gap:8px">'
                        .$r->attachments->map(fn ($a) => sprintf(
                            '<a href="%1$s" target="_blank"><img src="%1$s" alt="" style="width:140px;height:140px;object-fit:cover;border-radius:10px;border:1px solid #e2e8f0"></a>',
                            e(route('feedback.attachment', $a))))->implode('')
                        .'</div>'))),
            ]),
            Section::make('پاسخ')->schema([
                Select::make('status')->label('وضعیت')->options(Feedback::STATUSES)->required()->native(false),
                Textarea::make('admin_reply')->label('پاسخ به کاربر')->rows(5)->maxLength(5000)
                    ->helperText('کاربر پاسخ را در سایت («انتقادات و پیشنهادات») و در اپ می‌بیند.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user')->withCount('attachments'))
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('kind')->label('نوع')->badge()
                    ->formatStateUsing(fn ($state) => Feedback::KINDS[$state] ?? $state)
                    ->color(fn ($state) => match ($state) { 'bug' => 'danger', 'suggestion' => 'success', 'criticism' => 'warning', default => 'gray' }),
                Tables\Columns\TextColumn::make('subject')->label('موضوع')->limit(50)->searchable()
                    ->description(fn (Feedback $r) => $r->book_title),
                Tables\Columns\TextColumn::make('user.name')->label('کاربر')->searchable(),
                Tables\Columns\TextColumn::make('attachments_count')->label('تصویر')->alignCenter(),
                Tables\Columns\TextColumn::make('source')->label('منبع')
                    ->formatStateUsing(fn ($state) => $state === 'app' ? 'اپ' : 'سایت'),
                Tables\Columns\TextColumn::make('status')->label('وضعیت')->badge()
                    ->formatStateUsing(fn ($state) => Feedback::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) { 'new' => 'info', 'in_progress' => 'warning', 'resolved' => 'success', default => 'gray' }),
                Tables\Columns\TextColumn::make('created_at')->label('زمان')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('وضعیت')->options(Feedback::STATUSES),
                Tables\Filters\SelectFilter::make('kind')->label('نوع')->options(Feedback::KINDS),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('بررسی و پاسخ'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeedback::route('/'),
            'edit' => Pages\EditFeedback::route('/{record}/edit'),
        ];
    }
}
