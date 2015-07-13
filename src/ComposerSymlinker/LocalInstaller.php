<?php
/**
 * This file is part of <https://github.com/piwi/composer-symlinker>
 */

namespace ComposerSymlinker;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;
use Composer\Util\Filesystem;

/**
 * Local package installer manager
 *
 * @author piwi <me@e-piwi.fr>
 */
class LocalInstaller extends LibraryInstaller
{

    protected $localPackages = array();

    /**
     * {@inheritDoc}
     */
    public function __construct(IOInterface $io, Composer $composer, $type = 'library', Filesystem $filesystem = null)
    {
        parent::__construct($io, $composer, $type, $filesystem);
        $extra = $composer->getPackage()->getExtra();
        if (isset($extra['symlinker']['local-packages'])) {
            $this->setLocalPackages($extra['symlinker']['local-packages']);
        }
    }

    /**
     * Set the array of `vendor/package => local_path` mappings
     *
     * @param   array $paths
     * @return  $this
     * @throws  \InvalidArgumentException if the `local_path` does not exist or does not seem to be a valid composer package
     */
    public function setLocalPackages(array $paths)
    {
        foreach ($paths as $name => $path) {
            if (!$this->isValidLocalPackage($path)) {
                throw new \InvalidArgumentException(
                    sprintf('Local path "%s" defined for package "%s" is not valid', $path, $name)
                );
            }
        }
        $this->localPackages = $paths;
        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * @throws \ComposerSymlinker\FilesystemSymlinkerException if the symbolic link fails
     *
     * @link https://github.com/symfony/Filesystem/blob/master/Filesystem.php#L310
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

        if (true !== @symlink($localPath, $this->getInstallPath($package))) {
            throw new FilesystemSymlinkerException(
                sprintf('Symlink fails: "%s" => "%s"', $localPath, $this->getInstallPath($package))
            );
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
    public function isSymlink($path)
    {
        if (!is_link($path)) {
            return false;
        }
        if (readlink($path) === $path) {
            return false;
        }
        return true;
    }


    /**
     * Tests if a local path seems to be a valid Composer package
     *
     * @param   string    $path
     * @return  bool
     */
    public function isValidLocalPackage($path)
    {
        return (bool) (file_exists($path) && is_dir($path) && file_exists($path . DIRECTORY_SEPARATOR . 'composer.json'));
    }

    /**
     * Get the target path of a local package if it is found
     *
     * @param \Composer\Package\PackageInterface $package
     * @return null|string
     */
    protected function getLocalPathForPackage(PackageInterface $package)
    {
        // declared paths
        if (array_key_exists($package->getPrettyName(), $this->localPackages)) {
            return $this->localPackages[$package->getPrettyName()];
        }

        return null;
    }

    /**
     * Get a package vendor name
     *
     * I'm sure there is a way to get the vendor name in the original Composer package already
     * but can't put a hand on it ...
     *
     * @param \Composer\Package\PackageInterface $package
     * @return mixed
     *
     * @TODO replace this method by an internal one
     */
    protected function getPackageVendorName(PackageInterface $package)
    {
        list($vendor, $name) = explode('/', $package->getName());
        return $vendor;
    }

    /**
     * Be sure to create a `vendor/my_vendor` directory before to create symbolic link
     *
     * @param \Composer\Package\PackageInterface $package
     */
    protected function initializeVendorSubdir(PackageInterface $package)
    {
        $this->initializeVendorDir();
        $this->filesystem->ensureDirectoryExists(
            $this->vendorDir . DIRECTORY_SEPARATOR . $this->getPackageVendorName($package)
        );
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
