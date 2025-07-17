<div>
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Notes') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Manage your notes') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <flux:modal.trigger name="create-note">
        <flux:button>Create Note</flux:button>
    </flux:modal.trigger>

    @session('success')
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => { show = false }, 3000)" role="alert"
        class="fixed top-5 right-5 bg-green-600 text-white text-sm p-4 rounded-lg shadow-lg z-50">
        <p>{{ $value }}</p>
    </div>
    @endsession('success')

    @session('error')
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => { show = false }, 3000)" role="alert"
        class="fixed top-5 right-5 bg-red-600 text-white text-sm p-4 rounded-lg shadow-lg z-50">
        <p>{{ $value }}</p>
    </div>
    @endsession('error')

    <livewire:create-note />
    <livewire:edit-note />

    <table class="table-auto w-full bg-slate-800 shadow-md rounded-md mt-5">
        <thead class="bg-slate-900">
            <tr>
                <th class="px-4 py-2 text-left">Title</th>
                <th class="px-4 py-2 text-left">Content</th>
                <th class="px-4 py-2 text-center">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($notes as $note)
                <tr class="border-t">
                    <td class="px-4 py-2">{{ $note->title }}</td>
                    <td class="px-4 py-2">{{ $note->content }}</td>
                    <td class="px-4 py-2 text-center space-x-2">
                        <flux:button wire:click="edit({{ $note->id }})">Edit</flux:button>
                        <flux:button variant="danger" wire:click="delete({{ $note->id }})">Delete</flux:button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="px-4 py-2 text-center text-gray-500">
                        No Notes yet!
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
    {{-- pagination Links --}}
    <div class="mt-4">
        {{ $notes->links() }}
    </div>

    <flux:modal name="delete-note" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete note?</flux:heading>

                <flux:text class="mt-2">
                    <p>You're about to delete this note.</p>
                    <p>This action cannot be reversed.</p>
                </flux:text>
            </div>

            <div class="flex gap-2">
                <flux:spacer />

                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="danger" wire:click="deleteNote()">Delete note</flux:button>
            </div>
        </div>
    </flux:modal>

</div>
