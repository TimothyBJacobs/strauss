<?php
/**
 * Deletes source files and empty directories.
 */

namespace BrianHenryIE\Strauss;

use BrianHenryIE\Strauss\Composer\Extra\StraussConfig;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use RecursiveDirectoryIterator;
use Symfony\Component\Finder\Finder;

class Cleanup
{

    /** @var Filesystem */
    protected Filesystem $filesystem;

    protected bool $isDeleteVendorFiles;

    protected string $vendorDirectory = 'vendor'. DIRECTORY_SEPARATOR;
    
    public function __construct(StraussConfig $config, string $workingDir)
    {
        $this->vendorDirectory = $config->getVendorDirectory();

        $this->isDeleteVendorFiles = $config->isDeleteVendorFiles() && $config->getTargetDirectory() !== $config->getVendorDirectory();
        
        $this->filesystem = new Filesystem(new Local($workingDir));
    }

    /**
     * Maybe delete the source files that were copied (depending on config),
     * then delete empty directories.
     *
     * @param array $sourceFiles
     */
    public function cleanup(array $sourceFiles)
    {
        if ($this->isDeleteVendorFiles) {
            foreach ($sourceFiles as $sourceFile) {
                $relativeFilepath = $this->vendorDirectory . $sourceFile;

                $this->filesystem->delete($relativeFilepath);
            }

            // Get the root folders of the moved files.
            $rootSourceDirectories = [];
            foreach ($sourceFiles as $sourceFile) {
                $arr = explode("/", $sourceFile, 2);
                $dir = $arr[0];
                $rootSourceDirectories[ $dir ] = $dir;
            }
            $rootSourceDirectories = array_keys($rootSourceDirectories);


            $finder = new Finder();

            foreach ($rootSourceDirectories as $rootSourceDirectory) {
                if (!is_dir($rootSourceDirectory) || is_link($rootSourceDirectory)) {
                    continue;
                }

                $finder->directories()->path($rootSourceDirectory);

                foreach ($finder as $directory) {
                    $metadata = $this->filesystem->getMetadata($directory);

                    if ($this->dirIsEmpty($directory)) {
                        $this->filesystem->deleteDir($directory);
                    }
                }
            }
        }
    }

    // TODO: Use Symphony or Flysystem functions.
    protected function dirIsEmpty(string $dir): bool
    {
        $di = new RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
        return iterator_count($di) === 0;
    }
}
