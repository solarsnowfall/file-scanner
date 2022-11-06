<?php

namespace Solar;

use Exception;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use SplFileObject;

class Scanner
{
    const MODE_FILENAME = 0;
    const MODE_CONTENTS = 1;

    /**
     * @var string[]
     */
    protected array $extensions = [];

    /**
     * @var string[]
     */
    protected array $ignore = [];

    /**
     * @var array
     */
    protected array $matches = [];

    /**
     * @var int
     */
    protected int $mode;

    /**
     * @var string
     */
    protected string $path;

    /**
     * @var string
     */
    protected string $phrase;

    /**
     * @param string $path
     * @param string $phrase
     * @param int $mode
     * @param array $extensions
     * @param array $ignore
     */
    public function __construct(
        string $path,
        string $phrase,
        int $mode,
        array $extensions = ['php'],
        array $ignore = ['vendor']
    ) {
        if (!file_exists($path)) {
            throw new InvalidArgumentException("Path not found: $path");
        }

        if (!strlen($phrase)) {
            throw new InvalidArgumentException("Cannot search for empty phrase");
        }

        $this->path = $path;
        $this->phrase = $phrase;
        $this->mode = $mode;
        $this->extensions = $extensions;
        $this->ignore = $ignore;

        $this->ignore[] = $_SERVER['SCRIPT_NAME'];
    }

    /**
     * @return int
     */
    public function count(): int
    {
        if ($this->matches == self::MODE_FILENAME) {
            return count($this->matches);
        }

        $count = 0;

        foreach ($this->matches as $match) {
            $count += count($match);
        }

        return $count;
    }

    /**
     * @return void
     */
    public function dump(): void
    {
        $count = $this->count();
        echo "Found \"{$this->phrase}\": $count\n\n";

        $this->mode == self::MODE_FILENAME
            ? $this->dumpFilenames()
            : $this->dumpContents();
    }

    /**
     * @return int
     */
    public function scan(): int
    {
        try {

            $iterator = new RecursiveDirectoryIterator($this->path);

            /** @var SplFileInfo $fileInfo */
            foreach (new RecursiveIteratorIterator($iterator) as $fileInfo) {

                if (!$fileInfo->isFile() || $this->isIgnored($fileInfo)) {
                    continue;
                }

                $handle = $fileInfo->openFile();
                $this->mode === self::MODE_FILENAME
                    ? $this->matchFilename($handle)
                    : $this->matchContents($handle);
            }

            return $this->count();

        } catch (Exception $exception) {

            throw new RuntimeException($exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * @return void
     */
    protected function dumpContents(): void
    {
        foreach ($this->matches as $filename => $matches) {
            echo "$filename\n";
            foreach ($matches as $key => $line) {
                $this->dumpMatch($key, $line);
            }
            echo "\n";
        }
    }

    /**
     * @return void
     */
    protected function dumpFilenames(): void
    {
        foreach ($this->matches as $key => $filename) {
            $this->dumpMatch($key, $filename);
        }
    }

    /**
     * @param SplFileInfo $fileInfo
     * @return bool
     */
    protected function isIgnored(SplFileInfo $fileInfo): bool
    {
        if (!in_array($fileInfo->getExtension(), $this->extensions)) {
            return true;
        }

        $pathname = $fileInfo->getPathname();

        foreach ($this->ignore as $word) {
            if (str_contains($pathname, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param SplFileObject $handle
     * @return void
     */
    protected function matchContents(SplFileObject $handle): void
    {
        $lineNo = 0;

        while (!$handle->eof()) {

            $line = trim($handle->fgets());
            $lineNo++;

            if (empty($line)) {
                continue;
            }

            if (str_contains($line, $this->phrase)) {
                $this->matches[$handle->getPathname()][$lineNo] = $line;
            }
        }
    }

    /**
     * @param SplFileObject $handle
     * @return void
     */
    protected function matchFilename(SplFileObject $handle): void
    {
        if (str_contains($this->phrase, $handle->getPathname())) {
            $this->matches[] = $handle->getPathname();
        }
    }

    /**
     * @param int $key
     * @param string $match
     * @return void
     */
    private function dumpMatch(int $key, string $match)
    {
        $spaces = str_repeat(' ', 8 - strlen($key));
        echo "{$key}{$spaces}{$match}\n";
    }
}