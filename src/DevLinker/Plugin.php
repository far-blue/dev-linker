<?php
/**
 * DevLinker plugin loader.
 *
 * Based on https://github.com/piwi/composer-symlinker
 *
 * @author Robert Goldsmith <r.s.goldsmith@far-blue.co.uk>
 */

namespace DevLinker;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

/**
 * DevLinker plugin loader.
 *
 * @author Robert Goldsmith <r.s.goldsmith@far-blue.co.uk>
 */
class Plugin implements PluginInterface
{
	/**
	 * Add the DevLinker installer to the Composer installation manager.
	 *
	 * @param \Composer\Composer       $composer The Composer instance.
	 * @param \Composer\IO\IOInterface $io       The IOInterface instance.
	 *
	 * @return void
	 */
	public function activate(Composer $composer, IOInterface $io)
	{
		$composer->getInstallationManager()->addInstaller(new DevLinker($io, $composer));
	}
}
