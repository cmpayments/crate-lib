<?php

namespace Herrera\Box;

use Herrera\Box\Compactor\Php;
use Herrera\Box\Exception\InvalidArgumentException;

/**
 * Generates a new PHP bootstrap loader stub for a Phar.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class StubGenerator
{
    /**
     * The list of allowed LSB init parameters
     *
     * @var array
     */
    private static $allowedLsbInitParams = array(
        'Provides',
        'Required-Start',
        'Required-Stop',
        'Default-Start',
        'Default-Stop',
        'Short-Description',
        'Description'
    );

    /**
     * The list of server variables that are allowed to be modified.
     *
     * @var array
     */
    private static $allowedMung = array(
        'PHP_SELF',
        'REQUEST_URI',
        'SCRIPT_FILENAME',
        'SCRIPT_NAME'
    );

    /**
     * The alias to be used in "phar://" URLs.
     *
     * @var string
     */
    private $alias;

    /**
     * The top header comment banner text.
     *
     * @var string.
     */
    private $banner = 'Generated by Box.

@link https://github.com/herrera-io/php-box/';

    /**
     * Embed the Extract class in the stub?
     *
     * @var boolean
     */
    private $extract = false;

    /**
     * The processed extract code.
     *
     * @var array
     */
    private $extractCode = array();

    /**
     * Force the use of the Extract class?
     *
     * @var boolean
     */
    private $extractForce = false;

    /**
     * The location within the Phar of index script.
     *
     * @var string
     */
    private $index;

    /**
     * Use the Phar::interceptFileFuncs() method?
     *
     * @var boolean
     */
    private $intercept = false;

    /**
     * include LSB Init standard parameters in stub header?
     *
     * @var boolean
     */
    private $lsbInit = false;

    /**
     * LSB Init parameter array.
     *
     * @var array
     */
    private $lsbInitParams = array();

    /**
     * The map for file extensions and their mimetypes.
     *
     * @var array
     */
    private $mimetypes = array();

    /**
     * The list of server variables to modify.
     *
     * @var array
     */
    private $mung = array();

    /**
     * The location of the script to run when a file is not found.
     *
     * @var string
     */
    private $notFound;

    /**
     * The rewrite function.
     *
     * @var string
     */
    private $rewrite;

    /**
     * The shebang line.
     *
     * @var string
     */
    private $shebang = '#!/usr/bin/env php';

    /**
     * Use Phar::webPhar() instead of Phar::mapPhar()?
     *
     * @var boolean
     */
    private $web = false;

    /**
     * Sets the alias to be used in "phar://" URLs.
     *
     * @param string $alias The alias.
     *
     * @return StubGenerator The stub generator.
     */
    public function alias($alias)
    {
        $this->alias = $alias;

        return $this;
    }

    /**
     * Sets the top header comment banner text.
     *
     * @param string $banner The banner text.
     *
     * @return StubGenerator The stub generator.
     */
    public function banner($banner)
    {
        $this->banner = $banner;

        return $this;
    }

    /**
     * Creates a new instance of the stub generator.
     *
     * @return StubGenerator The stub generator.
     */
    public static function create()
    {
        return new static();
    }

    /**
     * Embed the Extract class in the stub?
     *
     * @param boolean $extract Embed the class?
     * @param boolean $force   Force the use of the class?
     *
     * @return StubGenerator The stub generator.
     */
    public function extract($extract, $force = false)
    {
        $this->extract = $extract;
        $this->extractForce = $force;

        if ($extract) {
            $this->extractCode = array(
                'constants' => array(),
                'class' => array(),
            );

            $compactor = new Php();
            $code = file_get_contents(__DIR__ . '/Extract.php');
            $code = $compactor->compact($code);
            $code = preg_replace('/\n+/', "\n", $code);
            $code = explode("\n", $code);
            $code = array_slice($code, 2);

            foreach ($code as $i => $line) {
                if ((0 === strpos($line, 'use'))
                    && (false === strpos($line, '\\'))
                ) {
                    unset($code[$i]);
                } elseif (0 === strpos($line, 'define')) {
                    $this->extractCode['constants'][] = $line;
                } else {
                    $this->extractCode['class'][] = $line;
                }
            }
        }

        return $this;
    }

    /**
     * Sets location within the Phar of index script.
     *
     * @param string $index The index file.
     *
     * @return StubGenerator The stub generator.
     */
    public function index($index)
    {
        $this->index = $index;

        return $this;
    }

    /**
     * Use the Phar::interceptFileFuncs() method in the stub?
     *
     * @param boolean $intercept Use interceptFileFuncs()?
     *
     * @return StubGenerator The stub generator.
     */
    public function intercept($intercept)
    {
        $this->intercept = $intercept;

        return $this;
    }

    /**
     * Sets an LSB init parameter
     *
     * @param string $param The name of the parameter
     * @param string $value The value of the parameter
     *
     * @return StubGenerator The stub generator.
     *
     * @throws Exception\Exception
     * @throws InvalidArgumentException If the list contains an invalid value.
     */
    public function lsbInitParam($param, $value)
    {
        $param = implode('-', array_map('ucfirst', explode('-', strtolower($param))));

        if (false === in_array($param, self::$allowedLsbInitParams)) {
            throw InvalidArgumentException::create(
                'The LSB init parameter "%s" is not allowed.',
                $param
            );
        }

        $this->lsbInitParams[$param] = $value;
        $this->lsbInit = true;

        return $this;
    }

    /**
     * Generates the stub.
     *
     * @return string The stub.
     */
    public function generate()
    {
        $stub = array();

        if ('' !== $this->shebang) {
            $stub[] = $this->shebang;
        }

        $stub[] = '<?php';

        if ($this->lsbInit) {
            $stub[] = '/*';
            $stub[] = '### BEGIN INIT INFO';

            $maxKeyLength = max(array_map('strlen', array_keys($this->lsbInitParams)));
            $lsbInitParams = array();

            foreach (self::$allowedLsbInitParams as $allowedParam) {
                if (isset($this->lsbInitParams[$allowedParam])) {
                    $lsbInitParams[$allowedParam] = $this->lsbInitParams[$allowedParam];
                }
            }

            foreach ($lsbInitParams as $param => $value) {
                $stub[] = '# ' . str_pad($param . ':', $maxKeyLength + 3) . $value;
            }

            unset ($allowedParam, $param, $value);

            $stub[] = '### END INIT INFO';
            $stub[] = '*/';
        }

        if (null !== $this->banner) {
            $stub[] = $this->getBanner();
        }

        if ($this->extract) {
            $stub[] = join("\n", $this->extractCode['constants']);

            if ($this->extractForce) {
                $stub = array_merge($stub, $this->getExtractSections());
            }
        }

        $stub = array_merge($stub, $this->getPharSections());

        if ($this->extract) {
            if ($this->extractForce) {
                if ($this->index && !$this->web) {
                    $stub[] = "require \"\$dir/{$this->index}\";";
                }
            } else {
                end($stub);

                $stub[key($stub)] .= ' else {';

                $stub = array_merge($stub, $this->getExtractSections());

                if ($this->index) {
                    $stub[] = "require \"\$dir/{$this->index}\";";
                }

                $stub[] = '}';
            }

            $stub[] = join("\n", $this->extractCode['class']);
        }

        $stub[] = "__HALT_COMPILER();";

        return join("\n", $stub);
    }

    /**
     * Sets the map for file extensions and their mimetypes.
     *
     * @param array $mimetypes The map.
     *
     * @return StubGenerator The stub generator.
     */
    public function mimetypes(array $mimetypes)
    {
        $this->mimetypes = $mimetypes;

        return $this;
    }

    /**
     * Sets the list of server variables to modify.
     *
     * @param array $list The list.
     *
     * @return StubGenerator The stub generator.
     *
     * @throws Exception\Exception
     * @throws InvalidArgumentException If the list contains an invalid value.
     */
    public function mung(array $list)
    {
        foreach ($list as $value) {
            if (false === in_array($value, self::$allowedMung)) {
                throw InvalidArgumentException::create(
                    'The $_SERVER variable "%s" is not allowed.',
                    $value
                );
            }
        }

        $this->mung = $list;

        return $this;
    }

    /**
     * Sets the location of the script to run when a file is not found.
     *
     * @param string $script The script.
     *
     * @return StubGenerator The stub generator.
     */
    public function notFound($script)
    {
        $this->notFound = $script;

        return $this;
    }

    /**
     * Sets the rewrite function.
     *
     * @param string $function The function.
     *
     * @return StubGenerator The stub generator.
     */
    public function rewrite($function)
    {
        $this->rewrite = $function;

        return $this;
    }

    /**
     * Sets the shebang line.
     *
     * @param string $shebang The shebang line.
     *
     * @return StubGenerator The stub generator.
     */
    public function shebang($shebang)
    {
        $this->shebang = $shebang;

        return $this;
    }

    /**
     * Use Phar::webPhar() instead of Phar::mapPhar()?
     *
     * @param boolean $web Use Phar::webPhar()?
     *
     * @return StubGenerator The stub generator.
     */
    public function web($web)
    {
        $this->web = $web;

        return $this;
    }

    /**
     * Escapes an argument so it can be written as a string in a call.
     *
     * @param string $arg   The argument.
     * @param string $quote The quote.
     *
     * @return string The escaped argument.
     */
    private function arg($arg, $quote = "'")
    {
        return $quote . addcslashes($arg, $quote) . $quote;
    }

    /**
     * Returns the alias map.
     *
     * @return string The alias map.
     */
    private function getAlias()
    {
        $stub = '';
        $prefix = '';

        if ($this->extractForce) {
            $prefix = '$dir/';
        }

        if ($this->web) {
            $stub .= 'Phar::webPhar(' . $this->arg($this->alias);

            if ($this->index) {
                $stub .= ', ' . $this->arg($prefix . $this->index, '"');

                if ($this->notFound) {
                    $stub .= ', ' . $this->arg($prefix . $this->notFound, '"');

                    if ($this->mimetypes) {
                        $stub .= ', ' . var_export(
                            $this->mimetypes,
                            true
                        );

                        if ($this->rewrite) {
                            $stub .= ', ' . $this->arg($this->rewrite);
                        }
                    }
                }
            }

            $stub .= ');';
        } else {
            $stub .= 'Phar::mapPhar(' . $this->arg($this->alias) . ');';
        }

        return $stub;
    }

    /**
     * Returns the banner after it has been processed.
     *
     * @return string The processed banner.
     */
    private function getBanner()
    {
        $banner = "/**\n * ";
        $banner .= str_replace(
            " \n",
            "\n",
            str_replace("\n", "\n * ", $this->banner)
        );

        $banner .= "\n */";

        return $banner;
    }

    /**
     * Returns the self extracting sections of the stub.
     *
     * @return array The stub sections.
     */
    private function getExtractSections()
    {
        return array(
            '$extract = new Extract(__FILE__, Extract::findStubLength(__FILE__));',
            '$dir = $extract->go();',
            'set_include_path($dir . PATH_SEPARATOR . get_include_path());',
        );
    }

    /**
     * Returns the sections of the stub that use the Phar class.
     *
     * @return array The stub sections.
     */
    private function getPharSections()
    {
        $stub = array(
            'if (class_exists(\'Phar\')) {',
            $this->getAlias(),
        );

        if ($this->intercept) {
            $stub[] = "Phar::interceptFileFuncs();";
        }

        if ($this->mung) {
            $stub[] = 'Phar::mungServer(' . var_export($this->mung, true) . ");";
        }

        if ($this->index && !$this->web && !$this->extractForce) {
            $stub[] = "require 'phar://' . __FILE__ . '/{$this->index}';";
        }

        $stub[] = '}';

        return $stub;
    }
}
