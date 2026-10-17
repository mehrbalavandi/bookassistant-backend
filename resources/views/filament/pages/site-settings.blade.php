{{-- 🌟 SITE-1: صفحه‌ی «تنظیماتِ سایت» (App\Filament\Pages\SiteSettingsPage) --}}
<x-filament-panels::page>
    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}

        <x-filament-panels::form.actions :actions="$this->getFormActions()" />
    </x-filament-panels::form>
</x-filament-panels::page>
