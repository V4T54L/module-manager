# module-manager

A dynamic module management system can have a profound social impact by enabling the rapid deployment and customization of new features or "plugins" tailored to specific community needs

# To add a module:

1. Create a new module using `php artisan module:make <ModuleName>`
2. Place a `menu.blade.php` for menu extension in sidebar. For example:

```php
<flux:navlist.item :href="route('users.home')" :current="request()->routeIs('users.home')" wire:navigate>
    {{ __('Users Home') }}</flux:navlist.item>
```

3. Add field `"version": "0.1",` in the module's `module.json` file.

Now we are all set to develop the plugin and share it as zip.

> Note: Make sure to zip the contents of the module and not the directory itself, refer the `example-plugins/Users.zip`
