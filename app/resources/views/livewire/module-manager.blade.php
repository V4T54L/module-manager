<div class="">

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

    <form wire:submit.prevent="uploadFile">
        <div class="p-6 rounded-lg shadow-lg space-y-6" x-data="{ isUploading: false, progress: 0 }"
            x-on:livewire-upload-start="isUploading = true" x-on:livewire-upload-finish="isUploading = false"
            x-on:livewire-upload-error="isUploading = false"
            x-on:livewire-upload-progress="progress = $event.detail.progress">
            <div class="text-center">
                <flux:heading size="lg" class="text-2xl font-semibold">Upload File</flux:heading>
            </div>

            <div class="flex justify-center">
                <flux:input label="Choose File" type="file" wire:model="file" accept=".zip"
                    class="border-gray-300 rounded-md px-4 py-2 text-gray-700" />
            </div>

            <div wire:loading wire:target="file">Loading...</div>

            @error('file')
                <span class="error">{{ $message }}</span>
            @enderror

            <div x-show="isUploading">
                <progress max="100" x-bind:value="progress"></progress>
            </div>

            <div class="flex justify-center">
                <flux:spacer />
                <flux:button type="submit" variant="primary"
                    class="px-6 py-3 bg-blue-500 text-white rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400">
                    Upload</flux:button>
            </div>
        </div>
    </form>

    <div class="mt-8">
        <h2 class="text-2xl font-semibold mb-4">Modules</h2>

        @if ($modules->isEmpty())
            <p>No modules found.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto bg-white shadow-md rounded-lg">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="px-4 py-2 text-left">ID</th>
                            <th class="px-4 py-2 text-left">Name</th>
                            <th class="px-4 py-2 text-left">Version</th>
                            <th class="px-4 py-2 text-left">Status</th>
                            <th class="px-4 py-2 text-left">last_checked</th>
                            <th class="px-4 py-2 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        @foreach ($modules as $module)
                            <tr class="border-t hover:bg-gray-100">
                                <td class="px-4 py-2">{{ $module->id }}</td>
                                <td class="px-4 py-2">{{ $module->name }}</td>
                                <td class="px-4 py-2">{{ $module->version }}</td>
                                <td class="px-4 py-2">
                                    <span class="{{ $module->status == 'active' ? 'text-green-500' : 'text-red-500' }}">
                                        {{ ucfirst($module->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">
                                    @if ($module->last_checked)
                                        {{ $module->last_checked->diffForHumans() }}
                                    @else
                                        —
                                    @endif
                                </td>

                                <td class="px-4 py-2 text-center">
                                    <flux:button wire:click="toggleModuleStatus('{{ $module->name }}')"
                                        {{-- class="px-4 py-2 text-sm font-semibold rounded-md {{ $module->status == 'active' ? 'bg-red-500 text-white hover:bg-red-600' : 'bg-green-500 text-white hover:bg-green-600' }}" --}}>
                                        Toggle
                                    </flux:button>
                                    <flux:button wire:click="stageModule('{{ $module->name }}')">Stage</flux:button>
                                    <flux:button wire:click="upgradeModule('{{ $module->name }}')">Upgrade
                                    </flux:button>
                                    <flux:button wire:click="downgradeModule('{{ $module->name }}')">Downgrade
                                    </flux:button>
                                    <flux:button wire:click="unstageModule('{{ $module->name }}')">Unstage
                                    </flux:button>
                                    <flux:button wire:click="deleteModule('{{ $module->name }}')">Delete</flux:button>

                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.7.1/jszip.min.js"></script>
    <script>
        document.querySelector('input[type="file"]').addEventListener('change', function(event) {
            const file = event.target.files[0];

            if (file && file.type === "application/zip") {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const zip = new JSZip();
                    zip.loadAsync(e.target.result).then(function(contents) {
                        let fileList = Object.keys(contents);
                        console.log("ZIP Contents: ", fileList);
                    });
                };
                reader.readAsArrayBuffer(file);
            }
        });
    </script>
    <script>
        let file = document.querySelector('input[type="file"]').files[0]

        @this.upload('file', file, (uploadedFilename) => {}, () => {}, (event) => {
            // Progress callback.
            // event.detail.progress contains a number between 1 and 100 as the upload progresses.
        })

        @this.uploadMultiple('photos', [file], successCallback, errorCallback, progressCallback)

        @this.removeUpload('photos', uploadedFilename, successCallback)
    </script>
</div>
