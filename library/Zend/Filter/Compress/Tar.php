<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Filter
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

namespace Zend\Filter\Compress;

use Archive_Tar,
    RecursiveDirectoryIterator,
    RecursiveIteratorIterator,
    Zend\Filter\Exception;

/**
 * Compression adapter for Tar
 *
 * @category   Zend
 * @package    Zend_Filter
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Tar extends AbstractCompressionAlgorithm
{
    /**
     * Compression Options
     * array(
     *     'archive'  => Archive to use
     *     'target'   => Target to write the files to
     * )
     *
     * @var array
     */
    protected $options = array(
        'archive'  => null,
        'target'   => '.',
        'mode'     => null,
    );

    /**
     * Class constructor
     *
     * @param array $options (Optional) Options to set
     */
    public function __construct($options = null)
    {
        if (!class_exists('Archive_Tar')) {
            throw new Exception\ExtensionNotLoadedException(
                'This filter needs PEAR\'s Archive_Tar component. '
                . 'Ensure loading Archive_Tar (registering autoload or require_once)');
        }

        parent::__construct($options);
    }

    /**
     * Returns the set archive
     *
     * @return string
     */
    public function getArchive()
    {
        return $this->options['archive'];
    }

    /**
     * Sets the archive to use for de-/compression
     *
     * @param  string $archive Archive to use
     * @return Tar
     */
    public function setArchive($archive)
    {
        $archive = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, (string) $archive);
        $this->options['archive'] = $archive;

        return $this;
    }

    /**
     * Returns the set target path
     *
     * @return string
     */
    public function getTarget()
    {
        return $this->options['target'];
    }

    /**
     * Sets the target path to use
     *
     * @param  string $target
     * @return Tar
     * @throws Exception\InvalidArgumentException if target path does not exist
     */
    public function setTarget($target)
    {
        if (!file_exists(dirname($target))) {
            throw new Exception\InvalidArgumentException("The directory '$target' does not exist");
        }

        $target = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, (string) $target);
        $this->options['target'] = $target;
        return $this;
    }

    /**
     * Returns the set compression mode
     *
     * @return string
     */
    public function getMode()
    {
        return $this->options['mode'];
    }

    /**
     * Compression mode to use
     *
     * Either Gz or Bz2.
     *
     * @param string $mode
     * @return Tar
     * @throws Exception\InvalidArgumentException for invalid $mode values
     * @throws Exception\ExtensionNotLoadedException if bz2 mode selected but extension not loaded
     * @throws Exception\ExtensionNotLoadedException if gz mode selected but extension not loaded
     */
    public function setMode($mode)
    {
        $mode = ucfirst(strtolower($mode));
        if (($mode != 'Bz2') && ($mode != 'Gz')) {
            throw new Exception\InvalidArgumentException("The mode '$mode' is unknown");
        }

        if (($mode == 'Bz2') && (!extension_loaded('bz2'))) {
            throw new Exception\ExtensionNotLoadedException('This mode needs the bz2 extension');
        }

        if (($mode == 'Gz') && (!extension_loaded('zlib'))) {
            throw new Exception\ExtensionNotLoadedException('This mode needs the zlib extension');
        }

        $this->options['mode'] = $mode;
        return $this;
    }

    /**
     * Compresses the given content
     *
     * @param  string $content
     * @return string
     * @throws Exception\RuntimeException if unable to create temporary file
     * @throws Exception\RuntimeException if unable to create archive
     */
    public function compress($content)
    {
        $archive = new Archive_Tar($this->getArchive(), $this->getMode());
        if (!file_exists($content)) {
            $file = $this->getTarget();
            if (is_dir($file)) {
                $file .= DIRECTORY_SEPARATOR . "tar.tmp";
            }

            $result = file_put_contents($file, $content);
            if ($result === false) {
                throw new Exception\RuntimeException('Error creating the temporary file');
            }

            $content = $file;
        }

        if (is_dir($content)) {
            // collect all file infos
            foreach (new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($content, RecursiveDirectoryIterator::KEY_AS_PATHNAME),
                        RecursiveIteratorIterator::SELF_FIRST
                    ) as $directory => $info
            ) {
                if ($info->isFile()) {
                    $file[] = $directory;
                }
            }

            $content = $file;
        }

        $result  = $archive->create($content);
        if ($result === false) {
            throw new Exception\RuntimeException('Error creating the Tar archive');
        }

        return $this->getArchive();
    }

    /**
     * Decompresses the given content
     *
     * @param  string $content
     * @return boolean
     * @throws Exception\RuntimeException if unable to find archive
     * @throws Exception\RuntimeException if error occurs decompressing archive
     */
    public function decompress($content)
    {
        $archive = $this->getArchive();
        if (empty($archive) || !file_exists($archive)) {
            throw new Exception\RuntimeException('Tar Archive not found');
        }

        $archive = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, realpath($content));
        $archive = new Archive_Tar($archive, $this->getMode());
        $target  = $this->getTarget();
        if (!is_dir($target)) {
            $target = dirname($target);
        }

        $result = $archive->extract($target);
        if ($result === false) {
            throw new Exception\RuntimeException('Error while extracting the Tar archive');
        }

        return true;
    }

    /**
     * Returns the adapter name
     *
     * @return string
     */
    public function toString()
    {
        return 'Tar';
    }
}
