<?php

namespace App\Livewire;

use App\Models\Note;
use Flux\Flux;
use Livewire\Component;
use Livewire\WithPagination;

class Notes extends Component
{
    use WithPagination;
    public $noteId;
    public function render()
    {
        $notes = Note::orderBy('createdAt', 'desc')->paginate(5);
        return view('livewire.notes', [
            'notes' => $notes
        ]);
    }

    public function edit($id)
    {
        // dd("==>{$id}");
        $this->dispatch('edit-note', id: $id);
    }


    public function delete($id)
    {
        // dd($id);
        $this->noteId = $id;
        Flux::modal('delete-note')->show();
    }

    public function deleteNote()
    {
        Note::find($this->noteId)->delete();
        Flux::modal('delete-note')->close();
        session()->flash('success', 'Note deleted successfully');
        $this->redirectRoute('notes', navigate: true);
    }
}
