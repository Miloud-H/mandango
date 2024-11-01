<?php

/*
 * This file is part of Mandango.
 *
 * (c) Pablo Díez <pablodip@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Mandango\Cache;

use DirectoryIterator;
use RuntimeException;

/**
 * FilesystemCache.
 *
 * @author Pablo Díez <pablodip@gmail.com>
 */
class FilesystemCache implements CacheInterface
{
    private string $dir;

    /**
     * Constructor.
     *
     * @param string $dir The directory.
     */
    public function __construct(string $dir)
    {
        $this->dir = $dir;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return file_exists($this->dir.'/'.$key.'.php');
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key): mixed
    {
        $file = $this->dir.'/'.$key.'.php';

        return file_exists($file) ? require($file) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value): void
    {
        if (!is_dir($this->dir) && false === @mkdir($this->dir, 0777, true)) {
            throw new RuntimeException(sprintf('Unable to create the "%s" directory.', $this->dir));
        }

        $file = $this->dir.'/'.$key.'.php';
        $valueExport = var_export($value, true);
        $content = <<<EOF
<?php

return $valueExport;
EOF;

        if (false === @file_put_contents($file, $content, LOCK_EX)) {
            throw new RuntimeException(sprintf('Unable to write the "%s" file.', $file));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $key): void
    {
        $file = $this->dir.'/'.$key.'.php';
        if (file_exists($file) && false === @unlink($file)) {
            throw new RuntimeException(sprintf('Unable to remove the "%s" file.', $file));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        if (is_dir($this->dir)) {
            foreach (new DirectoryIterator($this->dir) as $file) {
                if ($file->isFile()) {
                    if (false === @unlink($file->getRealPath())) {
                        throw new RuntimeException(sprintf('Unable to remove the "%s" file.', $file->getRealPath()));
                    }
                }
            }
        }
    }
}
