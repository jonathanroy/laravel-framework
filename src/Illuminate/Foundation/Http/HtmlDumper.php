<?php

namespace Illuminate\Foundation\Http;

use Illuminate\Foundation\Concerns\ResolvesDumpSource;
use Symfony\Component\VarDumper\Caster\ReflectionCaster;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper as BaseHtmlDumper;
use Symfony\Component\VarDumper\VarDumper;
use Throwable;

class HtmlDumper extends BaseHtmlDumper
{
    use ResolvesDumpSource;

    /**
     * Where the source should be placed on "expanded" kind of dumps.
     *
     * @var string
     */
    const EXPANDED_SEPARATOR = 'class=sf-dump-expanded>';

    /**
     * Where the source should be placed on "non expanded" kind of dumps.
     *
     * @var string
     */
    const NON_EXPANDED_SEPARATOR = "\n</pre><script>";

    /**
     * The base path of the application.
     *
     * @var string
     */
    protected $basePath;

    /**
     * If the dumper is currently dumping.
     *
     * @var bool
     */
    protected $dumping = false;

    /**
     * Create a new HTML dumper instance.
     *
     * @param  string  $basePath
     * @return void
     */
    public function __construct($basePath)
    {
        parent::__construct();

        $this->basePath = $basePath;
    }

    /**
     * Create a new HTML dumper instance and register it as the default dumper.
     *
     * @param  string  $basePath
     * @return void
     */
    public static function register($basePath)
    {
        $cloner = tap(new VarCloner())->addCasters(ReflectionCaster::UNSET_CLOSURE_FILE_INFO);

        $dumper = new static($basePath);

        VarDumper::setHandler(fn ($value) => $dumper->dumpWithSource($cloner->cloneVar($value)));
    }

    /**
     * Dump a variable with its source file / line.
     *
     * @param  \Symfony\Component\VarDumper\Cloner\Data  $data
     * @return void
     */
    public function dumpWithSource(Data $data)
    {
        if ($this->dumping) {
            $this->dump($data);

            return;
        }

        $this->dumping = true;

        $output = (string) $this->dump($data, true);

        $output = match (true) {
            str_contains($output, static::EXPANDED_SEPARATOR) => str_replace(
                static::EXPANDED_SEPARATOR,
                static::EXPANDED_SEPARATOR.$this->getDumpSourceContent(),
                $output,
            ),
            str_contains($output, static::NON_EXPANDED_SEPARATOR) => str_replace(
                static::NON_EXPANDED_SEPARATOR,
                $this->getDumpSourceContent().static::NON_EXPANDED_SEPARATOR,
                $output,
            ),
            default => $output,
        };

        fwrite($this->outputStream, $output);

        $this->dumping = false;
    }

    /**
     * Get the dump's source HTML content.
     *
     * @return string
     */
    protected function getDumpSourceContent()
    {
        if (is_null($dumpSource = $this->resolveDumpSource())) {
            return '';
        }

        [$file, $relativeFile, $line] = $dumpSource;

        $source = sprintf('%s:%s', $relativeFile, $line);

        if ($editor = $this->editor()) {
            $source = sprintf(
                '<a href="%s://open?file=%s&line=%s">%s</a>',
                $editor,
                $file,
                $line,
                $source,
            );
        }

        return sprintf('<span style="color: #A0A0A0; font-family: Menlo"> // %s</span>', $source);
    }

    /**
     * Get the application editor, if applicable.
     *
     * @return string|null
     */
    protected function editor()
    {
        try {
            return config('app.editor');
        } catch (Throwable $e) {
            // ...
        }
    }
}