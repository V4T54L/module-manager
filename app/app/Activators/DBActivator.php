<?php

namespace App\Activators;

use Nwidart\Modules\Contracts\ActivatorInterface;
use Nwidart\Modules\Module;
use Illuminate\Support\Facades\DB; // Make sure to import the DB facade

class DbActivator implements ActivatorInterface
{
    /**
     * Enable the module.
     *
     * @param Module $module
     */
    public function enable(Module $module): void
    {
        $this->setActive($module, true);
    }

    /**
     * Disable the module.
     *
     * @param Module $module
     */
    public function disable(Module $module): void
    {
        $this->setActive($module, false);
    }

    /**
     * Determine whether the given status same with a module status.
     *
     * @param Module|string $module
     * @param bool $status
     * @return bool
     */
    public function hasStatus(Module|string $module, bool $status): bool
    {
        $moduleName = ($module instanceof Module) ? $module->getName() : $module;

        return DB::table('module_states')
            ->where('name', $moduleName)
            ->where('enabled', $status)
            ->exists();
    }

    /**
     * Set active state for a module.
     *
     * @param Module $module
     * @param bool $active
     */
    public function setActive(Module $module, bool $active): void
    {
        DB::table('module_states')->updateOrInsert(
            ['name' => $module->getName()],
            ['enabled' => $active, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    /**
     * Sets a module status by its name
     *
     * @param string $name
     * @param bool $active
     */
    public function setActiveByName(string $name, bool $active): void
    {
        DB::table('module_states')->updateOrInsert(
            ['name' => $name],
            ['enabled' => $active, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    /**
     * Deletes a module activation status
     *
     * @param Module $module
     */
    public function delete(Module $module): void
    {
        DB::table('module_states')
            ->where('name', $module->getName())
            ->delete();
    }

    /**
     * Deletes any module activation statuses created by this class.
     */
    public function reset(): void
    {
        DB::table('module_states')->truncate();
    }
}
