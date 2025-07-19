<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Livewire\Component;
use Livewire\WithFileUploads;
use ZipArchive;

class ModuleManager extends Component
{
    use WithFileUploads;

    public $file;
    protected $statusFilePath;

    public function __construct()
    {
        $this->statusFilePath = base_path('modules_statuses.json');
    }

    protected $rules = [
        'file' => 'required|file|mimes:zip|max:10240' // 10MB max size
    ];

    public function uploadFile()
    {
        $this->validate();

        $path = $this->file->store('uploads', 'public');
        $this->extractZip($path);

        session()->flash('success', 'Module uploaded successfully!');
        $this->reset('file');
    }

    private function extractZip($filePath)
    {
        $zipFilePath = storage_path("app/public/{$filePath}");
        $extractPath = base_path('Modules');

        // Ensure the Modules directory exists (if not, create it)
        if (!file_exists($extractPath)) {
            mkdir($extractPath, 0777, true);  // Creates the directory if it doesn't exist
        }

        $zip = new ZipArchive();

        if ($zip->open($zipFilePath) === true) {
            $zip->extractTo($extractPath);
            $zip->close();

            // Get the module name from the extracted files
            $moduleName = $this->getModuleNameFromZip($zipFilePath, $extractPath);

            // Check if the module name was found
            if ($moduleName) {
                // Ensure the module doesn't already exist in the status file
                if ($this->isModuleExists($moduleName)) {
                    session()->flash('error', "Module '{$moduleName}' already exists!");
                    return;
                }

                // Add the new module to the status file with 'false' (disabled)
                $this->addModuleToStatusFile($moduleName);

                // Run migrations and enable the module dynamically
                Artisan::call('module:migrate', ['module' => $moduleName, '--force' => true]);
                Artisan::call('module:enable', ['module' => $moduleName]);

                // Optionally, clear the cache
                Artisan::call('optimize:clear');

                // Remove the zip file after extraction
                unlink($zipFilePath);

                session()->flash('success', "{$moduleName} module uploaded and enabled successfully!");
            } else {
                session()->flash('error', 'Failed to detect module name from the ZIP file.');
            }
        } else {
            session()->flash('error', 'Failed to extract the file!');
        }
    }

    public function toggleModuleStatus($moduleName)
    {
        if (!File::exists($this->statusFilePath)) {
            session()->flash('error', 'Status file not found!');
            return;
        }

        $modulesStatus = json_decode(File::get($this->statusFilePath), true);

        if (isset($modulesStatus[$moduleName])) {
            $modulesStatus[$moduleName] = !$modulesStatus[$moduleName];

            File::put($this->statusFilePath, json_encode($modulesStatus, JSON_PRETTY_PRINT));

            session()->flash('success', "{$moduleName} module status updated to " . ($modulesStatus[$moduleName] ? 'enabled' : 'disabled') . '!');
        } else {
            session()->flash('error', "Module {$moduleName} not found in status file!");
        }
    }

    private function getModuleNameFromZip($zipFilePath, $extractPath)
    {
        $zip = new ZipArchive();
        if ($zip->open($zipFilePath) === true) {
            $files = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $files[] = $zip->getNameIndex($i);
            }
            $zip->close();

            foreach ($files as $file) {
                if (is_dir($extractPath . '/' . $file)) {
                    return basename($file);
                }
            }
        }
        return null;
    }

    // Check if module already exists in the status file
    private function isModuleExists($moduleName)
    {
        if (File::exists($this->statusFilePath)) {
            $modulesStatus = json_decode(File::get($this->statusFilePath), true);
            return isset($modulesStatus[$moduleName]);
        }
        return false; // Return false if the file doesn't exist
    }

    // Add the module to the status file with initial status 'false' (disabled)
    private function addModuleToStatusFile($moduleName)
    {
        $modulesStatus = [];

        // Check if the status file exists and read the content
        if (File::exists($this->statusFilePath)) {
            $modulesStatus = json_decode(File::get($this->statusFilePath), true);
        }

        // Add the new module with status 'false'
        $modulesStatus[$moduleName] = false;

        // Save the updated status back to the file
        File::put($this->statusFilePath, json_encode($modulesStatus, JSON_PRETTY_PRINT));
    }

    public function render()
    {
        return view('livewire.module-manager');
    }
}
