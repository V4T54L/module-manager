<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Modules;
use ZipArchive;

class ModuleManager extends Component
{
    use WithFileUploads;

    public $file;
    public $modules;
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

            $moduleName = $this->getModuleNameFromZip($zipFilePath, $extractPath);

            if ($moduleName) {
                if ($this->isModuleExists($moduleName)) {
                    session()->flash('error', "Module '{$moduleName}' already exists!");
                    return;
                }

                $this->addModuleToStatusFile($moduleName);

                Artisan::call('module:migrate', ['module' => $moduleName, '--force' => true]);
                Artisan::call('module:enable', ['module' => $moduleName]);

                Artisan::call('optimize:clear');

                unlink($zipFilePath);

                Modules::create([
                    'name' => $moduleName,
                    'version' => '1.0',
                    'status' => 'inactive',  // Default status can be 'installed'
                ]);


                session()->flash('success', "{$moduleName} module uploaded and enabled successfully!");

                // $this->redirectRoute('manage', navigate: true);
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

        $module = Modules::where('name', $moduleName)->first();

        if (!$module) {
            session()->flash('error', "Module '{$moduleName}' not found!");
            return;
        }

        $modulesStatus = json_decode(File::get($this->statusFilePath), true);

        if (!isset($modulesStatus[$moduleName])) {
            session()->flash('error', "Module '{$moduleName}' not found in status file!");
            return;
        }

        $modulesStatus[$moduleName] = !$modulesStatus[$moduleName];  // Toggle between true/false

        File::put($this->statusFilePath, json_encode($modulesStatus, JSON_PRETTY_PRINT));

        $module->status = $modulesStatus[$moduleName] ? 'active' : 'inactive';
        $module->save();

        session()->flash('success', "{$moduleName} module status updated to " . ($modulesStatus[$moduleName] ? 'enabled' : 'disabled') . '!');

        // $this->render();
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
        $this->modules = Modules::all();

        return view('livewire.module-manager', [
            'modules' => $this->modules
        ]);
    }
}
