<?php

namespace BigBridge\ProductImport\Model\Resource\Storage;

/**
 * Confines local file system paths to a set of root directories.
 *
 * Paths are resolved with realpath() and callers must use the returned path, not the one they passed in,
 * so a symlink cannot lead outside a root between the check and the use.
 */
class LocalPathGuard
{
    /**
     * Resolves a directory to a real path inside one of $roots, optionally creating the missing part.
     *
     * Missing directories are only created after the deepest existing ancestor has been verified to be
     * inside a root, so no directory is ever created outside the roots.
     *
     * @param string $dir absolute, or relative to $baseDir
     * @param string[] $roots absolute directories; created if missing
     * @return string|null the real path, or null if the directory is not inside a root (or could not be created)
     */
    public static function resolveDirectory(string $dir, array $roots, string $baseDir, bool $create)
    {
        $path = self::normalize($dir, $baseDir);
        if ($path === null) {
            return null;
        }

        $realRoots = self::realRoots($roots, $create);

        $existing = $path;
        $missing = [];
        while (!file_exists($existing)) {
            $missing[] = basename($existing);
            $existing = dirname($existing);
        }

        $realExisting = realpath($existing);
        if ($realExisting === false || !is_dir($realExisting) || !self::isInside($realExisting, $realRoots)) {
            return null;
        }

        if ($missing) {
            if (!$create) {
                return null;
            }
            $target = rtrim($realExisting, '/') . '/' . implode('/', array_reverse($missing));
            if (!is_dir($target) && !@mkdir($target, 0777, true) && !is_dir($target)) {
                return null;
            }
        } else {
            $target = $realExisting;
        }

        $realTarget = realpath($target);
        if ($realTarget === false || !is_dir($realTarget) || !self::isInside($realTarget, $realRoots)) {
            return null;
        }

        return $realTarget;
    }

    /**
     * Resolves an existing file to a real path inside one of $roots.
     *
     * @param string[] $roots absolute directories
     * @return string|null the real path, or null if it is not an existing regular file inside a root
     */
    public static function resolveFile(string $file, array $roots)
    {
        if ($file === '' || strpos($file, "\0") !== false) {
            return null;
        }

        $real = realpath($file);
        if ($real === false || !is_file($real) || !self::isInside($real, self::realRoots($roots, false))) {
            return null;
        }

        return $real;
    }

    /**
     * Makes the path absolute and removes empty and '.' segments. Paths with '..' segments are rejected.
     *
     * @return string|null
     */
    protected static function normalize(string $dir, string $baseDir)
    {
        if ($dir === '' || strpos($dir, "\0") !== false) {
            return null;
        }

        if ($dir[0] !== '/') {
            $dir = $baseDir . '/' . $dir;
        }

        $segments = array_filter(explode('/', $dir), function ($segment) {
            return $segment !== '' && $segment !== '.';
        });
        if (in_array('..', $segments, true)) {
            return null;
        }

        return '/' . implode('/', $segments);
    }

    /**
     * @param string[] $roots
     * @return string[]
     */
    protected static function realRoots(array $roots, bool $create): array
    {
        $realRoots = [];
        foreach ($roots as $root) {
            if ($create && !is_dir($root)) {
                @mkdir($root, 0777, true);
            }
            $realRoot = realpath($root);
            if ($realRoot !== false && is_dir($realRoot)) {
                $realRoots[] = $realRoot;
            }
        }
        return $realRoots;
    }

    /**
     * @param string[] $realRoots
     */
    protected static function isInside(string $realPath, array $realRoots): bool
    {
        foreach ($realRoots as $realRoot) {
            $prefix = rtrim($realRoot, '/') . '/';
            if ($realPath === $realRoot || strncmp($realPath, $prefix, strlen($prefix)) === 0) {
                return true;
            }
        }
        return false;
    }
}
