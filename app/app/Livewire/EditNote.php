<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Note;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;

class EditNote extends Component
{
    public $title, $content, $noteId;

    #[On('edit-note')]
    public function editNote($id)
    {
        // dd("edit-note received with ID: {$id}");
        $note = Note::findOrFail($id);
        $this->noteId = $id;
        $this->title = $note->title;
        $this->content = $note->content;

        // dd("==>{$this->noteId} :: {$this->title} :: {$this->content}");
        Flux::modal('edit-note')->show();
    }

    public function update()
    {
        // dd('ok');
        $this->validate([
            'title' => ['required', 'string', 'max: 255', Rule::unique('notes', 'title')->ignore($this->noteId)],
            'content' => ['required', 'string'],
        ]);

        $note = Note::find($this->noteId);
        $note->title = $this->title;
        $note->content = $this->content;
        $note->save();
        // display flash message
        session()->flash('success', 'Note updated');
        //redirect to notes route
        $this->redirectRoute('notes', navigate: true);
        Flux::modal('edit-note')->close();
    }


    public function render()
    {
        return view('livewire.edit-note');
    }
}
