Composer dev-linker
==================

A [Composer](http://getcomposer.org/) plugin to link local working copies of packages into a project.

This plugin allows you to override a package definition in composer.json to symlink to a local working copy
so you can work on both a project and the packages needed by the project in parallel.

Providence
-----

This plugin was heavily inspired by the [piwi/composer-symlinker](https://github.com/piwi/composer-symlinker)
plugin. Compared to the composer-symlinker plugin, this plugin:

* Does not try to scan local directories for packages
* Does not maintain a distinction between symlinks it manages and others you may have created by hand
* Fixes a number of bugs
* Handled a number of edge cases the original plugin does not cope with

Usage
-----

To use it, just add it as a dependency in your `composer.json`:

```json
"require": {
	"far-blue/dev-linker": "dev-master"
}

"extra": {
	"dev-linker": {
		"local-packages": {
			"vendor/package1": "/path/to/working/copy",
			"vendor/package2": "/path/to/working/copy"
    }
}
```

The packages being overridden must be valid (i.e. they would still work if you didn't override them).

Note that Composer will only recalculate dependencies when a require or require-dev entry changes.
If you wish to switch between a normal package and an overridden package or back again you do need to change
the require or require-dev entry for the package before Composer will re-calculate details for it.
