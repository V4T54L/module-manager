<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use ZipArchive;

class ModuleManager extends Component
{
    use WithFileUploads;

    public $file;

    protected $rules = [
        'file' => 'required|file|mimes:zip|max:102400' // 100MB max size
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

        $extractPath = public_path('Modules');

        $zip = new ZipArchive();

        if ($zip->open($zipFilePath) === true) {
            $zip->extractTo($extractPath);

            $zip->close();

            unlink($zipFilePath);
        } else {
            session()->flash('error', 'Failed to extract the file!');
        }
    }


    public function render()
    {
        return view('livewire.module-manager');
    }
}
