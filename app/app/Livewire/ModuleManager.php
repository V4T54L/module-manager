<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\ModuleState;
use ZipArchive;
use Illuminate\Support\Facades\Storage;

class ModuleManager extends Component
{
    use WithFileUploads;

    public $file;
    public $modules;

    /*––– LAB –––*/
    private const INSTALLED_DIR = 'app/installed';
    /*––––––––––––*/

    protected $rules = [
        'file' => 'required|file|mimes:zip|max:10240', // 10MB max size
    ];

    /*==========================================================
    | 1. ZIP → warehouse (stored, NOT yet used)
    *==========================================================*/
    public function uploadFile()
    {
        $this->validate();

        $original = $this->file->getClientOriginalName();
        $tmpZip = $this->file->store('uploads', 'public');
        $target = storage_path(self::INSTALLED_DIR . '/' . pathinfo($original, PATHINFO_FILENAME));

        // Clean previous folder
        File::deleteDirectory($target);
        try {
            $this->extractZip(storage_path('app/public/' . $tmpZip), $target); // Updated here
        } catch (\RuntimeException $e) {
            dd("Error extracting zip file: {$e->getMessage()}");
        }

        if (!File::exists($target . '/module.json')) {
            File::deleteDirectory($target);
            session()->flash('error', "ZIP missing module.json ― rejected.");
            return;
        }

        try {
            $version = $this->readVersionFromModuleFile($target);
        } catch (\RuntimeException $e) {
            File::deleteDirectory($target);
            session()->flash('error', $e->getMessage());
            return;
        }

        // Create or update the module in the database with 'enabled' as false by default
        ModuleState::updateOrCreate(
            ['name' => basename($target)],
            [
                'enabled' => false, // Defaulting to 'false' (disabled)
            ]
        );

        File::delete($tmpZip);
        session()->flash('success', basename($target) . ' saved to warehouse.');
    }

    /*==========================================================
    | 2. Toggle Module Status (enable / disable)
    *==========================================================*/
    public function toggleModuleStatus(string $moduleName)
    {
        $module = ModuleState::firstWhere('name', $moduleName);
        if (!$module) {
            session()->flash('error', "Module not found in DB.");
            return;
        }

        if ($module->enabled) {
            Artisan::call('module:disable', ['module' => $moduleName, '--no-interaction' => true]);
            $module->update(['enabled' => false]); // Disable module
            session()->flash('success', $moduleName . ' disabled.');
        } else {
            if (!File::exists(base_path("Modules/$moduleName"))) {
                // You might need to stage the module here
                // $this->stageModule($module->path_under_installed);
            } else {
                Artisan::call('module:enable', ['module' => $moduleName, '--no-interaction' => true]);
                $module->update(['enabled' => true]); // Enable module
                session()->flash('success', $moduleName . ' enabled.');
            }
        }

        Artisan::call('module:dump', ['--no-interaction' => true]);
        $this->redirectRoute('manage', navigate: true);
    }

    /*==========================================================
    | 3. Stage / Re-Staged after upgrade/downgrade
    *==========================================================*/
    public function stageModule(string $moduleName)
    {
        $warehouse = storage_path(self::INSTALLED_DIR . '/' . $moduleName);
        $production = base_path("Modules/$moduleName");

        if (!File::exists($warehouse)) {
            session()->flash('error', "$moduleName not in warehouse.");
            return;
        }
        if (File::exists($production)) {
            session()->flash('error', "$moduleName already staged.");
            return;
        }

        File::copyDirectory($warehouse, $production);

        $this->runPostStaging($moduleName);

        session()->flash('success', "$moduleName staged to /Modules.");
    }

    /*==========================================================
    | 4. Upgrade (higher version from warehouse -> /Modules)
    *==========================================================*/
    public function upgradeModule(string $moduleName)
    {
        $this->upgradeOrDowngrade($moduleName, 'upgrade');
    }

    /*==========================================================
    | 5. Downgrade (lower version from warehouse -> /Modules)
    *==========================================================*/
    public function downgradeModule(string $moduleName)
    {
        $this->upgradeOrDowngrade($moduleName, 'downgrade');
    }

    /*==========================================================
    | 6. Unstage — rollback migrations + delete /Modules dir only
    *==========================================================*/
    public function unstageModule(string $moduleName)
    {
        $production = base_path("Modules/$moduleName");
        if (!File::exists($production)) {
            session()->flash('error', "$moduleName not staged.");
            return;
        }

        Artisan::call('module:disable', ['module' => $moduleName, '--no-interaction' => true]);

        /* run rollback inside production folder before deleting */
        try {
            Artisan::call('module:migrate:rollback', ['name' => $moduleName, '--force' => true]);
        } catch (\Throwable $e) {
            /* swallow or warn */
        }

        File::deleteDirectory($production);
        ModuleState::where('name', $moduleName)->update(['enabled' => false]); // Set 'enabled' to false

        Artisan::call('module:dump', ['--no-interaction' => true]);
        session()->flash('success', "$moduleName un-staged.");
    }

    /*==========================================================
    | 7. Delete – warehouse + DB + optionally /Modules
    *==========================================================*/
    public function deleteModule(string $moduleName)
    {
        // If it exists in /Modules, completely wipe
        if (File::exists(base_path("Modules/$moduleName"))) {
            $this->unstageModule($moduleName);
        }

        $warehouse = storage_path(self::INSTALLED_DIR . '/' . $moduleName);
        File::deleteDirectory($warehouse);

        ModuleState::where('name', $moduleName)->delete();

        session()->flash('success', "$moduleName completely deleted.");
    }

    /*───────────────────── helper gas-gas ─────────────────────*/
    private function upgradeOrDowngrade(string $moduleName, string $mode)
    {
        $warehouse = storage_path(self::INSTALLED_DIR . '/' . $moduleName);
        $production = base_path("Modules/$moduleName");

        if (!File::exists($warehouse)) {
            session()->flash('error', "$moduleName not in warehouse.");
            return;
        }
        if (!File::exists($production)) {
            session()->flash('error', "$moduleName not staged.");
            return;
        }

        $new = $this->readVersionFromModuleFile($warehouse);
        $curr = $this->readVersionFromModuleFile($production);

        $cmp = version_compare($new, $curr);
        if ($mode === 'upgrade' && $cmp <= 0) {
            session()->flash('error', "Version $new must be higher than $curr.");
            return;
        }
        if ($mode === 'downgrade' && $cmp >= 0) {
            session()->flash('error', "Version $new must be lower than $curr.");
            return;
        }

        try {
            Artisan::call('module:migrate:rollback', ['name' => $moduleName, '--force' => true]);
            File::deleteDirectory($production);
            File::copyDirectory($warehouse, $production);
            Artisan::call('module:migrate', ['name' => $moduleName, '--force' => true]);
            Artisan::call('module:enable', ['name' => $moduleName, '--no-interaction' => true]);
            Artisan::call('module:dump', ['--no-interaction' => true]);
            Artisan::call('optimize:clear');
        } catch (\Throwable $e) {
            session()->flash('error', "$mode migration issue: " . $e->getMessage());
            return;
        }

        ModuleState::where('name', $moduleName)->update(['enabled' => true, 'version' => $new]);
        session()->flash('success', "$moduleName $mode" . "d to $new.");
    }

    private function runPostStaging(string $moduleName)
    {
        $module = ModuleState::firstWhere('module', $moduleName);
        if (!$module) {
            session()->flash('error', "Module not found in DB.");
            return;
        }
        Artisan::call('module:migrate', ['name' => $moduleName, '--force' => 1]);
        Artisan::call('module:enable', ['name' => $moduleName, '--no-interaction' => 1]);
        Artisan::call('module:dump', ['--no-interaction' => true]);
        Artisan::call('optimize:clear');
        $module->update(['enabled' => true]); // Set 'enabled' to true
    }

    private function extractZip(string $zip, string $dest)
    {
        $z = new ZipArchive;

        // Improved error handling for opening ZIP
        $res = $z->open($zip);
        if ($res !== true) {
            throw new \RuntimeException("Failed to open ZIP file at {$zip}. Error code: {$res}");
        }

        try {
            // Ensure the destination directory exists
            if (!File::exists($dest)) {
                File::makeDirectory($dest, 0755, true);
            }

            // Extract the ZIP file
            $z->extractTo($dest);
        } finally {
            $z->close();
        }
    }

    private function readVersionFromModuleFile(string $dir)
    {
        $file = $dir . '/module.json';
        if (!File::exists($file)) throw new \RuntimeException('module.json missing');
        $data = json_decode(File::get($file), true);
        if (empty($data) || !isset($data['version'])) {
            throw new \RuntimeException('version key missing in module.json');
        }
        return trim($data['version']);
    }

    public function render()
    {
        $this->modules = ModuleState::orderBy('name')->get();
        return view('livewire.module-manager', ['modules' => $this->modules]);
    }
}
