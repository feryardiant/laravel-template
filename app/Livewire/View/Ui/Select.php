<?php

declare(strict_types=1);

namespace App\Livewire\View\Ui;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Development page for the select listbox and pillbox demos.
 */
#[Layout('ui.layout')]
class Select extends Component
{
    public ?string $industry = 'web';

    /**
     * @var array<int, string>
     */
    public array $industries = ['web'];

    /**
     * @var array<int, string>
     */
    public array $tags = ['design'];

    /**
     * @var array<int, array{id: string, name: string}>
     */
    public array $tagOptions = [
        ['id' => 'design', 'name' => 'Design'],
        ['id' => 'development', 'name' => 'Development'],
    ];

    public ?string $projectId = null;

    public ?string $modalProjectId = null;

    public string $projectSearch = '';

    public string $tagSearch = '';

    public bool $showCreateModal = false;

    public string $newProjectName = '';

    /**
     * @var array<int, array{id: string, name: string}>
     */
    public array $projects = [
        ['id' => 'branding', 'name' => 'Branding'],
        ['id' => 'analytics', 'name' => 'Analytics'],
        ['id' => 'infrastructure', 'name' => 'Infrastructure'],
    ];

    public function render(): View
    {
        return view('ui.select');
    }

    /**
     * Swap the single selection for the "legal" demo option.
     */
    public function resetIndustry(): void
    {
        $this->industry = 'legal';
    }

    /**
     * Return the filtered project options for the select's backend search.
     */
    public function searchProjects(string $search): string
    {
        $this->projectSearch = trim($search);

        $needle = mb_strtolower($this->projectSearch);

        $projects = array_values(array_filter(
            $this->projects,
            fn (array $project): bool => $needle === ''
                || str_contains(mb_strtolower($project['name']), $needle),
        ));

        return view('ui.partials.select-projects', ['projects' => $projects])->render();
    }

    /**
     * Return the filtered tag options for the pillbox's backend search.
     */
    public function searchTags(string $search): string
    {
        $this->tagSearch = trim($search);
        $needle = mb_strtolower($this->tagSearch);

        $tags = array_values(array_filter(
            $this->tagOptions,
            fn (array $tag): bool => $needle === ''
                || str_contains(mb_strtolower($tag['name']), $needle),
        ));

        return view('ui.partials.select-tags', ['tags' => $tags])->render();
    }

    /**
     * Append a project created from the bound search query.
     */
    public function createProject(?string $name = null): void
    {
        $id = $this->appendProject($name ?? $this->projectSearch);

        if ($id === null) {
            return;
        }

        $this->projectId = $id;
        $this->projectSearch = '';
    }

    /**
     * Open the modal creation flow with the current search pre-filled.
     */
    public function openCreateProject(?string $query = null): void
    {
        $this->newProjectName = trim($query ?? $this->projectSearch);
        $this->showCreateModal = true;
    }

    /**
     * Create the project from the modal form and select it.
     */
    public function createProjectFromModal(): void
    {
        $id = $this->appendProject($this->newProjectName);

        if ($id === null) {
            return;
        }

        $this->modalProjectId = $id;
        $this->projectSearch = '';
        $this->newProjectName = '';
        $this->showCreateModal = false;
    }

    /**
     * Append a tag created from the pillbox's search query.
     */
    public function createTag(?string $name = null): void
    {
        $name = trim($name ?? $this->tagSearch);

        if ($name === '') {
            return;
        }

        $needle = mb_strtolower($name);
        $id = null;

        foreach ($this->tagOptions as $tag) {
            if (mb_strtolower($tag['name']) === $needle) {
                $id = $tag['id'];
                break;
            }
        }

        if ($id === null) {
            $base = Str::slug($name) ?: 'tag-'.\count($this->tagOptions);
            $id = $base;
            $suffix = 2;

            while (\in_array($id, array_column($this->tagOptions, 'id'), true)) {
                $id = $base.'-'.$suffix;
                $suffix++;
            }

            $this->tagOptions[] = ['id' => $id, 'name' => $name];
        }

        if (! \in_array($id, $this->tags, true)) {
            $this->tags[] = $id;
        }

        $this->tagSearch = '';
    }

    /**
     * Append a project if its name is new, returning its id.
     */
    private function appendProject(string $name): ?string
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        $needle = mb_strtolower($name);

        foreach ($this->projects as $project) {
            if (mb_strtolower($project['name']) === $needle) {
                return $project['id'];
            }
        }

        $base = Str::slug($name) ?: 'project-'.\count($this->projects);
        $id = $base;
        $suffix = 2;

        while (\in_array($id, array_column($this->projects, 'id'), true)) {
            $id = $base.'-'.$suffix;
            $suffix++;
        }

        $this->projects[] = ['id' => $id, 'name' => $name];

        return $id;
    }
}
