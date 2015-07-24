<?php
/**
 * DevLinker Installer plugin.
 *
 * Based on https://github.com/piwi/composer-symlinker
 *
 * @author Robert Goldsmith <r.s.goldsmith@far-blue.co.uk>
 */

namespace DevLinker;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;
use Composer\Util\Filesystem;

/**
 * Override package install and symlink to local working copies instead.
 *
 * @author Robert Goldsmith <r.s.goldsmith@far-blue.co.uk>
 */
class DevLinker extends LibraryInstaller
{
	/**
	 * Stores a key/value array of packages to override.
	 *
	 * Keys are package names. Values are local file paths to working copies.
	 *
	 * @var array
	 */
	protected $_localPackages = null;

//	/**
//	 * {@inheritDoc}
//	 */
//	public function __construct(IOInterface $io, Composer $composer, $type = 'library', Filesystem $filesystem = null)
//	{
//		parent::__construct($io, $composer, $type, $filesystem);
//		$this->_composer
//	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \Exception if the symbolic link fails
	 */
	protected function installCode(PackageInterface $package)
	{
		$localPath = $this->getLocalPathForPackage($package);
		if (is_null($localPath)) {
			return parent::installCode($package);
		}

		$this->io->write("  - Symlinking <info>" . $package->getName() . "</info>");
		$this->debug("Symlinking to local path <comment>{$localPath}</comment>");

		$this->initializeVendorSubdir($package);

		echo "Attempting to symlink $localPath from {$this->getInstallPath($package)}\n";
		if (true !== @symlink($localPath, $this->getInstallPath($package))) {
			throw new \Exception('Symlinking of "' . $localPath . '" failed');
		}
		return true;
	 }


	/**
	 * {@inheritDoc}
	 */
	protected function updateCode(PackageInterface $initial, PackageInterface $target)
	{
		if (
			$this->getLocalPathForPackage($target) === null
			&& !$this->isSymlink($this->getInstallPath($initial))
		) {
			return parent::updateCode($initial, $target);
		}

		$this->io->write("  - Replacing <info>" . $initial->getName() . "</info>");
		$this->removeCode($initial);
		return $this->installCode($target);
	}


	/**
	 * {@inheritDoc}
	 */
	protected function removeCode(PackageInterface $package)
	{
		$path = $this->getInstallPath($package);
		if ($this->isSymlink($path)) {
			$this->debug("Unlinking <comment>{$path}</comment>...");
			$this->filesystem->unlink($path);
			return true;
		}
		return parent::removeCode($package);
	}


	/**
	 * Check if the path is a symlink and not linking to itself.
	 *
	 * @param string $path The path to check.
	 *
	 * @return bool
	 */
	protected function isSymlink($path)
	{
		return is_link($path) && (readlink($path) != $path);
	}


	/**
	 * Tests if a local path seems to be a valid Composer package.
	 *
	 * @param string $path The path to check.
	 *
	 * @return bool
	 */
	protected function isValidLocalPackage($path)
	{
		return (bool)(file_exists($path) && is_dir($path) && file_exists($path . DIRECTORY_SEPARATOR . 'composer.json'));
	}

	/**
	 * Get the target path of a local package if it is found.
	 *
	 * @param \Composer\Package\PackageInterface $package The package.
	 *
	 * @return null|string
	 */
	protected function getLocalPathForPackage(PackageInterface $package)
	{
		if ($this->_localPackages == null) {
			$extra = $this->composer->getPackage()->getExtra();
			if (isset($extra['dev-linker']['local-packages'])) {
				foreach ($extra['dev-linker']['local-packages'] as $path) {
					if (!$this->isValidLocalPackage($path)) {
						throw new \InvalidArgumentException('Invalid local path: ' . $path);
					}
				}
				$this->_localPackages = $extra['dev-linker']['local-packages'];
			}
		}

		if (isset($this->_localPackages[$package->getPrettyName()])) {
			return $this->_localPackages[$package->getPrettyName()];
		}
		return null;
	}

	/**
	 * Be sure to create a `vendor/my_vendor` directory before creating the symbolic link
	 *
	 * @param \Composer\Package\PackageInterface $package
	 */
	protected function initializeVendorSubdir(PackageInterface $package)
	{
		$this->initializeVendorDir();
		$this->filesystem->ensureDirectoryExists($this->getPackageBasePath($package));
	}

	/**
	 * Output verbose info.
	 *
	 * @param string $message The message.
	 *
	 * @return void
	 */
	protected function debug($message)
	{
		if ($this->io->isVerbose()) {
			$this->io->writeError("  <info>[symlinker]</info> {$message}");
		}
	}

}
