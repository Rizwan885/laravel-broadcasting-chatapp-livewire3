<x-layouts.app.sidebar :title="$title ?? null">
    <flux:main>
         @livewireStyles
        {{ $slot }}
      
    </flux:main>
</x-layouts.app.sidebar>
