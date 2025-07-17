<div>
    <flux:modal name="edit-note" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Update Note</flux:heading>
                <flux:text class="mt-2">Update your existing note</flux:text>
            </div>

            <flux:input label="Title"
            wire:model="title"
            placeholder="Enter title for your note" />

            <flux:textarea label="Content"
            wire:model="content"
            placeholder="Enter content for your note" />

            <div class="flex">
                <flux:spacer />

                <flux:button type="submit" variant="primary" wire:click="update">Update</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
