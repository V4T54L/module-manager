<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Modules;
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
        'file' => 'required|file|mimes:zip|max:10240',
    ];

    /*==========================================================
    | 1.  ZIP  →  warehouse  (stored, NOT yet used)
    *==========================================================*/
    public function uploadFile()
    {
        $this->validate();

        $original = $this->file->getClientOriginalName();
        $tmpZip   = storage_path('app/' . $this->file->store('tmp'));
        $target   = storage_path(self::INSTALLED_DIR . '/' . pathinfo($original, PATHINFO_FILENAME));

        // clean previous folder
        File::deleteDirectory($target);
        $this->extractZip($tmpZip, $target);

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

        Modules::updateOrCreate(
            ['name' => basename($target)],
            [
                'path_under_installed' => basename($target),
                'version'              => $version,
                'status'               => 'installed',
            ]
        );

        File::delete($tmpZip);
        session()->flash('success', basename($target) . ' saved to warehouse.');
    }

    public function toggleModuleStatus(string $moduleName)
    {
        $module = Modules::firstWhere('name', $moduleName);
        if (!$module) {
            session()->flash('error', "Module not in DB.");
            return;
        }

        if ($module->status === 'active') {
            Artisan::call('module:disable', ['module' => $moduleName, '--no-interaction' => true]);
            $module->update(['status' => 'inactive']);
            session()->flash('success', $moduleName . ' disabled.');
        } else {
            if (!File::exists(base_path("Modules/$moduleName"))) {
                // $this->stageModule($module->path_under_installed);
            } else {
                Artisan::call('module:enable', ['module' => $moduleName, '--no-interaction' => true]);
                $module->update(['status' => 'active']);
                session()->flash('success', $moduleName . ' enabled.');
            }
        }

        Artisan::call('module:dump', ['--no-interaction' => true]);
    }


    /*==========================================================
    | 2.  Stage  /  Re-Staged after upgrade/downgrade
    *==========================================================*/
    public function stageModule(string $moduleName)
    {
        $warehouse   = storage_path(self::INSTALLED_DIR . '/' . $moduleName);
        $production  = base_path("Modules/$moduleName");

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
    | 3.  Upgrade  (higher version from warehouse -> /Modules)
    *==========================================================*/
    public function upgradeModule(string $moduleName)
    {
        $this->upgradeOrDowngrade($moduleName, 'upgrade');
    }

    /*==========================================================
    | 4.  Downgrade (lower version from warehouse -> /Modules)
    *==========================================================*/
    public function downgradeModule(string $moduleName)
    {
        $this->upgradeOrDowngrade($moduleName, 'downgrade');
    }

    /*==========================================================
    | 5.  Unstage — rollback migrations + delete /Modules dir only
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
            Artisan::call('module:migrate:rollback', ['module' => $moduleName, '--force' => true]);
        } catch (\Throwable $e) {
            /* swallow or warn */
        }

        File::deleteDirectory($production);
        Modules::where('name', $moduleName)->update(['status' => 'installed']);

        Artisan::call('module:dump');
        session()->flash('success', "$moduleName un-staged.");
    }

    /*==========================================================
    | 6.  Delete – warehouse + DB + optionally /Modules
    *==========================================================*/
    public function deleteModule(string $moduleName)
    {
        // If it exists in /Modules, completely wipe
        if (File::exists(base_path("Modules/$moduleName"))) {
            $this->unstageModule($moduleName);
        }

        $warehouse = storage_path(self::INSTALLED_DIR . '/' . $moduleName);
        File::deleteDirectory($warehouse);

        Modules::where('name', $moduleName)->delete();

        session()->flash('success', "$moduleName completely deleted.");
    }

    /*───────────────────── helper gas-gas ─────────────────────*/
    private function upgradeOrDowngrade(string $moduleName, string $mode)
    {
        $warehouse  = storage_path(self::INSTALLED_DIR . '/' . $moduleName);
        $production = base_path("Modules/$moduleName");

        if (!File::exists($warehouse)) {
            session()->flash('error', "$moduleName not in warehouse.");
            return;
        }
        if (!File::exists($production)) {
            session()->flash('error', "$moduleName not staged.");
            return;
        }

        $new  = $this->readVersionFromModuleFile($warehouse);
        $curr = $this->readVersionFromModuleFile($production);

        $cmp = version_compare($new, $curr);
        if ($mode === 'upgrade'   && $cmp <= 0) {
            session()->flash('error', "Version $new must be higher than $curr.");
            return;
        }
        if ($mode === 'downgrade' && $cmp >= 0) {
            session()->flash('error', "Version $new must be lower than $curr.");
            return;
        }

        try {
            Artisan::call('module:migrate:rollback', ['module' => $moduleName, '--force' => true]);
            File::deleteDirectory($production);
            File::copyDirectory($warehouse, $production);
            Artisan::call('module:migrate', ['module' => $moduleName, '--force' => true]);
            Artisan::call('module:enable',  ['module' => $moduleName, '--no-interaction' => true]);
            Artisan::call('module:dump');
            Artisan::call('optimize:clear');
        } catch (\Throwable $e) {
            session()->flash('error', "$mode migration issue: " . $e->getMessage());
            return;
        }

        Modules::where('name', $moduleName)->update(['version' => $new, 'status' => 'active']);
        session()->flash('success', "$moduleName $mode" . "d to $new.");
    }

    private function runPostStaging(string $moduleName)
    {
        Artisan::call('module:migrate', ['module' => $moduleName, '--force' => 1]);
        Artisan::call('module:enable',  ['module' => $moduleName, '--no-interaction' => 1]);
        Artisan::call('module:dump');
        Artisan::call('optimize:clear');
    }

    private function extractZip(string $zip, string $dest)
    {
        $z = new ZipArchive;
        $z->open($zip) === true or \abort(500, 'Cannot open ZIP');
        $z->extractTo($dest);
        $z->close();
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
        $this->modules = Modules::orderBy('name')->get();
        return view('livewire.module-manager', ['modules' => $this->modules]);
    }
}
