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

    /*––– LAB –––*/
    private const INSTALLED_STORAGE_DIR = 'app/installed';
    /*––––––––––––*/

    protected $rules = [
        'file' => 'required|file|mimes:zip|max:10240',
    ];

    /* ------------------------------------------------------------------
     |  STEP 1 — ZIP →  “installed” dir  (no composer faff yet)
     * -----------------------------------------------------------------*/
    public function uploadFile()
    {
        $this->validate();

        $originalName   = $this->file->getClientOriginalName();
        $tempFilePath   = storage_path('app/' . $this->file->store('tmp'));   // app/tmp/XXXX.zip
        $unzipTarget    = storage_path(self::INSTALLED_STORAGE_DIR . '/' . pathinfo($originalName, PATHINFO_FILENAME));

        // clean previous identical name
        File::deleteDirectory($unzipTarget);

        $this->extractZip($tempFilePath, $unzipTarget);

        // basic sanity check: module.json present?
        if (!File::exists($unzipTarget . '/module.json')) {
            File::deleteDirectory($unzipTarget);
            session()->flash('error', "ZIP missing module.json :: rejected.");
            return;
        }

        // OPTIONAL — checksum / manifest check here: skipped for brevity
        $moduleName = basename($unzipTarget);

        try {
            $version = $this->readVersionFromModuleFile($unzipTarget);
        } catch (\RuntimeException $e) {
            File::deleteDirectory($unzipTarget);
            session()->flash('error', $e->getMessage());
            return;
        }

        // Mark as *installed* (available) but NOT yet **enabled** for the app
        Modules::updateOrCreate(
            ['name' => $moduleName],
            [
                'path_under_installed' => $moduleName,
                'version'              => $version,
                'status'               => 'installed',
            ]
        );

        File::delete($tempFilePath);

        session()->flash('success', "Module package **{$moduleName}** staged in /installed/");
    }

    /* ------------------------------------------------------------------
     |  STEP 2 — activate a staged package   (called from UI or API)
     * -----------------------------------------------------------------*/
    public function addModule(string $dirUnderInstalled)
    {
        $source = storage_path(self::INSTALLED_STORAGE_DIR . "/{$dirUnderInstalled}");
        if (!File::exists($source)) {
            session()->flash('error', "Selected package **{$dirUnderInstalled}** not found in /installed.");
            return;
        }

        $destination = base_path("Modules/{$dirUnderInstalled}");

        // Pre-check – avoid overwriting
        if (File::exists($destination)) {
            session()->flash('error', $dirUnderInstalled . ' already activated.');
            return;
        }

        try {
            $version = $this->readVersionFromModuleFile($source);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
            return;
        }


        // 1. Copy tree
        File::copyDirectory($source, $destination);

        // 2. Wire-up
        Artisan::call('module:enable',  ['module' => $dirUnderInstalled]);
        Artisan::call('module:dump',                           []);
        Artisan::call('optimize:clear',                        []);

        // 3. Mark in admin table
        Modules::where('name', $dirUnderInstalled)->update([
            'status' => 'active',
        ]);

        session()->flash('success', "Module **{$dirUnderInstalled}** is now part of the application.");
    }

    /* ------------------------------------------------------------------
     |  Existing db toggle (updated to new column names / laravel-modules)
     * -----------------------------------------------------------------*/
    public function toggleModuleStatus(string $moduleName)
    {
        $module = Modules::firstWhere('name', $moduleName);
        if (!$module) {
            session()->flash('error', "Module not in DB.");
            return;
        }

        if ($module->status === 'active') {
            Artisan::call('module:disable', ['module' => $moduleName]);
            $module->update(['status' => 'inactive']);
            session()->flash('success', $moduleName . ' disabled.');
        } else {
            if (!File::exists(base_path("Modules/$moduleName"))) {
                $this->addModule($module->path_under_installed);
            } else {
                Artisan::call('module:enable', ['module' => $moduleName]);
                $module->update(['status' => 'active']);
                session()->flash('success', $moduleName . ' enabled.');
            }
        }

        Artisan::call('module:dump');
    }

    /* ------------------------------------------------------------------
     | Helpers
     * -----------------------------------------------------------------*/
    private function extractZip(string $zipFile, string $dest): void
    {
        $zip = new ZipArchive;
        $zip->open($zipFile) === true or abort(500, 'Cannot open zip');
        $zip->extractTo($dest);
        $zip->close();
    }

    private function readVersionFromModuleFile(string $unpackedDir)
    {
        $file = $unpackedDir . '/module.json';

        if (!File::exists($file)) {
            throw new \RuntimeException('module.json not found.');
        }

        $json = json_decode(File::get($file), true);

        if (empty($json) || empty($json['version'])) {
            throw new \RuntimeException('Module JSON is invalid or missing required "version" key.');
        }

        return trim($json['version']);
    }

    public function render()
    {
        $this->modules = Modules::orderBy('name')->get();
        return view('livewire.module-manager', ['modules' => $this->modules]);
    }
}
