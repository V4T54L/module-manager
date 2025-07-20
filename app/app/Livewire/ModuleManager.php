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

    protected $rules = [
        'file' => 'required|file|mimes:zip|max:10240' // 10MB max size
    ];

    public function uploadFile()
    {
        $this->validate();

        // save the ZIP into vendor/tmp
        $zipPath = $this->file->store('tmp', 'local');
        $fullZip = storage_path("app/$zipPath");

        // temporary extraction
        $tempPath = storage_path("app/tmp_" . now()->timestamp);
        $this->extractTemp($fullZip, $tempPath);

        // module folder inside the archive  (root dir)
        $moduleDir = collect(File::directories($tempPath))->first();   // /tmp_X/Blog
        $moduleName = basename($moduleDir);

        if (!$moduleDir || !$moduleName || !File::exists($moduleDir . '/module.json')) {
            File::deleteDirectory($tempPath);
            File::delete($fullZip);
            session()->flash('error', 'ZIP does not contain a valid module root directory.');
            return;
        }

        // copy to Modules/Blog
        $target = base_path("Modules/$moduleName");

        if (File::exists($target)) {
            session()->flash('error', "Module **$moduleName** already exists.");
            File::deleteDirectory($tempPath);
            File::delete($fullZip);
            return;
        }

        File::copyDirectory($moduleDir, $target);

        // boot-load new code
        Artisan::call('module:enable',  ['module' => $moduleName]);
        Artisan::call('module:dump',                            []);
        Artisan::call('optimize:clear',                         []);

        // optional DB entry
        Modules::updateOrCreate(
            ['name' => $moduleName],
            ['version' => '1.0', 'status' => 'active']
        );

        // cleanup
        File::deleteDirectory($tempPath);
        File::delete($fullZip);

        session()->flash('success', "Module **$moduleName** installed and enabled.");
        $this->reset('file');
    }

    public function toggleModuleStatus(string $moduleName)
    {
        if (!app('modules')->find($moduleName)) {
            session()->flash('error', "Module not found.");
            return;
        }

        $module = Modules::where('name', $moduleName)->firstOrFail();
        $shouldEnable = $module->status === 'inactive';

        if ($shouldEnable) {
            Artisan::call('module:enable', ['module' => $moduleName]);
        } else {
            Artisan::call('module:disable', ['module' => $moduleName]);
        }

        $module->status = $shouldEnable ? 'active' : 'inactive';
        $module->save();

        Artisan::call('module:dump');
        Artisan::call('optimize');

        session()->flash('success', "Module **$moduleName** "
            . ($shouldEnable ? 'enabled.' : 'disabled.'));
    }

    /* ---------------------------------------------------------
     |  OTHER
     * ---------------------------------------------------------*/
    private function extractTemp(string $zip, string $dest): void
    {
        $zipper = new ZipArchive;
        $zipper->open($zip) === true or abort(500, 'Cannot open zip');
        $zipper->extractTo($dest);
        $zipper->close();
    }

    public function render()
    {
        $this->modules = Modules::all();

        return view('livewire.module-manager', [
            'modules' => $this->modules,
        ]);
    }
}
