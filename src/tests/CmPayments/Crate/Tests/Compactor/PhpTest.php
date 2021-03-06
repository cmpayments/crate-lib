<?php

namespace CmPayments\Crate\Tests\Compactor;

use Herrera\Annotations\Tokenizer;
use CmPayments\Crate\Compactor\Php;
use Herrera\PHPUnit\TestCase;

class PhpTest extends TestCase
{
    /**
     * @var Php
     */
    private $php;

    public function testCompact()
    {
        $original = <<<ORIGINAL
<?php

/**
 * A comment.
 */
class AClass
{
    /**
     * A comment.
     */
    public function aMethod()
    {
        \$test = true;# a comment
    }
}
ORIGINAL;

        $expected = <<<EXPECTED
<?php




class AClass
{



public function aMethod()
{
\$test = true;
 }
}
EXPECTED;

        $this->assertEquals($expected, $this->php->compact($original));
    }

    public function testConvertWithAnnotations()
    {
        $tokenizer = new Tokenizer();
        $tokenizer->ignore(array('ignored'));

        $this->php->setTokenizer($tokenizer);

        $original = <<<ORIGINAL
<?php

/**
 * This is an example entity class.
 *
 * @Entity()
 * @Table(name="test")
 */
class Test
{
    /**
     * The unique identifier.
     *
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue()
     * @ORM\Id()
     */
    private \$id;

    /**
     * A foreign key.
     *
     * @ORM\ManyToMany(targetEntity="SomethingElse")
     * @ORM\JoinTable(
     *     name="aJoinTable",
     *     joinColumns={
     *         @ORM\JoinColumn(name="joined",referencedColumnName="foreign")
     *     },
     *     inverseJoinColumns={
     *         @ORM\JoinColumn(name="foreign",referencedColumnName="joined")
     *     }
     * )
     */
    private \$foreign;

    /**
     * @ignored
     */
    private \$none;
}
ORIGINAL;


        $expected = <<<EXPECTED
<?php

/**
@Entity()
@Table(name="test")


*/
class Test
{
/**
@ORM\Column(type="integer")
@ORM\GeneratedValue()
@ORM\Id()


*/
private \$id;

/**
@ORM\ManyToMany(targetEntity="SomethingElse")
@ORM\JoinTable(name="aJoinTable",joinColumns={@ORM\JoinColumn(name="joined",referencedColumnName="foreign")},inverseJoinColumns={@ORM\JoinColumn(name="foreign",referencedColumnName="joined")})










*/
private \$foreign;




private \$none;
}
EXPECTED;


        $this->assertEquals($expected, $this->php->compact($original));
    }

    public function testIssue14()
    {
        $original = <<<CODE
<?php

// autoload_real.php @generated by Composer

/**
 * @author Made Up <author@web.com>
 */
class ComposerAutoloaderInitc22fe6e3e5ad79bad24655b3e52999df
{
    private static \$loader;

    /** @inline annotation */
    public static function loadClassLoader(\$class)
    {
        if ('Composer\Autoload\ClassLoader' === \$class) {
            require __DIR__ . '/ClassLoader.php';
        }
    }

    public static function getLoader()
    {
        if (null !== self::\$loader) {
            return self::\$loader;
        }

        spl_autoload_register(array('ComposerAutoloaderInitc22fe6e3e5ad79bad24655b3e52999df', 'loadClassLoader'), true, true);
        self::\$loader = \$loader = new \Composer\Autoload\ClassLoader();
        spl_autoload_unregister(array('ComposerAutoloaderInitc22fe6e3e5ad79bad24655b3e52999df', 'loadClassLoader'));

        \$vendorDir = dirname(__DIR__);
        \$baseDir = dirname(\$vendorDir);

        \$includePaths = require __DIR__ . '/include_paths.php';
        array_push(\$includePaths, get_include_path());
        set_include_path(join(PATH_SEPARATOR, \$includePaths));

        \$map = require __DIR__ . '/autoload_namespaces.php';
        foreach (\$map as \$namespace => \$path) {
            \$loader->set(\$namespace, \$path);
        }

        \$map = require __DIR__ . '/autoload_psr4.php';
        foreach (\$map as \$namespace => \$path) {
            \$loader->setPsr4(\$namespace, \$path);
        }

        \$classMap = require __DIR__ . '/autoload_classmap.php';
        if (\$classMap) {
            \$loader->addClassMap(\$classMap);
        }

        \$loader->register(true);

        return \$loader;
    }
}

CODE;

        $expected = <<<CODE
<?php






class ComposerAutoloaderInitc22fe6e3e5ad79bad24655b3e52999df
{
private static \$loader;


public static function loadClassLoader(\$class)
{
if ('Composer\Autoload\ClassLoader' === \$class) {
require __DIR__ . '/ClassLoader.php';
}
}

public static function getLoader()
{
if (null !== self::\$loader) {
return self::\$loader;
}

spl_autoload_register(array('ComposerAutoloaderInitc22fe6e3e5ad79bad24655b3e52999df', 'loadClassLoader'), true, true);
self::\$loader = \$loader = new \Composer\Autoload\ClassLoader();
spl_autoload_unregister(array('ComposerAutoloaderInitc22fe6e3e5ad79bad24655b3e52999df', 'loadClassLoader'));

\$vendorDir = dirname(__DIR__);
\$baseDir = dirname(\$vendorDir);

\$includePaths = require __DIR__ . '/include_paths.php';
array_push(\$includePaths, get_include_path());
set_include_path(join(PATH_SEPARATOR, \$includePaths));

\$map = require __DIR__ . '/autoload_namespaces.php';
foreach (\$map as \$namespace => \$path) {
\$loader->set(\$namespace, \$path);
}

\$map = require __DIR__ . '/autoload_psr4.php';
foreach (\$map as \$namespace => \$path) {
\$loader->setPsr4(\$namespace, \$path);
}

\$classMap = require __DIR__ . '/autoload_classmap.php';
if (\$classMap) {
\$loader->addClassMap(\$classMap);
}

\$loader->register(true);

return \$loader;
}
}

CODE;

        $tokenizer = new Tokenizer();
        $tokenizer->ignore(array('author', 'inline'));

        $this->php->setTokenizer($tokenizer);

        $this->assertEquals(
            $expected,
            $this->php->compact($original)
        );
    }

    public function testSetTokenizer()
    {
        $tokenizer = new Tokenizer();

        $this->php->setTokenizer($tokenizer);

        $this->assertInstanceOf(
            'Herrera\\Annotations\\Convert\\ToString',
            $this->getPropertyValue($this->php, 'converter')
        );

        $this->assertSame(
            $tokenizer,
            $this->getPropertyValue($this->php, 'tokenizer')
        );
    }

    public function testSupports()
    {
        $this->assertTrue($this->php->supports('test.php'));
    }

    protected function setUp()
    {
        $this->php = new Php();
    }
}
